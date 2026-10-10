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
use Illuminate\Support\Facades\DB;
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

    /**
     * Status aset yang boleh dipilih user dari form master (Aturan 15).
     * `kondisi` tetap field terpisah dan tidak bercampur dengan status.
     */
    const STATUS_USER = ['aktif', 'nonaktif'];

    /**
     * Status milik workflow penghapusan — tidak boleh ditulis lewat form.
     */
    const STATUS_SISTEM = ['diusulkan_hapus', 'dihapuskan'];

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
     * Cakupan ditentukan AsetScope: Admin Aset melihat seluruh aset;
     * Admin Bidang hanya lokasi Dinas; Admin UPT hanya lokasi UPT-nya;
     * Pegawai mengikuti skpd-nya (UPT -> lokasi UPT, Bidang/Sekretariat ->
     * lokasi Dinas); identitas invalid/role tak dikenal -> nol baris.
     * Kedua pembatasan ditegakkan oleh AsetScope di dalam query — bukan
     * dengan menyembunyikan menu.
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
        $search = trim((string) $request->query('search'));
        $penempatan = $request->query('penempatan'); // 'pemegang' | 'tanpa_pemegang' | null (semua)
        $pemegangId = $request->query('pemegang');

        // Filter status: whitelist agar querystring acak tidak menghasilkan
        // error atau membocorkan baris berstatus sistem.
        $statusFilter = $request->query('status');
        $statusFilter = in_array($statusFilter, self::STATUS_USER, true) ? $statusFilter : null;

        $isAdminAset = $this->isAdminAset();

        $asetList = Aset::with(['barang', 'pemegangSaatIni.pegawai', 'penempatanAktif.ruangan', 'skpd', 'lokasi'])
            ->barang()
            ->whereIn('status_aset', self::STATUS_USER)
            ->tap(fn ($q) => $this->scope->terapkan($q))
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nomor_kartu_barang', 'like', "%{$search}%")
                        ->orWhere('merk', 'like', "%{$search}%")
                        ->orWhereHas('barang', fn ($b) => $b->where('nama_barang', 'like', "%{$search}%"))
                        ->orWhereHas('pemegangSaatIni.pegawai', fn ($p) => $p->where('nama_pegawai', 'like', "%{$search}%"));
                });
            })
            ->when($statusFilter, fn ($query, $status) => $query->where('status_aset', $status))
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
            // Dropdown pemegang pada form Tambah/Edit. `skpd` ikut di-eager-load
            // karena option Pemegang mencarry nama SKPD untuk pratinjau turunan.
            $allPegawai = $batasiPegawai(Pegawai::query())
                ->with('skpd')
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
                ->with(['lokasi', 'skpd'])
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

        $pegawai->load(['skpd', 'ruangan']);

        $asetList = PemegangAset::with('aset.barang.kategori')
            ->where('id_pegawai', $pegawai->id_pegawai)
            ->where('status', 'aktif')
            ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false)->whereIn('status_aset', self::STATUS_USER))
            ->orderByDesc('id_pemegang')
            ->get();

        $isAdminAset = $this->isAdminAset();

        // Kandidat "Pasang Aset": master barang aktif yang belum berpemegang.
        // Aset diusulkan_hapus / dihapuskan tidak boleh dipasang lagi.
        $asetPasang = $this->asetPasangKandidat();

        // Berkas SPPBI bertanda tangan: satu dokumen aktif per pegawai.
        $pegawai->load('dokumenSppbi');

        return view('aset-barang.show', compact(
            'pegawai', 'asetList', 'isAdminAset', 'asetPasang'
        ));
    }

    /**
     * Master aset barang aktif dalam cakupan user yang belum mempunyai
     * pemegang aktif — kandidat Pasang Aset (ke pegawai maupun ke ruangan).
     *
     * Cakupan ditentukan `AsetScope::terapkan()`; logika hak akses TIDAK
     * ditulis ulang di sini agar konsisten dengan daftar Aset Barang.
     *
     * @param  bool  $harusTanpaPenempatan  true untuk Pasang Aset ke Ruangan
     */
    private function asetPasangKandidat(bool $harusTanpaPenempatan = false)
    {
        return Aset::with('barang')
            ->barang()
            ->where('status_aset', 'aktif')
            ->whereDoesntHave('pemegangSaatIni')
            ->when($harusTanpaPenempatan, fn ($q) => $q->whereDoesntHave('penempatanAktif'))
            ->tap(fn ($q) => $this->scope->terapkan($q))
            ->orderBy('nomor_kartu_barang')
            // Hanya kolom yang benar-benar ada di tabel `aset`. Nama barang
            // dibaca lewat relasi `barang` (tabel master_barang).
            ->get(['id_aset', 'nomor_kartu_barang', 'id_barang']);
    }

    /**
     * Penempatan awal aset ke pegawai: memasang MASTER aset yang sudah ada.
     *
     * Tidak membuat record `aset` baru dan tidak menulis `mutasi_aset` /
     * `detail_mutasi_aset` (Aturan 3 & 10). Ruangan tidak ada di form —
     * server menentukannya dari ruang kerja pegawai (Aturan 4/11/18).
     */
    public function pasangAsetKePegawai(Pegawai $pegawai, Aset $aset)
    {
        $this->authorizeAdminAset();

        abort_if((bool) $aset->is_kendaraan, 422, 'Aset kendaraan tidak dapat dipasang sebagai aset barang.');
        abort_if($aset->status_aset !== 'aktif', 422, 'Hanya aset berstatus aktif yang dapat dipasang.');

        $this->authorizeAset($aset, 'memasang aset ke pegawai');

        // Ruangan TIDAK diterima dari request. Sumbernya tunggal: ruang kerja
        // pegawai. Bila pegawai belum punya ruangan, penempatan tetap sah dan
        // aset akan tampil tanpa ruangan (kondisi master "berpemegang tanpa ruang").
        $ruanganId = $pegawai->id_ruangan;

        // Row locking + recheck: tabel pemegang_aset/penempatan_aset tidak punya
        // unique index untuk baris aktif, jadi duplikasi dicegah di sini.
        $result = DB::transaction(function () use ($aset, $pegawai, $ruanganId) {
            $asetLock = Aset::where('id_aset', $aset->id_aset)->lockForUpdate()->first();

            if ($asetLock->status_aset !== 'aktif') {
                throw ValidationException::withMessages([
                    'id_aset' => 'Hanya aset berstatus aktif yang dapat dipasang.',
                ]);
            }

            if ($asetLock->pemegangSaatIni()->exists()) {
                throw ValidationException::withMessages([
                    'id_aset' => 'Aset sudah dipegang '.$asetLock->pemegangSaatIni->pegawai?->nama_pegawai
                        .'. Gunakan Mutasi → Ganti Pemegang.',
                ]);
            }

            PemegangAset::create([
                'id_aset' => $asetLock->id_aset,
                'id_pegawai' => $pegawai->id_pegawai,
                'tanggal_mulai' => now()->toDateString(),
                'status' => 'aktif',
            ]);

            if ($ruanganId) {
                // Ruangan warisan pemegang juga harus berada dalam cakupan user.
                $this->authorizeRuangan($ruanganId, 'memasang aset ke pegawai');

                $this->placeAtRoom($asetLock, $ruanganId);

                // SKPD & Lokasi ikut master: Pemegang -> pegawai.id_skpd,
                // effective room -> ruangan.id_lokasi. Tidak ada input admin.
                AsetPenempatan::sinkronAset($asetLock, $pegawai->id_pegawai, $ruanganId);
            }

            return $asetLock->fresh();
        });

        return response()->json([
            'success' => true,
            'message' => 'Aset berhasil dipasang ke '.$pegawai->nama_pegawai.'.',
            'warning' => $ruanganId
                ? null
                : 'Pegawai belum punya ruangan. Aset akan tampil tanpa ruangan sampai ruangan pegawai diisi.',
            'ruangan' => $ruanganId ? Ruangan::find($ruanganId)?->nama_ruangan : null,
            'id_lokasi' => $result->id_lokasi,
        ]);
    }

    /**
     * Tambah aset dari tabel flat (tanpa pegawai di route).
     *
     * SATU-SATUNYA tempat pembuatan master aset (Aturan 2). Detail Pegawai
     * dan Detail Ruangan hanya memasang master yang sudah ada.
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

        // Effective room ditetapkan SATU KALI di awal, sebelum ada tulisan DB:
        //   - ada pemegang  -> ruang kerja pemegang (form diabaikan)
        //   - tanpa pemegang -> ruangan yang dipilih admin (boleh kosong)
        $ruanganEfektif = $pegawaiId
            ? AsetPenempatan::ruanganKerjaPemegang($pegawaiId)
            : ($request->input('id_ruangan') ?: null);

        // Otorisasi ruang di luar cakupan HARUS ditolak sebelum Aset::create()
        // agar penolakan tidak meninggalkan baris yatim. Ruangan kosong sah
        // (master tanpa pemegang & tanpa ruangan); aset tanpa `id_lokasi`
        // otomatis fail-closed oleh AsetScope.
        if ($ruanganEfektif) {
            $this->authorizeRuangan($ruanganEfektif, 'menambah aset');
        }

        // `id_skpd` dan `id_lokasi` TIDAK PERNAH dari request. Satu-satunya
        // sumber: Pemegang->pegawai.id_skpd / Ruangan.id_skpd, dan
        // effective room->ruangan.id_lokasi (lihat AsetPenempatan).
        $data['id_skpd'] = AsetPenempatan::skpdUntuk($pegawaiId, $ruanganEfektif);
        $data['id_lokasi'] = AsetPenempatan::lokasiUntukRuangan($ruanganEfektif);

        $aset = Aset::create($data);

        // Alur manual: aset melekat langsung ke ruangan, tanpa pemegang.
        if (! $pegawaiId) {
            $this->placeAtRoom($aset, $ruanganEfektif);

            return null;
        }

        // Alur dengan pemegang: aset dipegang pegawai.
        PemegangAset::create([
            'id_aset' => $aset->id_aset,
            'id_pegawai' => $pegawaiId,
            'tanggal_mulai' => now()->toDateString(),
            'status' => 'aktif',
        ]);

        $this->placeAtRoom($aset, $ruanganEfektif);

        if (! $ruanganEfektif) {
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
     * Tempatkan aset secara manual ke ruangan tertentu (tanpa pemegang).
     */
    private function placeAtRoom(Aset $aset, ?int $ruanganId): ?int
    {
        return $aset->placeAtRoom($ruanganId);
    }

    /**
     * Tutup penempatan aktif tanpa membuka yang baru.
     *
     * Dipakai saat asset harus berada tanpa ruangan (mis. pemegang baru belum
     * punya ruang kerja). `Aset::placeAtRoom()` tidak menutup penempatan lama
     * saat dipanggil dengan null, jadi penutupan dilakukan di sini agar
     * `penempatan_aset` dan `aset.id_lokasi` tetap konsisten.
     */
    private function tutupPenempatanAktif(Aset $aset): void
    {
        PenempatanAset::where('id_aset', $aset->id_aset)
            ->where('status', 'aktif')
            ->update(['status' => 'tidak aktif', 'tanggal_selesai' => now()->toDateString()]);

        $aset->unsetRelation('penempatanAktif');
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
            // SKPD & Lokasi = nilai turunan yang SUDAH dihitung server; dikirim
            // ke form hanya sebagai tampilan (form tidak punya name untuk keduanya).
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
        // `skpd` di-eager-load: option Pemegang pada form Edit mencarry nama
        // SKPD untuk pratinjau turunan (nilai tetap dihitung server).
        $query = Pegawai::with('skpd')->orderBy('nama_pegawai');

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
            ->with(['lokasi', 'skpd'])
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
     * Perbarui data aset.
     *
     * Berbeda dari create, form Edit MEMILIKI field Pemegang + Ruangan:
     *  - Aset SUDAH berpemegang aktif -> keduanya read-only. Crafted request
     *    yang mencoba mengganti pemegang/ruangan ditolak 422; perubahan hanya
     *    lewat Mutasi → Ganti Pemegang.
     *  - Aset BELUM berpemegang -> Pemegang boleh diisi (initial assignment)
     *    dan Ruangan boleh dipilih. Ini initial assignment, BUKAN mutasi:
     *    tidak ada baris MutasiAset/DetailMutasiAset yang dibuat.
     */
    public function update(Request $request, Aset $aset)
    {
        $this->authorizeAdminAset();
        $this->authorizeAset($aset, 'memperbarui aset');

        // Status sistem milik workflow penghapusan tidak boleh ditimpa lewat
        // form edit (Aturan 15) — pengajuannya lewat aksi "Usulkan Hapus".
        abort_if(
            in_array($aset->status_aset, self::STATUS_SISTEM, true),
            422,
            'Aset berstatus '.$aset->status_aset.' tidak dapat diubah. Kelola lewat workflow penghapusan.'
        );

        $data = $this->validateUpdateData($request, $aset);
        $data['id_barang'] = $this->resolveBarang($request->input('nama_barang'));

        $pemegangAktif = $aset->pemegangSaatIni;
        $ruanganAktif = $aset->penempatanAktif?->id_ruangan;

        if ($pemegangAktif) {
            // (A) Aset sudah berpemegang: Pemegang & Ruangan terkunci.
            $this->tolakPerubahanPemegangSaatEdit($request, $pemegangAktif, $ruanganAktif);

            $pegawaiEfektif = (int) $pemegangAktif->id_pegawai;
            $ruanganEfektif = AsetPenempatan::ruanganKerjaPemegang($pegawaiEfektif);
        } else {
            // (B) Aset tanpa pemegang: initial assignment / pilih ruangan.
            $pegawaiDiminta = $request->input('id_pegawai');

            $pegawaiEfektif = $pegawaiDiminta !== null && $pegawaiDiminta !== ''
                ? (int) $pegawaiDiminta
                : null;

            $ruanganEfektif = $pegawaiEfektif
                ? AsetPenempatan::ruanganKerjaPemegang($pegawaiEfektif)
                : ($request->input('id_ruangan') ?: null);
        }

        // Otorisasi ruangan TIDAK pernah ditolak setelah ada tulisan: dicek
        // sebelum Aset::update() supaya 403 tidak meninggalkan perubahan.
        if ($ruanganEfektif) {
            $this->authorizeRuangan($ruanganEfektif, 'mengubah aset');
        }

        $aset->update($data);

        // Initial assignment: aset tanpa pemegang aktif boleh diberi pemegang
        // pertama kali lewat Edit. Bukan mutasi, jadi TIDAK ada MutasiAset /
        // DetailMutasiAset yang dicatat.
        if (! $pemegangAktif && $pegawaiEfektif) {
            PemegangAset::create([
                'id_aset' => $aset->id_aset,
                'id_pegawai' => $pegawaiEfektif,
                'tanggal_mulai' => now()->toDateString(),
                'status' => 'aktif',
            ]);
        }

        if ($ruanganEfektif) {
            $this->placeAtRoom($aset, $ruanganEfektif);
        } else {
            // Effective room kosong (tanpa pemegang, atau Pemegang belum punya
            // ruang kerja): penempatan lama ditutup supaya aset tidak diam-diam
            // masih "berada" di ruangan yang tak berlaku dan id_lokasi ikut NULL.
            $this->tutupPenempatanAktif($aset);
        }

        // SKPD + Lokasi SELALU dari effective source, bukan dari request.
        // Pemegang yang sudah aktif ikut diteruskan supaya SKPD-nya tetap
        // mengikuti pegawai (bukan ruangan, yang bisa NULL bila ruang bersama).
        AsetPenempatan::sinkronAset($aset, $pegawaiEfektif, $ruanganEfektif);

        return response()->json([
            'success' => true,
            'message' => 'Aset berhasil diperbarui.',
            'id_skpd' => $aset->fresh()->id_skpd,
            'nama_skpd' => $aset->fresh()->skpd?->nama_skpd ?? '',
            'id_lokasi' => $aset->fresh()->id_lokasi,
            'nama_lokasi' => $aset->fresh()->lokasi?->nama_lokasi ?? '',
            'id_ruangan' => $aset->fresh()->penempatanAktif?->id_ruangan,
            'id_pegawai' => $aset->fresh()->pemegangSaatIni?->id_pegawai,
        ]);
    }

    /**
     * Tolak crafted request yang mencoba mengganti Pemegang atau Ruangan pada
     * aset yang SUDAH memiliki pemegang aktif.
     *
     * Pemegang hanya berubah lewat Mutasi → Ganti Pemegang; Ruangan selalu
     * mengikuti ruang kerja pemegang. Value kosong TIDAK dianggap permintaan
     * perubahan (form read-only tidak mengirim key).
     */
    private function tolakPerubahanPemegangSaatEdit(Request $request, $pemegangAktif, ?int $ruanganAktif): void
    {
        $pegawaiDiminta = $request->input('id_pegawai');

        if ($pegawaiDiminta !== null && $pegawaiDiminta !== ''
            && (int) $pegawaiDiminta !== (int) $pemegangAktif->id_pegawai) {
            throw ValidationException::withMessages([
                'id_pegawai' => 'Pemegang aset diubah melalui Mutasi → Ganti Pemegang.',
            ]);
        }

        $ruanganDiminta = $request->input('id_ruangan');

        if ($ruanganDiminta !== null && $ruanganDiminta !== ''
            && (int) $ruanganDiminta !== (int) $ruanganAktif) {
            throw ValidationException::withMessages([
                'id_ruangan' => 'Ruangan mengikuti ruang kerja pemegang. Perubahan pemegang dilakukan melalui Mutasi → Ganti Pemegang.',
            ]);
        }
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

        // `id_skpd` SENGAJA TIDAK ada di kontrak input: SKPD aset bukan input
        // admin, tapi turunan master (pegawai/ruangan) yang dihitung server.
        $data = $request->validate([
            'tipe' => ['required', Rule::in(['pegawai', 'ruangan'])],
            'id_pegawai' => ['nullable', 'exists:pegawai,id_pegawai'],
            'id_ruangan' => ['nullable', 'exists:ruangan,id_ruangan'],
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
        // HANYA untuk aset TANPA pemegang aktif (Aturan 9 & 18). Barang yang
        // berpemegang mengikuti ruang kerja pemegangnya — bukan ruangan yang
        // berdiri sendiri, jadi Pindah Ruangan tidak boleh dipakai padanya.
        $ruanganBaru = $data['id_ruangan'] ?? null;

        if (! $ruanganBaru) {
            throw ValidationException::withMessages([
                'id_ruangan' => 'Pilih ruangan tujuan.',
            ]);
        }

        $this->authorizeRuangan((int) $ruanganBaru, 'memutasi aset ke ruangan');

        $pemegangAktif = $aset->pemegangSaatIni?->pegawai?->nama_pegawai;

        if ($pemegangAktif) {
            throw ValidationException::withMessages([
                'id_ruangan' => 'Aset memiliki pemegang; ruangan mengikuti pemegang. Gunakan Mutasi → Ganti Pemegang.',
            ]);
        }

        $ruanganLama = $aset->penempatanAktif?->id_ruangan;
        $lokasiLama = $aset->id_lokasi;

        $this->placeAtRoom($aset, $ruanganBaru);

        // SKPD + Lokasi untuk aset TANPA pemegang mengikuti ruangan tujuan:
        // `ruangan.id_skpd` dan `ruangan.id_lokasi`. Nilai dari request
        // (`id_skpd`) tidak pernah dipercaya.
        AsetPenempatan::sinkronAset($aset, null, $ruanganBaru);

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

        // Effective room = ruang kerja pemegang baru (null bila pegawai belum
        // punya ruangan). Ruangan lama dicatat sebagai riwayat sebelum berubah.
        $ruanganLama = $aset->penempatanAktif?->id_ruangan;
        $ruanganBaru = Pegawai::where('id_pegawai', $pemegangBaruId)->value('id_ruangan');

        // Otorisasi dijalankan SEBELUM ada satu pun penulisan database, supaya
        // penolakan tidak meninggalkan pemegang lama tertutup atau history
        // setengah jadi.
        if ($ruanganBaru) {
            $this->authorizeRuangan($ruanganBaru, 'memindahkan aset ke pemegang baru');
        }

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

        // Ruangan aset mengikuti ruang kerja pemegang baru.
        if ($ruanganBaru) {
            $this->placeAtRoom($aset, $ruanganBaru);
        } else {
            // Pemegang baru belum punya ruang: penempatan lama harus ditutup,
            // kalau tidak aset tetap "berada" di ruang lamanya.
            $this->tutupPenempatanAktif($aset);
        }

        // SKPD aset mengikuti `pegawai.id_skpd` PEMEGANG BARU, dan lokasi
        // mengikuti `ruangan.id_lokasi` ruang kerjanya. Keduanya NULL bila
        // efektif tidak ada — nilai lama tidak dipertahankan.
        AsetPenempatan::sinkronAset($aset, $pemegangBaruId, $ruanganBaru);

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

        $data = $request->validate([
            'id_pegawai' => ['nullable', 'exists:pegawai,id_pegawai'],
            'id_ruangan' => ['nullable', 'exists:ruangan,id_ruangan'],
            // `id_skpd` dan `id_lokasi` SENGAJA tidak ada di aturan validasi:
            // keduanya bukan input admin. Server menghitungnya dari
            // Pemegang->pegawai.id_skpd / Ruangan.id_skpd dan
            // effective room->ruangan.id_lokasi (lihat AsetPenempatan).
            'nama_barang' => ['required', 'string', 'max:100'],
            'nomor_kartu_barang' => ['required', 'string', 'max:255', Rule::unique('aset', 'nomor_kartu_barang')->ignore($asetId, 'id_aset')],
            'merk' => ['nullable', 'string', 'max:100'],
            'tanggal_pengadaan' => ['nullable', 'date'],
            'tanggal_perolehan' => ['nullable', 'date'],
            'tanggal_habis_pakai' => ['nullable', 'date'],
            'nilai_perolehan' => ['nullable', 'numeric'],
            'kondisi' => ['required', Rule::in(self::KONDISI)],
            'status_aset' => ['nullable', 'string', 'max:50'],
        ]);

        // Buang key turunan kalau crafted request tetap mengirimnya.
        unset($data['id_skpd'], $data['id_lokasi']);

        $data['status_aset'] = $this->normalizeStatusAset($data['status_aset'] ?? null);

        return $data;
    }

    /**
     * Normalisasi + whitelist status aset yang boleh ditulis user.
     *
     * Trim + lowercase, kosong menjadi "aktif", dan nilai di luar
     * {aktif, nonaktif} ditolak. Status sistem (diusulkan_hapus/dihapuskan)
     * TIDAK bisa diieuwati lewat form ini.
     */
    private function normalizeStatusAset(mixed $value): string
    {
        $status = mb_strtolower(trim((string) $value));

        if ($status === '') {
            return 'aktif';
        }

        if (! in_array($status, self::STATUS_USER, true)) {
            throw ValidationException::withMessages([
                'status_aset' => 'Status aset hanya boleh "aktif" atau "nonaktif".',
            ]);
        }

        return $status;
    }

    private function validateUpdateData(Request $request, ?Aset $aset = null): array
    {
        $asetId = $aset?->id_aset;

        $rules = [
            // `id_pegawai`/`id_ruangan` divalidasi di sini supaya initial
            // assignment lewat Edit punya rules yang sama dengan create.
            // `id_skpd` dan `id_lokasi` SENGAJA TIDAK divalidasi: keduanya
            // bukan input admin dan selalu dihitung ulang dari effective
            // source oleh AsetPenempatan::sinkronAset().
            'id_pegawai' => ['nullable', 'exists:pegawai,id_pegawai'],
            'id_ruangan' => ['nullable', 'exists:ruangan,id_ruangan'],
            'nama_barang' => ['required', 'string', 'max:100'],
            'nomor_kartu_barang' => ['required', 'string', 'max:255', Rule::unique('aset', 'nomor_kartu_barang')->ignore($asetId, 'id_aset')],
            'merk' => ['nullable', 'string', 'max:100'],
            'tanggal_pengadaan' => ['nullable', 'date'],
            'tanggal_perolehan' => ['nullable', 'date'],
            'tanggal_habis_pakai' => ['nullable', 'date'],
            'nilai_perolehan' => ['nullable', 'numeric'],
            'kondisi' => ['required', Rule::in(self::KONDISI)],
            'status_aset' => ['nullable', 'string', 'max:50'],
        ];

        $data = $request->validate($rules);

        // Buang key turunan kalau crafted request tetap mengirimnya.
        unset($data['id_skpd'], $data['id_lokasi']);

        $data['status_aset'] = $this->normalizeStatusAset($data['status_aset'] ?? null);

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
