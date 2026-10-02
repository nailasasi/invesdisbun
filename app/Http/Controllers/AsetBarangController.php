<?php

namespace App\Http\Controllers;

use App\Models\Aset;
use App\Models\DetailMutasiAset;
use App\Models\KategoriAset;
use App\Models\MasterBarang;
use App\Models\MutasiAset;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\PenempatanAset;
use App\Models\Ruangan;
use App\Models\Skpd;
use App\Models\Lokasi;
use App\Models\TemplateDokumen;
use App\Models\UsulanPenghapusan;
use App\Services\AsetPenempatan;
use App\Services\AsetScope;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class AsetBarangController extends Controller
{
    const KONDISI = ['Baik', 'Rusak Ringan', 'Rusak Berat'];

    const KATEGORI_MESIN = 'Peralatan dan Mesin';

    public function __construct(private readonly AsetScope $scope)
    {
    }

    /**
 * Apakah user yang sedang login punya role "Admin Aset".
 *
 * Memeriksa NAMA ROLE aktif milik user yang login, bukan role default atau
 * nilai yang di-hardcode. Perbandingan di-normalisasi (di-trim + case
 * insensitive) supaya data role yang spasi/kapitalisasinya tidak rapi tetap
 * dikenali.
 *
 * `AsetScope` tetap jadi sumber kebenaran utama; pemeriksaan kedua hanya
 * menutup kemungkinan scope dibangun dengan user yang salah sehingga akun
 * Admin Aset terjatuh ke mode baca. Keduanya diturunkan dari user yang login,
 * jadi tidak pernah memberi hak lebih dari role yang memang dimiliki.
 */
private function isAdminAset(): bool
    {
        if ($this->scope->isAdminAset()) {
            return true;
        }

        $namaRole = auth()->user()?->role?->nama_role;

        return is_string($namaRole)
            && strcasecmp(trim($namaRole), AsetScope::ROLE_ADMIN_ASET) === 0;
    }

    /**
     * Penjaga tambahan untuk operasi tulis (CUD + mutasi).
     *
     * Route sudah dilindungi middleware `role:Admin Aset`, tetapi oprasi
     * tulis dicek ulang di sini agar tetap aman bila dipanggil dari
     * controller lain, job, atau route tanpa middleware.
     */
    private function authorizeAdminAset(): void
    {
        abort_unless(
            $this->isAdminAset(),
            403,
            'Hanya Admin Aset yang dapat menambah, mengubah, dan mutasi aset.'
        );
    }

    /**
     * Pastikan aset ini berada dalam cakupan user saat ini.
     */
    private function authorizeAset(Aset $aset, string $aksi = 'mengakses'): void
    {
        abort_unless(
            $this->scope->bolehAksesAset($aset),
            403,
            $this->scope->pesanAkses($aksi)
        );
    }

    /**
     * Indeks Aset Barang: satu tabel flat untuk SEMUA role.
     *
     * Role bercakupan penuh (Admin Aset, Admin Bidang, Pegawai Dinas)
     * melihat seluruh aset dinas. User UPT hanya melihat aset yang
     * lokasinya berada di dalam cakupan UPT-nya. Kedua pembatasan ditegakkan
     * oleh AsetScope di dalam query — bukan dengan menyembunyikan menu.
     *
     * Tombol CUD hanya dirender untuk Admin Aset; role lain read-only.
     */
    public function index(Request $request)
    {
        return $this->adminIndex($request);
    }

    /**
     * Tabel flat seluruh aset dalam cakupan user.
     */
    private function adminIndex(Request $request)
    {
        $search = $request->query('search');
        $penempatan = $request->query('penempatan'); // 'pemegang' | 'tanpa_pemegang' | null (semua)
        $pemegangId = $request->query('pemegang');

        $isAdminAset = $this->isAdminAset();

        $asetList = Aset::with(['barang', 'pemegangSaatIni.pegawai', 'penempatanAktif.ruangan', 'skpd', 'lokasi'])
            ->barang()
            ->where('status_aset', 'aktif')
            ->tap(fn ($q) => $this->scope->terapkan($q))
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nomor_kartu_barang', 'like', "%{$search}%")
                        ->orWhere('merk', 'like', "%{$search}%")
                        ->orWhereHas('barang', fn ($b) => $b->where('nama_barang', 'like', "%{$search}%"))
                        ->orWhereHas('pemegangSaatIni.pegawai', fn ($p) => $p->where('nama_pegawai', 'like', "%{$search}%"));
                });
            })
            ->when($penempatan === 'pemegang', function ($query) {
                $query->whereHas('pemegangSaatIni');
            })
            ->when($penempatan === 'tanpa_pemegang', function ($query) {
                $query->whereDoesntHave('pemegangSaatIni');
            })
            ->when($pemegangId, function ($query, $pemegangId) {
                $query->whereHas('pemegangSaatIni', fn ($p) => $p->where('pemegang_aset.id_pegawai', $pemegangId));
            })
            ->orderByDesc('id_aset')
            ->paginate(10)
            ->withQueryString();

        // Batasi pilihan pegawai/ruangan/lokasi ke lokasi fisik dalam cakupan user.
        // PENTING: user bercakupan penuh tidak boleh mendapat filter apa pun —
        // tanpa penjagaan ini filter `id_lokasi IN (0)` membuat seluruh dropdown
        // kosong dan tabel Administrator ikut terpotong.
        $idLokasiCakupan = $this->scope->idLokasi() ?: [0];

        // Filter langsung pada kolom lokasi milik model (Lokasi, Ruangan).
        $batasiLokasi = function (Builder $query, string $kolom) use ($idLokasiCakupan) {
            if ($this->scope->cakupanPenuh()) {
                return $query;
            }

            return $query->whereIn($kolom, $idLokasiCakupan);
        };

        // Untuk Pegawai, lokasi fisik ada di tabel `ruangan`, jadi harus lewat
        // relasi — bukan `whereIn('ruangan.id_lokasi', ...)`.
        $batasiPegawai = function (Builder $query) use ($idLokasiCakupan) {
            if ($this->scope->cakupanPenuh()) {
                return $query;
            }

            return $query->whereHas('ruangan', fn ($r) => $r->whereIn('id_lokasi', $idLokasiCakupan));
        };

        // Filter pemegang: PEGAWAI yang saat ini memegang aset. Ini adalah
        // fitur baca, jadi tetap tersedia untuk semua role (termasuk user UPT
        // yang hanya read-only).
        $pemegangOptions = $batasiPegawai(
            Pegawai::whereHas('pemegangAset', function ($q) {
                $q->where('status', 'aktif')
                    ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false));
            })
        )
            ->orderBy('nama_pegawai')
            ->get(['id_pegawai', 'nama_pegawai']);

        // Dropdown form (Tambah/Edit/Mutasi) hanya dibutuhkan Admin Aset.
        // Role read-only tidak menjalankan query dropdown ini sama sekali.
        $allPegawai = $ruanganOptions = $skpdOptions = $lokasiOptions = collect();

        if ($isAdminAset) {
            // Dropdown pemegang pada form Tambah/Edit.
            $allPegawai = $batasiPegawai(Pegawai::query())
                ->orderBy('nama_pegawai')
                ->get(['id_pegawai', 'nama_pegawai', 'id_ruangan', 'id_skpd']);

            // Unit penanggung jawab aset = skpd (Sekretariat / Bidang / UPT).
            $skpdOptions = Skpd::whereNotNull('jenis_skpd')
                ->orderBy('jenis_skpd')
                ->orderBy('nama_skpd')
                ->get(['id_skpd', 'nama_skpd', 'jenis_skpd']);

            // Lokasi fisik aset — bebas dari unit penanggung jawab.
            $lokasiOptions = $batasiLokasi(
                Lokasi::where('status', 'Aktif')->orderBy('jenis_lokasi')->orderBy('nama_lokasi'),
                'id_lokasi'
            )->get(['id_lokasi', 'nama_lokasi', 'jenis_lokasi']);

            // Dropdown ruangan, mengikuti lokasi fisik.
            $ruanganOptions = $batasiLokasi(
                Ruangan::where('status', 'Aktif')->orderBy('nama_ruangan'),
                'id_lokasi'
            )
                ->with('lokasi')
                ->get(['id_ruangan', 'nama_ruangan', 'id_lokasi', 'id_skpd']);
        }

        $kondisiList = self::KONDISI;

        return view('aset-barang.admin-index', compact(
            'asetList',
            'pemegangOptions',
            'allPegawai',
            'ruanganOptions',
            'skpdOptions',
            'lokasiOptions',
            'kondisiList',
            'isAdminAset'
        ));
    }

    /**
     * Detail per orang: aset barang yang sedang dipegang (`pemegang_aset` aktif).
     */
    public function show(Pegawai $pegawai)
    {
        // User UPT tidak boleh membuka pegawai di luar cakupan lokasinya.
        if (! $this->scope->cakupanPenuh()) {
            $idLokasi = $this->scope->idLokasi();

            $dalamCakupan = Ruangan::where('id_ruangan', $pegawai->id_ruangan)
                ->whereIn('id_lokasi', $idLokasi ?: [0])
                ->exists();

            abort_unless($dalamCakupan, 403, $this->scope->pesanAkses('melihat aset pegawai'));
        }

        $pegawai->load('skpd');

        $asetList = PemegangAset::with('aset.barang.kategori')
            ->where('id_pegawai', $pegawai->id_pegawai)
            ->where('status', 'aktif')
            ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false))
            ->orderByDesc('id_pemegang')
            ->get();

        $isAdminAset = $this->isAdminAset();
        $kondisiList = self::KONDISI;
        $allPegawai = Pegawai::orderBy('nama_pegawai')->get(['id_pegawai', 'nama_pegawai', 'id_skpd']);
        $skpdOptions = Skpd::whereNotNull('jenis_skpd')
            ->orderBy('jenis_skpd')->orderBy('nama_skpd')
            ->get(['id_skpd', 'nama_skpd', 'jenis_skpd']);

        // Berkas SPPBI bertanda tangan: satu dokumen aktif per pegawai.
        $pegawai->load('dokumenSppbi');

        return view('aset-barang.show', compact(
            'pegawai', 'asetList', 'kondisiList', 'allPegawai', 'isAdminAset', 'skpdOptions'
        ));
    }

    /**
     * Tambah aset baru. Pemegang diambil dari request `id_pegawai`
     * (dropdown pada tabel flat) atau default pegawai route.
     */
    public function store(Request $request, Pegawai $pegawai)
    {
        $this->authorizeAdminAset();

        $pemegangId = $request->input('id_pegawai') ?: $pegawai->id_pegawai;

        $warning = $this->createAsetFor($request, $pemegangId);

        return response()->json([
            'success' => true,
            'message' => 'Aset berhasil ditambahkan.',
            'warning' => $warning,
        ]);
    }

    /**
     * Tambah aset dari tabel flat (tanpa pegawai di route).
     */
    public function storeFlat(Request $request)
    {
        $this->authorizeAdminAset();

        $warning = $this->createAsetFor($request, $request->input('id_pegawai'));

        return response()->json([
            'success' => true,
            'message' => 'Aset berhasil ditambahkan.',
            'warning' => $warning,
        ]);
    }

    private function createAsetFor(Request $request, ?int $pegawaiId): ?string
    {
        $data = $this->validateData($request);
        $data['id_barang'] = $this->resolveBarang($request->input('nama_barang'));

        $skpdPJ = $request->input('id_skpd');
        $ruanganId = $request->input('id_ruangan');

        // User UPT hanya boleh membuat aset di lokasi fisiknya sendiri.
        // Alur tanpa pemegang wajib pilih ruangan; alur ber-pemegang boleh
        // kosong (mengikuti ruang kerja pegawai).
        if ($ruanganId || ! $pemegangId) {
            $this->authorizeRuangan($ruanganId, 'menambah aset');
        }
        $this->authorizeLokasi($request->input('id_lokasi'), 'menambah aset');

        // Lokasi fisik aset mengikuti lokasi ruang yang dipilih.
        $lokasiId = $this->validasiLokasiDanRuangan($request, $ruanganId);

        $data['id_skpd'] = $skpdPJ ?: AsetPenempatan::skpdUntukPemegang($pemegangId);
        $data['id_lokasi'] = $lokasiId;

        $aset = Aset::create($data);

        // Alur manual: aset melekat langsung ke ruangan, tanpa pemegang.
        if (! $pemegangId) {
            $this->placeAtRoom($aset, $ruanganId);

            return null;
        }

        // Alur dengan pemegang: aset dipegang pegawai. Ruangan aset mengikuti
        // ruang kerja pemegang kecuali form menentukan ruang lain.
        PemegangAset::create([
            'id_aset' => $aset->id_aset,
            'id_pegawai' => $pemegangId,
            'tanggal_mulai' => now()->toDateString(),
            'status' => 'aktif',
        ]);

        $ruanganAset = $ruanganId ?: Pegawai::where('id_pegawai', $pemegangId)->value('id_ruangan');

        // Ruangan warisan pemegang juga harus berada dalam cakupan user.
        if ($ruanganAset) {
            $this->authorizeRuangan($ruanganAset, 'menambah aset');
        }

        $this->placeAtRoom($aset, $ruanganAset);

        if (! $ruanganAset) {
            return 'Pemegang belum punya ruangan. Aset akan tampil tanpa ruangan sampai ruangan pegawai diisi.';
        }

        return null;
    }

    /**
     * Pastikan user boleh memakai ruangan tertentu (create/mutasi).
     */
    private function authorizeRuangan(?int $ruanganId, string $aksi = 'mengakses aset'): void
    {
        abort_if(
            $ruanganId === null,
            422,
            'Ruangan tujuan wajib dipilih.'
        );

        abort_unless(
            Ruangan::where('id_ruangan', $ruanganId)->where('status', 'Aktif')->exists(),
            422,
            'Ruangan tujuan tidak ditemukan atau tidak aktif.'
        );

        abort_unless(
            $this->scope->bolehAksesRuangan($ruanganId),
            403,
            $this->scope->pesanAkses($aksi)
        );
    }

    /**
     * Pastikan user boleh memakai lokasi tertentu (create/edit aset).
     */
    private function authorizeLokasi(?int $lokasiId, string $aksi = 'mengakses aset'): void
    {
        if ($lokasiId === null) {
            return;
        }

        abort_unless(
            $this->scope->bolehAksesLokasi($lokasiId),
            403,
            $this->scope->pesanAkses($aksi)
        );
    }

    /**
     * Lokasi fisik aset mengikuti lokasi ruang yang dipilih. Bila form
     * mengirim lokasi yang tidak cocok dengan ruang tersebut, ditolak —
     * aset tidak boleh tercatat berada di dua tempat.
     */
    private function validasiLokasiDanRuangan(Request $request, ?int $ruanganId): ?int
    {
        $lokasiId = $request->input('id_lokasi');
        $lokasiRuangan = AsetPenempatan::lokasiUntukRuangan($ruanganId);

        if ($lokasiRuangan) {
            if ($lokasiId && (int) $lokasiId !== (int) $lokasiRuangan) {
                throw ValidationException::withMessages([
                    'id_lokasi' => 'Lokasi fisik tidak sesuai dengan ruangan yang dipilih. Pilih lokasi yang sama dengan ruang tersebut.',
                ]);
            }

            return $lokasiRuangan;
        }

        return $lokasiId;
    }

    /**
     * Tempatkan aset secara manual ke ruangan tertentu (tanpa pemegang).
     */
    private function placeAtRoom(Aset $aset, ?int $ruanganId): ?int
    {
        return $aset->placeAtRoom($ruanganId);
    }

    /**
     * Data aset sebagai JSON untuk modal edit.
     */
    public function showAset(Aset $aset)
    {
        $this->authorizeAset($aset, 'melihat detail aset');

        $pemegang = $aset->pemegangSaatIni;

        return response()->json([
            'id_aset' => $aset->id_aset,
            'id_barang' => $aset->id_barang,
            'nama_barang' => $aset->barang?->nama_barang ?? '',
            'id_pegawai' => $pemegang?->id_pegawai ?? null,
            'nama_pemegang' => $pemegang?->pegawai?->nama_pegawai ?? '',
            'id_ruangan' => $aset->penempatanAktif?->id_ruangan ?? null,
            'id_skpd' => $aset->id_skpd,
            'nama_skpd' => $aset->skpd?->nama_skpd ?? '',
            'id_lokasi' => $aset->id_lokasi,
            'nama_lokasi' => $aset->lokasi?->nama_lokasi ?? '',
            'nomor_kartu_barang' => $aset->nomor_kartu_barang,
            'merk' => $aset->merk,
            'tanggal_pengadaan' => $aset->tanggal_pengadaan?->format('Y-m-d'),
            'tanggal_perolehan' => $aset->tanggal_perolehan?->format('Y-m-d'),
            'tanggal_habis_pakai' => $aset->tanggal_habis_pakai?->format('Y-m-d'),
            'nilai_perolehan' => $aset->nilai_perolehan,
            'kondisi' => $aset->kondisi,
            'status_aset' => $aset->status_aset,
        ]);
    }

    /**
     * Detail aset (read-only): informasi lengkap + riwayat mutasi per barang.
     */
    public function detailAset(Aset $aset)
    {
        $this->authorizeAset($aset, 'melihat detail aset');

        $aset->load([
            'barang.kategori',
            'pemegangSaatIni.pegawai.skpd',
            'penempatanAktif.ruangan.skpd',
            'skpd',
            'lokasi',
        ]);

        $riwayatMutasi = $aset->mutasiDetails()
            ->with([
                'mutasi.userPenginput.pegawai',
                'mutasi.userPenginput.role',
                'pegawaiLama',
                'pegawaiBaru',
                'ruanganLama',
                'ruanganBaru',
            ])
            ->orderByDesc('id_detail')
            ->get();

        $kondisiList = self::KONDISI;
        $isAdminAset = $this->isAdminAset();
        $allPegawai = $this->pegawaiTerlihat()->get();
        $ruanganList = $this->ruanganTerlihat()->get();
        $skpdOptions = Skpd::whereNotNull('jenis_skpd')
            ->orderBy('jenis_skpd')->orderBy('nama_skpd')
            ->get(['id_skpd', 'nama_skpd', 'jenis_skpd']);
        $lokasiOptions = Lokasi::where('status', 'Aktif')
            ->orderBy('jenis_lokasi')->orderBy('nama_lokasi')
            ->get(['id_lokasi', 'nama_lokasi', 'jenis_lokasi']);

        return view('aset-barang.detail', compact(
            'aset',
            'riwayatMutasi',
            'kondisiList',
            'isAdminAset',
            'allPegawai',
            'ruanganList',
            'skpdOptions',
            'lokasiOptions'
        ));
    }

    /**
     * Pegawai yang boleh dipilih pada form mutasi — dibatasi cakupan lokasi.
     */
    private function pegawaiTerlihat(): Builder
    {
        $query = Pegawai::orderBy('nama_pegawai');

        if (! $this->scope->cakupanPenuh()) {
            $query->whereHas('ruangan', fn ($q) => $q->whereIn('id_lokasi', $this->scope->idLokasi() ?: [0]));
        }

        return $query;
    }

    /**
     * Ruangan yang boleh dipilih pada form mutasi — dibatasi cakupan lokasi.
     */
    private function ruanganTerlihat(): Builder
    {
        $query = Ruangan::where('status', 'Aktif')
            ->with('lokasi')
            ->orderBy('nama_ruangan');

        if (! $this->scope->cakupanPenuh()) {
            $query->whereIn('id_lokasi', $this->scope->idLokasi() ?: [0]);
        }

        return $query;
    }

    /**
     * Label QR untuk aset: menampilkan QR code nomor kartu barang sebagai PNG.
     */
    public function qrLabel(Aset $aset)
    {
        $this->authorizeAset($aset, 'mencetak label aset');

        $content = $aset->nomor_kartu_barang ?: ('aset-'.$aset->id_aset);

        $qr = \QrCode::format('svg')->size(300)->margin(1)->generate($content);

        return response($qr, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="label-'.$content.'.svg"',
        ]);
    }

    /**
     * Unduh label seluruh aset aktif pegawai dalam SATU sheet tunggal (.xlsx).
     * Blok label master (B2:F7) diduplikasi ke bawah berjeda 1 baris kosong via
     * duplicateStyle + mergeCells, lalu placeholder diganti per barang:
     * {no_kartu}, {nama_barang}, {merk_tipe}, {tahun}, {ruangan}, {lokasi_user}.
     */
    public function downloadSemuaLabel($id_pegawai)
    {
        $this->authorizeAdminAset();

        $pegawai = Pegawai::with([
            'pemegangAset' => function ($q) {
                $q->where('status', 'aktif')
                    ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false))
                    ->with(['aset.barang', 'aset.penempatanAktif.ruangan']);
            },
        ])->findOrFail($id_pegawai);

        $asetList = $pegawai->pemegangAset
            ->map(fn ($pemegang) => $pemegang->aset)
            ->filter()
            ->values();

        if ($asetList->isEmpty()) {
            return back()->with('error', 'Pegawai ini belum memegang aset aktif.');
        }

        $template = TemplateDokumen::where('kode_template', 'label')->first();
        if (! $template || ! $template->file_path || ! Storage::disk('public')->exists($template->file_path)) {
            return back()->with('error', 'File template label belum diunggah.');
        }

        try {
            $spreadsheet = IOFactory::load(Storage::disk('public')->path($template->file_path));

            // Hapus sheet tambahan jika template punya lebih dari 1 sheet bawaan.
            while ($spreadsheet->getSheetCount() > 1) {
                $spreadsheet->removeSheetByIndex(1);
            }

            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Daftar Label');

            // Dimensi 1 blok label: Baris 2 sampai 7, Kolom B sampai F
            $srcStartRow = 2;
            $srcEndRow = 7;
            $labelHeight = 6;
            $gap = 1; // 1 baris kosong sebagai pembatas potong gunting
            $step = $labelHeight + $gap; // Tiap label baru berjarak 7 baris ke bawah

            $colStart = Coordinate::columnIndexFromString('B');
            $colEnd = Coordinate::columnIndexFromString('F');

            // Catat seluruh posisi sel gabungan (merged cells) di blok B2:F7
            $baseMerges = [];
            foreach ($sheet->getMergeCells() as $mergeRange) {
                if (preg_match('/([A-Z]+)(\d+):([A-Z]+)(\d+)/', $mergeRange, $matches)) {
                    $r1 = (int) $matches[2];
                    $r2 = (int) $matches[4];
                    if ($r1 >= $srcStartRow && $r2 <= $srcEndRow) {
                        $baseMerges[] = [
                            'col1' => $matches[1],
                            'row1_offset' => $r1 - $srcStartRow,
                            'col2' => $matches[3],
                            'row2_offset' => $r2 - $srcStartRow,
                        ];
                    }
                }
            }

            // Ambil logo master yang ada di sheet (jika ada)
            $originalDrawings = $sheet->getDrawingCollection();
            $baseDrawing = null;
            $logoSourcePath = null;
            $logoTempPath = null;

            foreach ($originalDrawings as $drawing) {
                if ($drawing instanceof Drawing) {
                    // Ambil gambar yang posisinya berada di sekitar sel B2
                    if (str_starts_with($drawing->getCoordinates(), 'B')) {
                        $baseDrawing = $drawing;
                        break;
                    }
                }
            }

            if ($baseDrawing) {
                $logoPath = $baseDrawing->getPath();

                // Path gambar dari worksheet hasil load berbentuk zip://...#xl/media/xxx
                // yang tidak bisa dipakai langsung, jadi ekstrak biner media ke file sementara.
                if (str_starts_with($logoPath, 'zip://') && preg_match('/#(xl\/media\/.+)$/', $logoPath, $mediaMatch)) {
                    $xlsxPath = Storage::disk('public')->path($template->file_path);
                    $zip = new \ZipArchive;

                    if ($zip->open($xlsxPath) === true) {
                        $binary = $zip->getFromName($mediaMatch[1]);
                        $zip->close();

                        if ($binary !== false) {
                            $ext = strtolower(pathinfo($mediaMatch[1], PATHINFO_EXTENSION)) ?: 'png';
                            $logoTempPath = storage_path('app/private/temp/label_'.uniqid().'.'.$ext);
                            if (! is_dir(dirname($logoTempPath))) {
                                mkdir(dirname($logoTempPath), 0755, true);
                            }
                            file_put_contents($logoTempPath, $binary);
                            $logoSourcePath = $logoTempPath;
                        }
                    }
                } else {
                    $logoSourcePath = $logoPath;
                }
            }

            // 1. Gandakan layout blok kotak ke bawah di sheet yang sama
            for ($i = 1; $i < $asetList->count(); $i++) {
                $destStartRow = $srcStartRow + ($i * $step);

                for ($r = 0; $r < $labelHeight; $r++) {
                    $fromRow = $srcStartRow + $r;
                    $toRow = $destStartRow + $r;

                    // Samakan tinggi baris
                    $rowHeight = $sheet->getRowDimension($fromRow)->getRowHeight();
                    if ($rowHeight > 0) {
                        $sheet->getRowDimension($toRow)->setRowHeight($rowHeight);
                    }

                    for ($c = $colStart; $c <= $colEnd; $c++) {
                        $colStr = Coordinate::stringFromColumnIndex($c);

                        // Copy nilai & formula (setCellValue: sel tujuan masih baru)
                        $sheet->setCellValue($colStr.$toRow, $sheet->getCell($colStr.$fromRow)->getValue());

                        // Copy format background hijau, border, font
                        $sheet->duplicateStyle($sheet->getStyle($colStr.$fromRow), $colStr.$toRow);
                    }
                }

                // Terapkan merge cells pada blok baru
                foreach ($baseMerges as $m) {
                    $sheet->mergeCells(
                        $m['col1'].($destStartRow + $m['row1_offset']).':'
                            .$m['col2'].($destStartRow + $m['row2_offset'])
                    );
                }

                // 3. DUPLIKASI LOGO DISBUN KE KOTAK BARU
                if ($baseDrawing && $logoSourcePath && file_exists($logoSourcePath)) {
                    $newDrawing = new Drawing;
                    $newDrawing->setName($baseDrawing->getName());
                    $newDrawing->setDescription($baseDrawing->getDescription() ?? '');
                    $newDrawing->setPath($logoSourcePath);
                    $newDrawing->setHeight($baseDrawing->getHeight());
                    $newDrawing->setWidth($baseDrawing->getWidth());
                    $newDrawing->setOffsetX($baseDrawing->getOffsetX());
                    $newDrawing->setOffsetY($baseDrawing->getOffsetY());

                    // Pasang logo ke sel B di awal baris kotak baru (misal B9, B16, dst)
                    $newDrawing->setCoordinates('B'.$destStartRow);
                    $newDrawing->setWorksheet($sheet);
                }
            }

            // 2. Isi nilai & replace placeholder untuk setiap barang
            foreach ($asetList as $index => $aset) {
                $currentRow = $srcStartRow + ($index * $step);

                $noKartu = $aset->nomor_kartu_barang ?? '-';
                $namaBarang = $aset->barang->nama_barang ?? '-';
                $merk = $aset->merk ?? '-';
                $tahun = $aset->tanggal_perolehan ? Carbon::parse($aset->tanggal_perolehan)->format('Y') : date('Y');
                $lokasi = $pegawai->nama_pegawai;
                $ruangan = $aset->penempatanAktif?->ruangan?->nama_ruangan ?? 'Sekretariat';

                // Loop sel dalam batas kotak barang ini
                for ($r = $currentRow; $r < ($currentRow + $labelHeight); $r++) {
                    for ($c = $colStart; $c <= $colEnd; $c++) {
                        $colStr = Coordinate::stringFromColumnIndex($c);
                        $cell = $sheet->getCell($colStr.$r);
                        $val = (string) $cell->getValue();

                        if ($val !== '' && str_contains($val, '{')) {
                            $newVal = str_replace(
                                ['{no_kartu}', '{nama_barang}', '{merk_tipe}', '{tahun}', '{lokasi_user}', '{ruangan}'],
                                [$noKartu, $namaBarang, $merk, $tahun, $lokasi, $ruangan],
                                $val
                            );
                            $cell->setValue($newVal);
                        }
                    }
                }
            }

            // 3. Ekspor 1 sheet tunggal
            $namaFile = 'Label_Aset_'.Str::slug($pegawai->nama_pegawai, '_').'.xlsx';
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

            return response()->streamDownload(function () use ($writer, $logoTempPath) {
                $writer->save('php://output');
                if ($logoTempPath && is_file($logoTempPath)) {
                    @unlink($logoTempPath);
                }
            }, $namaFile, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$namaFile.'"',
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal memproses template label. '.$e->getMessage());
        }
    }

    /**
     * Unduh label SATU aset (.xlsx) dari template label master.
     * Hanya memanfaatkan blok awal (B2:F7) untuk mengisi data aset yang dipilih.
     */
    public function cetakLabelSatuan($id)
    {
        $this->authorizeAdminAset();

        $aset = Aset::with(['barang', 'penempatanAktif.ruangan'])->findOrFail($id);

        $template = TemplateDokumen::where('kode_template', 'label')->first();
        if (! $template || ! $template->file_path || ! Storage::disk('public')->exists($template->file_path)) {
            return back()->with('error', 'File template label belum diunggah.');
        }

        try {
            $spreadsheet = IOFactory::load(Storage::disk('public')->path($template->file_path));
            while ($spreadsheet->getSheetCount() > 1) {
                $spreadsheet->removeSheetByIndex(1);
            }

            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Label Aset');

            $srcStartRow = 2;
            $srcEndRow = 7;
            $labelHeight = 6;
            $colStart = Coordinate::columnIndexFromString('B');
            $colEnd = Coordinate::columnIndexFromString('F');

            $noKartu = $aset->nomor_kartu_barang ?? '-';
            $namaBarang = $aset->barang?->nama_barang ?? '-';
            $merk = $aset->merk ?? '-';
            $tahun = $aset->tanggal_perolehan ? Carbon::parse($aset->tanggal_perolehan)->format('Y') : date('Y');
            $ruangan = $aset->penempatanAktif?->ruangan?->nama_ruangan ?? 'Sekretariat';
            $lokasi = $aset->pemegangSaatIni?->pegawai?->nama_pegawai ?? $ruangan;

            for ($r = $srcStartRow; $r < ($srcStartRow + $labelHeight); $r++) {
                for ($c = $colStart; $c <= $colEnd; $c++) {
                    $colStr = Coordinate::stringFromColumnIndex($c);
                    $cell = $sheet->getCell($colStr.$r);
                    $val = (string) $cell->getValue();

                    if ($val !== '' && str_contains($val, '{')) {
                        $newVal = str_replace(
                            ['{no_kartu}', '{nama_barang}', '{merk_tipe}', '{tahun}', '{lokasi_user}', '{ruangan}'],
                            [$noKartu, $namaBarang, $merk, $tahun, $lokasi, $ruangan],
                            $val
                        );
                        $cell->setValue($newVal);
                    }
                }
            }

            $namaFile = 'Label_'.Str::slug($aset->barang?->nama_barang ?? 'Aset', '_').'.xlsx';
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $namaFile, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$namaFile.'"',
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal memproses template label. '.$e->getMessage());
        }
    }

    /**
     * Perbarui data aset (tanpa pemegang/ruangan).
     * Ganti pemegang / pindah ruangan dilakukan via aksi "Mutasi" terpisah.
     */
    public function update(Request $request, Aset $aset)
    {
        $this->authorizeAdminAset();
        $this->authorizeAset($aset, 'memperbarui aset');

        $data = $this->validateUpdateData($request, $aset);
        $data['id_barang'] = $this->resolveBarang($request->input('nama_barang'));

        // Unit penanggung jawab & lokasi fisik boleh disetel dari form edit.
        if ($request->filled('id_skpd')) {
            $data['id_skpd'] = $request->input('id_skpd');
        }

        if ($request->filled('id_lokasi')) {
            $data['id_lokasi'] = $request->input('id_lokasi');
        }

        $aset->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Aset berhasil diperbarui.',
        ]);
    }

    /**
     * Aksi mutasi terpisah: Ganti Pemegang (`tipe=pegawai`) atau
     * Pindah Ruangan (`tipe=ruangan`). Setiap perubahan tercatat di
     * tabel mutasi_aset + detail_mutasi_aset.
     */
    public function mutasi(Request $request, Aset $aset)
    {
        $this->authorizeAdminAset();
        $this->authorizeAset($aset, 'memutasi aset');

        $data = $request->validate([
            'tipe' => ['required', Rule::in(['pegawai', 'ruangan'])],
            'id_pegawai' => ['nullable', 'exists:pegawai,id_pegawai'],
            'id_ruangan' => ['nullable', 'exists:ruangan,id_ruangan'],
            'id_skpd' => ['nullable', 'exists:skpd,id_skpd'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['tipe'] === 'pegawai') {
            $pemegangBaru = $data['id_pegawai'] ?? null;
            if (! $pemegangBaru) {
                throw ValidationException::withMessages([
                    'id_pegawai' => 'Pilih pemegang baru untuk aset ini.',
                ]);
            }

            $pemegangLama = $aset->pemegangSaatIni?->pegawai?->id_pegawai;
            $mutasi = $this->mutatePemegang($aset, $pemegangLama, $pemegangBaru, $data['keterangan'] ?? null);

            // Auto-download BAST setelah reload: flash URL tersedia satu kali di blade.
            session()->flash('download_bast_url', route('mutasi-aset.bast.download', $mutasi->id_mutasi));

            $warning = null;
            $penempatanAktif = PenempatanAset::where('id_aset', $aset->id_aset)->where('status', 'aktif')->exists();
            if (! $penempatanAktif) {
                $warning = 'Pegawai belum punya ruangan. Aset akan tampil tanpa ruangan sampai ruangan pegawai diisi.';
            }

            return response()->json([
                'success' => true,
                'message' => 'Pemegang aset berhasil diganti.',
                'warning' => $warning,
            ]);
        }

        // tipe = ruangan (Pindah Ruangan / Lokasi Fisik).
        //
        // Berpemegang TIDAK lagi menjadi penghalang: pemindahan fisik adalah
        // proses mutasi tersendiri, terpisah dari perpindahan kepemilikan.
        // Lokasi aset mengikuti lokasi ruang tujuan, sedangkan unit
        // penanggung jawab hanya berubah bila diminta eksplisit.
        $ruanganBaru = $data['id_ruangan'] ?? null;

        if (! $ruanganBaru) {
            throw ValidationException::withMessages([
                'id_ruangan' => 'Pilih ruangan tujuan.',
            ]);
        }

        $ruanganLama = $aset->penempatanAktif?->id_ruangan;
        $lokasiLama = $aset->id_lokasi;

        $this->placeAtRoom($aset, $ruanganBaru);

        // Unit penanggung jawab hanya ikut berubah bila diminta.
        if (! empty($data['id_skpd'])) {
            $aset->update(['id_skpd' => $data['id_skpd']]);
        }

        if ($ruanganLama == $ruanganBaru) {
            return response()->json([
                'success' => true,
                'message' => 'Aset sudah berada di ruangan tersebut.',
            ]);
        }

        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Pindah Ruangan',
            'keterangan' => $data['keterangan'] ?? null,
            'id_user_penginput' => auth()->id(),
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $aset->id_aset,
            'pegawai_lama' => null,
            'pegawai_baru' => null,
            'ruangan_lama' => $ruanganLama,
            'ruangan_baru' => $ruanganBaru,
        ]);

        $lokasiBaru = $aset->fresh()->id_lokasi;
        $pesan = 'Aset berhasil dipindah ruangan.';

        if ($lokasiBaru !== $lokasiLama) {
            $lokasi = Lokasi::find($lokasiBaru);
            $pesan .= ' Lokasi fisik sekarang: '.($lokasi?->nama_lokasi ?? '-').'.';
        }

        return response()->json([
            'success' => true,
            'message' => $pesan,
        ]);
    }

    /**
     * Usulkan penghapusan aset (bukan hard delete, sesuai SOP BMD):
     * status aset -> 'diusulkan_hapus' (hilang dari daftar aktif) dan
     * catat usulan ke tabel usulan_penghapusans untuk diproses admin.
     */
    public function destroy(Aset $aset)
    {
        $this->authorizeAdminAset();
        $this->authorizeAset($aset, 'mengusulkan penghapusan aset');

        if ($aset->status_aset !== 'aktif') {
            return response()->json([
                'success' => false,
                'message' => 'Hanya aset berstatus aktif yang dapat diusulkan penghapusan.',
            ], 422);
        }

        if (UsulanPenghapusan::where('id_aset', $aset->id_aset)->where('status_usulan', 'diajukan')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Aset sudah memiliki usulan penghapusan yang aktif.',
            ], 422);
        }

        $aset->update(['status_aset' => 'diusulkan_hapus']);

        UsulanPenghapusan::create([
            'id_aset' => $aset->id_aset,
            'id_pegawai_penghapus' => auth()->user()?->pegawai?->id_pegawai,
            'tanggal_usulan' => now()->toDateString(),
            'alasan_penghapusan' => 'Lainnya',
            'status_usulan' => 'diajukan',
            'keterangan' => 'Diusulkan melalui tombol hapus pada daftar aset.',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Aset diusulkan penghapusan dan dikeluarkan dari daftar aset aktif.',
        ]);
    }

    /**
     * Tutup pemegang lama, buka pemegang baru, pindah ruangan, catat riwayat mutasi.
     */
    private function mutatePemegang(Aset $aset, $pemegangLamaId, $pemegangBaruId, ?string $keterangan): MutasiAset
    {
        $now = now()->toDateString();

        // Tutup pemegang lama
        PemegangAset::where('id_aset', $aset->id_aset)
            ->where('status', 'aktif')
            ->update(['status' => 'tidak aktif', 'tanggal_selesai' => $now]);

        // Buka pemegang baru
        PemegangAset::create([
            'id_aset' => $aset->id_aset,
            'id_pegawai' => $pemegangBaruId,
            'tanggal_mulai' => $now,
            'status' => 'aktif',
        ]);

        // Lokasi aset mengikuti lokasi ruang tujuan. Unit penanggung jawab tidak
        // ikut berubah — perpindahan kepemilikan bukan perpindahan fisik.
        $ruanganLama = $aset->penempatanAktif?->id_ruangan;
        $ruanganBaru = Pegawai::where('id_pegawai', $pemegangBaruId)->value('id_ruangan');

        if ($ruanganBaru) {
            $this->placeAtRoom($aset, $ruanganBaru);
        }

        // Catat mutasi sebagai riwayat
        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => $now,
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => $keterangan,
            'id_user_penginput' => auth()->id(),
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $aset->id_aset,
            'pegawai_lama' => $pemegangLamaId,
            'pegawai_baru' => $pemegangBaruId,
            'ruangan_lama' => $ruanganLama,
            'ruangan_baru' => $ruanganBaru,
        ]);

        return $mutasi;
    }

    /**
     * Tempatkan aset di ruangan kerja utama pegawai. Kembalikan id_ruangan (nullable).
     */
    private function validateData(Request $request, ?Aset $aset = null): array
    {
        $asetId = $aset?->id_aset;

        return $request->validate([
            'id_pegawai' => ['nullable', 'exists:pegawai,id_pegawai'],
            'id_ruangan' => ['nullable', 'exists:ruangan,id_ruangan'],
            'id_skpd' => ['nullable', 'exists:skpd,id_skpd'],
            'id_lokasi' => ['nullable', 'exists:lokasi,id_lokasi'],
            'nama_barang' => ['required', 'string', 'max:100'],
            'nomor_kartu_barang' => ['required', 'string', 'max:255', Rule::unique('aset', 'nomor_kartu_barang')->ignore($asetId, 'id_aset')],
            'merk' => ['nullable', 'string', 'max:100'],
            'tanggal_pengadaan' => ['nullable', 'date'],
            'tanggal_perolehan' => ['nullable', 'date'],
            'tanggal_habis_pakai' => ['nullable', 'date'],
            'nilai_perolehan' => ['nullable', 'numeric'],
            'kondisi' => ['required', Rule::in(self::KONDISI)],
            'status_aset' => ['required', 'string', 'max:50'],
        ]);
    }

    private function validateUpdateData(Request $request, ?Aset $aset = null): array
    {
        $asetId = $aset?->id_aset;

        $rules = [
            'id_skpd' => ['nullable', 'exists:skpd,id_skpd'],
            'id_lokasi' => ['nullable', 'exists:lokasi,id_lokasi'],
            'nama_barang' => ['required', 'string', 'max:100'],
            'nomor_kartu_barang' => ['required', 'string', 'max:255', Rule::unique('aset', 'nomor_kartu_barang')->ignore($asetId, 'id_aset')],
            'merk' => ['nullable', 'string', 'max:100'],
            'tanggal_pengadaan' => ['nullable', 'date'],
            'tanggal_perolehan' => ['nullable', 'date'],
            'tanggal_habis_pakai' => ['nullable', 'date'],
            'nilai_perolehan' => ['nullable', 'numeric'],
            'kondisi' => ['required', Rule::in(self::KONDISI)],
            'status_aset' => ['required', 'string', 'max:50'],
        ];

        $data = $request->validate($rules);

        return $data;
    }

    /**
     * Cari master_barang berdasarkan nama; jika belum ada, buat baru
     * dengan kategori "Peralatan dan Mesin" (hard-coded).
     */
    private function resolveBarang(string $namaBarang): ?int
    {
        $namaBarang = trim($namaBarang);

        $barang = MasterBarang::whereRaw('LOWER(nama_barang) = ?', [mb_strtolower($namaBarang)])->first();

        if ($barang) {
            return $barang->id_barang;
        }

        $kategori = KategoriAset::firstOrCreate(['nama_kategori' => self::KATEGORI_MESIN]);

        $barang = MasterBarang::create([
            'nama_barang' => $namaBarang,
            'id_kategori' => $kategori->id_kategori,
        ]);

        return $barang->id_barang;
    }
}
