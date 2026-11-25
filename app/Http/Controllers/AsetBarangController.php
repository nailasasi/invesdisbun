<?php

namespace App\Http\Controllers;

use App\Models\Aset;
use App\Models\DetailMutasiAset;
use App\Models\KategoriAset;
use App\Models\Kendaraan;
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
use App\Services\AsetMutasiService;
use App\Services\AsetPenempatan;
use App\Services\AsetScope;
use App\Services\TanggalAset;
use App\Services\UsulanPenghapusanService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use Throwable;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as SpreadsheetDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

    /**
     * Urutan kolom baku template import master Aset Barang.
     */
    const KOLOM_TEMPLATE_IMPORT = [
        'Nomor Kartu Barang',
        'Nama Barang',
        'Merk',
        'Tanggal Pengadaan',
        'Tanggal Habis Pakai',
        'Nilai Perolehan (Rp)',
        'Kondisi',
        'Status Aset',
    ];

    private readonly AsetScope $scope;
    private readonly ?UsulanPenghapusanService $usulanPenghapusan;
    private readonly AsetMutasiService $mutasiService;

    public function __construct(
        AsetScope $scope,
        ?UsulanPenghapusanService $usulanPenghapusan = null,
        ?AsetMutasiService $mutasiService = null,
    ) {
        $this->scope = $scope;
        $this->usulanPenghapusan = $usulanPenghapusan;
        $this->mutasiService = $mutasiService ?? app(AsetMutasiService::class);
    }

    /**
     * Resolver lazy untuk service usulan penghapusan. Parameter konstruktor
     * opsional supaya skrip lama yang meng-instance controller manual tetap
     * kompatibel; pada runtime normal container menyuntikkan service.
     */
    private function usulan(): UsulanPenghapusanService
    {
        return $this->usulanPenghapusan ?? app(UsulanPenghapusanService::class);
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

    private function isAdminUpt(): bool
    {
        if ($this->scope->isAdminUpt()) {
            return true;
        }

        $namaRole = auth()->user()?->role?->nama_role;

        return is_string($namaRole)
            && strcasecmp(trim($namaRole), AsetScope::ROLE_ADMIN_UPT) === 0;
    }

    private function canManageAset(): bool
    {
        return $this->isAdminAset() || $this->isAdminUpt();
    }

    private function canPrintLabel(): bool
    {
        return $this->isAdminAset() || $this->isAdminUpt();
    }

    private function authorizePengelolaAset(): void
    {
        abort_unless(
            $this->canManageAset(),
            403,
            'Hanya Admin Aset dan Admin UPT yang dapat mengelola data aset.'
        );
    }

    /**
     * Penjaga tambahan untuk operasi khusus Admin Aset (mis. SPPBI/Import).
     */
    private function authorizeAdminAset(): void
    {
        abort_unless(
            $this->isAdminAset(),
            403,
            'Hanya Admin Aset yang dapat melakukan tindakan ini.'
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

        // Filter lokasi bersumber dari penempatan AKTIF aset -> ruangan -> lokasi
        // (sama dengan sumber kolom Lokasi), bukan dari `aset.id_skpd`. Nilai
        // 'belum_ada' menandai aset tanpa penempatan/relasi lokasi yang valid.
        $lokasiFilter = $request->query('lokasi');

        // Filter status: whitelist agar querystring acak tidak menghasilkan
        // error atau membocorkan baris berstatus sistem.
        $statusFilter = $request->query('status');
        $statusFilter = in_array($statusFilter, self::STATUS_USER, true) ? $statusFilter : null;

        // Filter kondisi: hanya opsi yang diizinkan (Baik, Rusak Ringan).
        // Nilai kosong / di luar whitelist menampilkan semua kondisi.
        $kondisiFilter = $request->query('kondisi');
        $kondisiFilter = in_array($kondisiFilter, ['Baik', 'Rusak Ringan'], true) ? $kondisiFilter : null;

        $isAdminAset = $this->isAdminAset();
        $canManage = $this->canManageAset();
        $canPrintLabel = $this->canPrintLabel();

        $asetQuery = Aset::with(['barang', 'pemegangSaatIni.pegawai', 'penempatanAktif.ruangan.lokasi'])
            ->barang()
            ->whereIn('status_aset', self::STATUS_USER)
            ->tap(fn ($q) => $this->scope->terapkan($q))
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nomor_kartu_barang', 'like', "%{$search}%")
                        ->orWhere('merk', 'like', "%{$search}%")
                        ->orWhereHas('barang', function ($b) use ($search) {
                            $b->where('nama_barang', 'like', "%{$search}%")
                                ->orWhere('kode_barang', 'like', "%{$search}%");
                        })
                        ->orWhereHas('pemegangSaatIni.pegawai', fn ($p) => $p->where('nama_pegawai', 'like', "%{$search}%"));
                });
            })
            ->when($statusFilter, fn ($query, $status) => $query->where('status_aset', $status))
            ->when($kondisiFilter, fn ($query, $kondisi) => $query->where('aset.kondisi', $kondisi))
            // Filter lokasi = ruangan pada penempatan aktif aset. `belum_ada`
            // mencakup aset tanpa penempatan aktif atau tanpa relasi
            // ruangan/lokasi yang valid. Sumbernya sama dengan kolom Lokasi.
            ->when($lokasiFilter === 'belum_ada', function ($query) {
                $query->whereDoesntHave('penempatanAktif.ruangan.lokasi');
            })
            ->when($lokasiFilter && $lokasiFilter !== 'belum_ada', function ($query) use ($lokasiFilter) {
                $query->whereHas('penempatanAktif.ruangan', fn ($r) => $r->where('id_lokasi', $lokasiFilter));
            });

        // Pegawai: Aset yang sedang dipegang oleh pegawai yang login muncul di paling atas
        if ($this->scope->isPegawai() && ($idPegawaiLogin = auth()->user()?->id_pegawai)) {
            $asetQuery->orderByRaw(
                "CASE WHEN EXISTS (
                    SELECT 1 FROM pemegang_aset
                    WHERE pemegang_aset.id_aset = aset.id_aset
                      AND pemegang_aset.id_pegawai = ?
                      AND pemegang_aset.status = 'aktif'
                ) THEN 0 ELSE 1 END",
                [$idPegawaiLogin]
            );
        }

        $asetList = $asetQuery
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

        // Pilihan filter lokasi = master lokasi aktif yang terlihat user
        // (bukan daftar hardcode). Nilai opsi = `id_lokasi` yang sama dengan
        // kunci pada relasi penempatan aktif aset -> ruangan.
        $lokasiFilterOptions = $batasiLokasi(
            Lokasi::where('status', 'Aktif')->orderBy('jenis_lokasi')->orderBy('nama_lokasi'),
            'id_lokasi'
        )->get(['id_lokasi', 'nama_lokasi']);

        // Untuk Pegawai, lokasi fisik ada di tabel `ruangan`, jadi harus lewat
        // relasi — bukan `whereIn('ruangan.id_lokasi', ...)`.
        $batasiPegawai = function (Builder $query) use ($idLokasiCakupan) {
            if ($this->scope->cakupanPenuh()) {
                return $query;
            }

            return $query->whereHas('ruangan', fn ($r) => $r->whereIn('id_lokasi', $idLokasiCakupan));
        };

        // Dropdown form modal (Penempatan/Mutasi) hanya dibutuhkan pengelola aset (Admin Aset & Admin UPT).
        // Role read-only tidak menjalankan query dropdown ini sama sekali karena filter toolbar telah dihapus.
        $pemegangOptions = $allPegawai = $ruanganOptions = $skpdOptions = $lokasiOptions = collect();

        if ($canManage) {
            // Dropdown pegawai untuk modal Penempatan Awal, Edit Penempatan, dan Mutasi.
            $pemegangOptions = $batasiPegawai(Pegawai::query())
                ->with(['skpd', 'ruangan.lokasi'])
                ->orderBy('nama_pegawai')
                ->get(['id_pegawai', 'nama_pegawai', 'id_ruangan', 'id_skpd']);

            $allPegawai = $pemegangOptions;

            // Dropdown ruangan, mengikuti lokasi fisik.
            $ruanganOptions = $batasiLokasi(
                Ruangan::where('status', 'Aktif')->orderBy('nama_ruangan'),
                'id_lokasi'
            )
                ->with(['lokasi', 'skpd'])
                ->get(['id_ruangan', 'nama_ruangan', 'id_lokasi', 'id_skpd']);
        }

        $kondisiList = self::KONDISI;

        // Status finalisasi penempatan menentukan apakah SPPBI boleh tampil.
        // Dicek SEKALI per request supaya query tidak berulang di tiap baris.
        $kolomFinalisasi = AsetPenempatan::kolomFinalisasiTersedia();

        // Tandai aset yang penempatannya sudah final (Sudah Dfinalisasi) dan
        // ruangannya valid. Hanya aset seperti ini yang boleh menampilkan SPPBI.
        //
        // `boleh_mutasi` dan `boleh_edit_finalisasi` memakai aturan yang SAMA
        // dengan backend (App\Services\AsetPenempatan) supaya tombol di tabel
        // tidak pernah berbeda dari penolakan di server:
        //   - mutasi  -> butuh pemegang aktif ATAU penempatan aktif,
        //   - edit/finalisasi -> hanya penempatan dengan is_finalized = 0.
        $asetList->getCollection()->each(function (Aset $aset) use ($kolomFinalisasi) {
            $penempatanAktif = $aset->penempatanAktif;

            $aset->setAttribute(
                'siap_sppbi',
                AsetPenempatan::penempatanFinalValid($penempatanAktif, $kolomFinalisasi)
            );

            $aset->setAttribute(
                'boleh_mutasi',
                AsetPenempatan::bolehMutasi($aset->pemegangSaatIni !== null, $penempatanAktif !== null)
            );

            // Mutasi butuh penempatan aktif yang SUDAH difinalisasi
            // (is_finalized = 1). Status 0 (Belum Difinalisasi) dan NULL
            // (Perlu Verifikasi) sama-sama menutup ikon Mutasi.
            $aset->setAttribute(
                'boleh_mutasi_final',
                AsetPenempatan::bolehMutasiFinalisasi($penempatanAktif, $kolomFinalisasi)
            );

            $aset->setAttribute(
                'boleh_edit_finalisasi',
                AsetPenempatan::bolehEditFinalisasi($penempatanAktif, $kolomFinalisasi)
            );
        });

        return view('aset-barang.admin-index', compact(
            'asetList',
            'pemegangOptions',
            'lokasiFilterOptions',
            'allPegawai',
            'ruanganOptions',
            'kondisiList',
            'isAdminAset',
            'canManage',
            'canPrintLabel'
        ));
    }

    /**
     * Detail per orang: aset barang yang sedang dipegang (`pemegang_aset` aktif).
     */
    public function show(Request $request, Pegawai $pegawai)
    {
        // Admin Bidang, Admin UPT, dan Pegawai hanya berwenang melihat aset pribadi milik sendiri.
        if (in_array($this->scope->namaRole(), [AsetScope::ROLE_ADMIN_BIDANG, AsetScope::ROLE_ADMIN_UPT, AsetScope::ROLE_PEGAWAI])) {
            abort_unless(
                (int) $pegawai->id_pegawai === (int) auth()->user()?->id_pegawai,
                403,
                $this->scope->namaRole() . ' hanya berwenang melihat aset pribadi milik sendiri.'
            );
        } elseif (! $this->scope->cakupanPenuh()) {
            // User UPT tidak boleh membuka pegawai di luar cakupan lokasinya.
            $idLokasi = $this->scope->idLokasi();

            $dalamCakupan = Ruangan::where('id_ruangan', $pegawai->id_ruangan)
                ->whereIn('id_lokasi', $idLokasi ?: [0])
                ->exists();

            abort_unless($dalamCakupan, 403, $this->scope->pesanAkses('melihat aset pegawai'));
        }

        $pegawai->load(['skpd', 'ruangan']);

        $asetList = PemegangAset::with(['aset.barang.kategori', 'aset.penempatanAktif.ruangan'])
            ->where('id_pegawai', $pegawai->id_pegawai)
            ->where('status', 'aktif')
            ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false)->whereIn('status_aset', self::STATUS_USER))
            ->orderByDesc('id_pemegang')
            ->get();

        $isAdminAset = $this->isAdminAset();
        $canManage = $this->canManageAset();
        $canPrintLabel = $this->canPrintLabel();

        // Kandidat "Pasang Aset": master barang aktif yang belum berpemegang.
        // Hanya dimuat untuk pengelola aset (Admin Aset / Admin UPT).
        $asetPasang = $canManage ? $this->asetPasangKandidat() : collect();

        // Aset kendaraan yang terkait pemegang ini. Sumber identitas pemegang
        // kendaraan adalah kolom string `kendaraan.pemegang` (relasi PemegangAset
        // kendaraan tidak selalu sinkron setelah mutasi). Hanya ditampilkan bila
        // nama cocok PERSIS & UNIK ke satu Pegawai, agar kendaraan pemegang lain
        // (nama kembar) tidak ikut tampil.
        $namaPemegang = mb_strtolower(trim((string) $pegawai->nama_pegawai));
        $namaUnik = $namaPemegang !== ''
            && Pegawai::whereRaw('LOWER(TRIM(nama_pegawai)) = ?', [$namaPemegang])->count() === 1;

        $kendaraanList = collect();
        if ($namaUnik) {
            $kendaraanList = Kendaraan::with(['aset.barang', 'platAktif', 'pajakAktif'])
                ->whereRaw('LOWER(TRIM(pemegang)) = ?', [$namaPemegang])
                ->whereHas('aset', function ($a) {
                    $a->whereIn('status_aset', self::STATUS_USER)
                        ->tap(fn ($q) => $this->scope->terapkan($q));
                })
                ->orderByDesc('id_kendaraan')
                ->get();
        }

        // Status finalisasi penempatan menentukan apakah SPPBI boleh tampil.
        $kolomFinalisasi = AsetPenempatan::kolomFinalisasiTersedia();

        // Tandai aset yang penempatannya sudah final dan ruangannya valid.
        $asetList->each(function (PemegangAset $item) use ($kolomFinalisasi) {
            $item->aset?->setAttribute(
                'siap_sppbi',
                AsetPenempatan::penempatanFinalValid($item->aset?->penempatanAktif, $kolomFinalisasi)
            );
        });

        $navigasiKembali = $this->resolveNavigasiKembali($request);
        $kembaliUrl = $navigasiKembali['url'];
        $kembaliLabel = $navigasiKembali['label'];

        return view('aset-barang.show', compact(
            'pegawai', 'asetList', 'kendaraanList', 'isAdminAset', 'canManage', 'canPrintLabel', 'asetPasang',
            'kembaliUrl', 'kembaliLabel'
        ));
    }

    /**
     * Tentukan URL dan label navigasi "Kembali" yang aman dan tervalidasi.
     *
     * @return array{url: string, label: string}
     */
    private function resolveNavigasiKembali(Request $request): array
    {
        $role = $this->scope->namaRole();
        $fallbackDashboard = [
            'url' => route('dashboard'),
            'label' => 'Kembali ke Dashboard',
        ];
        $fallbackAsetBarang = [
            'url' => route('aset-barang.index'),
            'label' => 'Kembali ke Aset Barang',
        ];

        // 1. Parameter eksplisit 'from'
        $from = trim((string) $request->query('from'));
        if ($from === 'dashboard') {
            return $fallbackDashboard;
        }
        if ($from === 'aset-barang') {
            return $fallbackAsetBarang;
        }

        // 2. Parameter 'return_url' jika ada dan valid
        $returnUrl = (string) $request->query('return_url');
        if ($returnUrl !== '') {
            $validated = $this->validasiReturnUrl($returnUrl);
            if ($validated) {
                return $validated;
            }
        }

        // 3. Header referer / previous URL jika valid dan aman
        $previous = (string) url()->previous();
        if ($previous !== '' && $previous !== $request->fullUrl()) {
            $validatedPrev = $this->validasiReturnUrl($previous);
            if ($validatedPrev) {
                return $validatedPrev;
            }
        }

        // 4. Fallback aman sesuai role pengguna
        if (in_array($role, [AsetScope::ROLE_ADMIN_UPT, AsetScope::ROLE_ADMIN_BIDANG, AsetScope::ROLE_PEGAWAI])) {
            return $fallbackDashboard;
        }

        return $fallbackAsetBarang;
    }

    /**
     * Validasi return URL agar hanya mengarah ke rute internal aplikasi yang aman.
     *
     * @return array{url: string, label: string}|null
     */
    private function validasiReturnUrl(string $url): ?array
    {
        // Tolak skema tidak aman atau open redirect berbasis scheme
        if (preg_match('#^(javascript|data|file):#i', $url) || str_starts_with($url, '//')) {
            return null;
        }

        $parsed = parse_url($url);
        if ($parsed === false) {
            return null;
        }

        // Jika memiliki host, pastikan sama persis dengan host aplikasi
        if (isset($parsed['host'])) {
            $appHost = parse_url(config('app.url'), PHP_URL_HOST) ?: request()->getHost();
            if (strcasecmp($parsed['host'], $appHost) !== 0) {
                return null;
            }
        }

        $path = $parsed['path'] ?? '';
        $path = '/' . ltrim($path, '/');

        // Whitelist destinasi internal yang diizinkan
        if ($path === '/dashboard' || str_starts_with($path, '/dashboard')) {
            return [
                'url' => route('dashboard'),
                'label' => 'Kembali ke Dashboard',
            ];
        }

        if ($path === '/aset-barang' || str_starts_with($path, '/aset-barang')) {
            // Hindari looping ke halaman show yang sama
            if (! preg_match('#^/aset-barang/\d+#', $path)) {
                return [
                    'url' => route('aset-barang.index'),
                    'label' => 'Kembali ke Aset Barang',
                ];
            }
        }

        return null;
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
        $this->authorizePengelolaAset();

        abort_if((bool) $aset->is_kendaraan, 422, 'Aset kendaraan tidak dapat dipasang sebagai aset barang.');
        abort_if($aset->status_aset !== 'aktif', 422, 'Hanya aset berstatus aktif yang dapat dipasang.');

        $this->authorizeAset($aset, 'memasang aset ke pegawai');

        if ($this->isAdminUpt()) {
            $idLokasiCakupan = $this->scope->idLokasi() ?: [];
            $pegawaiLokasi = $pegawai->ruangan?->id_lokasi;
            $pegawaiSkpd = $pegawai->id_skpd;
            $dalamCakupan = ($pegawaiLokasi && in_array($pegawaiLokasi, $idLokasiCakupan, true))
                || ($pegawaiSkpd && (int) $pegawaiSkpd === (int) $this->scope->skpdId());
            abort_unless($dalamCakupan, 403, 'Pegawai ini berada di luar cakupan UPT Anda.');
        }

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
     * Penempatan awal aset yang belum memiliki pemegang/penempatan aktif.
     *
     * Dua sumber penempatan sesuai tombol yang diklik:
     *  - mode `pegawai` : pemegang dipilih, ruangan mengikuti ruang kerja pegawai.
     *  - mode `ruangan` : ruangan dipilih langsung, tanpa membuat pemegang.
     *
     * Tidak membuat MutasiAset, DetailMutasiAset, BAST, atau SPPBI. Lokasi dan
     * SKPD selalu ditentukan server via `App\Services\AsetPenempatan`.
     */
    public function penempatanAwal(Request $request, Aset $aset)
    {
        $this->authorizePengelolaAset();
        $this->authorizeAset($aset, 'melakukan penempatan awal');

        abort_if(
            (bool) $aset->is_kendaraan,
            422,
            'Aset kendaraan tidak dapat ditempatkan sebagai aset barang.'
        );

        $mode = $request->input('mode') === 'ruangan' ? 'ruangan' : 'pegawai';

        if ($mode === 'ruangan') {
            $validated = $request->validate([
                'id_ruangan' => ['required', 'exists:ruangan,id_ruangan'],
            ], [
                'id_ruangan.required' => 'Ruangan wajib dipilih.',
                'id_ruangan.exists' => 'Ruangan yang dipilih tidak ditemukan.',
            ]);

            $pegawai = null;
            $ruanganId = (int) $validated['id_ruangan'];
        } else {
            $validated = $request->validate([
                'id_pegawai' => ['required', 'exists:pegawai,id_pegawai'],
            ], [
                'id_pegawai.required' => 'Pemegang wajib dipilih.',
                'id_pegawai.exists' => 'Pegawai yang dipilih tidak ditemukan.',
            ]);

            $pegawai = Pegawai::findOrFail($validated['id_pegawai']);

            if ($this->isAdminUpt()) {
                $idLokasiCakupan = $this->scope->idLokasi() ?: [];
                $pegawaiLokasi = $pegawai->ruangan?->id_lokasi;
                $pegawaiSkpd = $pegawai->id_skpd;
                $dalamCakupan = ($pegawaiLokasi && in_array($pegawaiLokasi, $idLokasiCakupan, true))
                    || ($pegawaiSkpd && (int) $pegawaiSkpd === (int) $this->scope->skpdId());
                abort_unless($dalamCakupan, 403, 'Pegawai ini berada di luar cakupan UPT Anda.');
            }

            // Ruangan bukan input bebas: sumbernya ruang kerja pegawai.
            $ruanganId = AsetPenempatan::ruanganKerjaPemegang($pegawai->id_pegawai);
        }

        $result = DB::transaction(function () use ($aset, $pegawai, $ruanganId) {
            $asetLock = Aset::where('id_aset', $aset->id_aset)->lockForUpdate()->first();

            if (! $asetLock || $asetLock->status_aset !== 'aktif') {
                throw ValidationException::withMessages([
                    'id_aset' => 'Hanya aset berstatus aktif yang dapat ditempatkan.',
                ]);
            }

            // Aset yang sudah berpemegang tidak boleh digeser ruangannya lewat
            // jalur Penempatan Awal; gunakan Mutasi yang sesuai.
            if ($asetLock->pemegangSaatIni()->exists()) {
                throw ValidationException::withMessages([
                    'id_aset' => 'Aset sudah memiliki pemegang aktif. Gunakan Mutasi untuk mengganti pemegang atau memindahkan ruangan.',
                ]);
            }

            if ($asetLock->penempatanAktif()->exists()) {
                throw ValidationException::withMessages([
                    'id_aset' => 'Aset sudah memiliki penempatan aktif.',
                ]);
            }

            if ($pegawai) {
                PemegangAset::create([
                    'id_aset' => $asetLock->id_aset,
                    'id_pegawai' => $pegawai->id_pegawai,
                    'tanggal_mulai' => now()->toDateString(),
                    'status' => 'aktif',
                ]);
            }

            if ($ruanganId) {
                $this->authorizeRuangan($ruanganId, 'melakukan penempatan awal');
                $this->placeAtRoom($asetLock, $ruanganId);

                // Penempatan baru via tombol + berstatus Belum Difinalisasi.
                if (Schema::hasColumn('penempatan_aset', 'is_finalized')) {
                    PenempatanAset::where('id_aset', $asetLock->id_aset)
                        ->where('status', 'aktif')
                        ->update(['is_finalized' => 0]);
                }

            }

            // SKPD & Lokasi mengikuti mekanisme AsetPenempatan yang sudah ada.
            // Saat pegawai belum punya ruangan, SKPD tetap disinkronkan.
            AsetPenempatan::sinkronAset($asetLock, $pegawai?->id_pegawai, $ruanganId);

            return $asetLock->fresh();
        });

        return response()->json([
            'success' => true,
            'message' => 'Penempatan awal berhasil disimpan.',
            'warning' => $ruanganId
                ? null
                : 'Pegawai belum punya ruangan. Aset akan tampil tanpa ruangan sampai ruangan pegawai diisi.',
            'id_ruangan' => $ruanganId,
            'id_lokasi' => $result->id_lokasi,
            'id_skpd' => $result->id_skpd,
        ]);
    }

    /**
     * Finalisasi penempatan aktif (Belum Difinalisasi -> Sudah Difinalisasi).
     *
     * Hanya menyentuh record penempatan aktif. Tidak membuat MutasiAset,
     * DetailMutasiAset, BAST, atau SPPBI. Penempatan lama bernilai NULL
     * (Perlu Verifikasi) sengaja tidak diubah otomatis oleh endpoint ini.
     */
    public function finalisasiPenempatan(Request $request, Aset $aset)
    {
        $this->authorizePengelolaAset();
        $this->authorizeAset($aset, 'memfinalisasi penempatan');

        abort_unless(
            Schema::hasColumn('penempatan_aset', 'is_finalized'),
            422,
            'Kolom finalisasi penempatan belum tersedia. Jalankan migration finalisasi terlebih dahulu.'
        );

        $result = $this->finalisasiPenempatanSatu($aset);

        return response()->json([
            'success' => true,
            'message' => 'Penempatan berhasil difinalisasi.',
            'is_finalized' => 1,
            'finalized_at' => $result->finalized_at?->toDateTimeString(),
            'finalized_by' => $result->finalized_by,
        ]);
    }

    /**
     * Verifikasi penempatan aktif berstatus NULL (Perlu Verifikasi) lalu
     * finalisasi secara EKSPLISIT untuk satu aset.
     *
     * Jalur ini hanya untuk data lama: penempatan yang dibuat sebelum
     * migration finalisasi tidak punya nilai `is_finalized`, sehingga
     * endpoint finalisasi biasa menolaknya ("tidak difinalisasi otomatis").
     * Tanpa jalur ini, aset lama tersebut tidak akan pernah bisa
     * difinalisasi — dan karena mutasi mewajibkan penempatan yang sudah
     * difinalisasi, aset lamanya ikut terkunci permanen.
     *
     * Sifatnya per aset dan dilakukan admin secara sadar: BUKAN backfill
     * massal dan tidak pernah dijalankan otomatis oleh sistem.
     */
    public function verifikasiFinalisasiPenempatan(Request $request, Aset $aset)
    {
        $this->authorizePengelolaAset();
        $this->authorizeAset($aset, 'memverifikasi penempatan aset');

        abort_unless(
            Schema::hasColumn('penempatan_aset', 'is_finalized'),
            422,
            'Kolom finalisasi penempatan belum tersedia. Jalankan migration finalisasi terlebih dahulu.'
        );

        $result = DB::transaction(function () use ($aset) {
            $asetLock = Aset::where('id_aset', $aset->id_aset)->lockForUpdate()->first();

            $penempatan = PenempatanAset::where('id_aset', $asetLock->id_aset)
                ->where('status', 'aktif')
                ->lockForUpdate()
                ->first();

            if (! $penempatan) {
                throw ValidationException::withMessages([
                    'penempatan' => 'Penempatan aktif tidak ditemukan untuk aset ini.',
                ]);
            }

            if (! is_null($penempatan->is_finalized)) {
                throw ValidationException::withMessages([
                    'penempatan' => (int) $penempatan->is_finalized === 1
                        ? 'Penempatan ini sudah difinalisasi.'
                        : 'Penempatan ini berstatus Belum Difinalisasi. Gunakan tombol Finalisasi biasa.',
                ]);
            }

            $penempatan->update([
                'is_finalized' => 1,
                'finalized_at' => now(),
                'finalized_by' => auth()->id(),
            ]);

            return $penempatan->fresh();
        });

        return response()->json([
            'success' => true,
            'message' => 'Penempatan aset lama berhasil diverifikasi dan difinalisasi.',
            'is_finalized' => 1,
            'finalized_at' => $result->finalized_at?->toDateTimeString(),
            'finalized_by' => $result->finalized_by,
        ]);
    }

    /**
     * Finalisasi penempatan aktif untuk BEBERAPA aset sekaligus.
     *
     * STRATEGI TRANSAKSI: satu transaksi PER ASET. Aset yang gagal tidak
     * menggagalkan aset lain — tiap aset punya hasil sendiri (berhasil/gagal)
     * yang dilaporkan terpisah. Alasan: finalisasi bersifat independen, dan
     * satu aset bermasalah tidak boleh membatalkan finalisasi aset lain yang
     * sudah sah (transaksi massal tunggal bisa rollback semuanya tanpa
     * laporan per aset).
     *
     * VALIDASI DIULANG untuk setiap aset: penempatan aktif harus ada,
     * `is_finalized` harus tepat 0, dan aset harus berada dalam AsetScope
     * user. Checkbox di frontend BUKAN otorisasi.
     *
     * Tidak membuat MutasiAset, DetailMutasiAset, BAST, atau SPPBI.
     */
    public function finalisasiPenempatanMassal(Request $request)
    {
        $this->authorizePengelolaAset();

        abort_unless(
            Schema::hasColumn('penempatan_aset', 'is_finalized'),
            422,
            'Kolom finalisasi penempatan belum tersedia. Jalankan migration finalisasi terlebih dahulu.'
        );

        $validated = $request->validate([
            'id_aset' => ['required', 'array', 'min:1', 'max:100'],
            'id_aset.*' => ['required', 'integer', 'distinct', 'exists:aset,id_aset'],
        ], [
            'id_aset.required' => 'Pilih minimal satu aset untuk difinalisasi.',
            'id_aset.array' => 'Daftar aset yang dipilih tidak valid.',
            'id_aset.min' => 'Pilih minimal satu aset untuk difinalisasi.',
            'id_aset.max' => 'Maksimal 100 aset dapat difinalisasi sekaligus.',
            'id_aset.*.distinct' => 'Aset yang sama tidak boleh dipilih lebih dari sekali.',
            'id_aset.*.exists' => 'Aset yang dipilih tidak ditemukan.',
        ]);

        $berhasil = [];
        $gagal = [];

        foreach ($validated['id_aset'] as $idAset) {
            $aset = Aset::find($idAset);

            if (! $aset) {
                $gagal[] = [
                    'id_aset' => $idAset,
                    'nama' => 'Aset #'.$idAset,
                    'alasan' => 'Aset tidak ditemukan.',
                ];
                continue;
            }

            $nama = $aset->barang?->nama_barang ?? ($aset->nomor_kartu_barang ?? 'Aset #'.$idAset);

            // Aset di luar AsetScope user DITOLAK (dilaporkan), bukan
            // dilewati diam-diam — checkbox frontend bukan otorisasi.
            if (! $this->scope->bolehAksesAset($aset)) {
                $gagal[] = [
                    'id_aset' => $idAset,
                    'nama' => $nama,
                    'alasan' => $this->scope->pesanAkses('mfinalisasi penempatan'),
                ];
                continue;
            }

            try {
                $penempatan = $this->finalisasiPenempatanSatu($aset);

                $berhasil[] = [
                    'id_aset' => $idAset,
                    'nama' => $nama,
                    'finalized_at' => $penempatan->finalized_at?->toDateTimeString(),
                ];
            } catch (ValidationException $e) {
                $gagal[] = [
                    'id_aset' => $idAset,
                    'nama' => $nama,
                    'alasan' => collect($e->errors())->flatten()->first(),
                ];
            } catch (Throwable $e) {
                report($e);

                $gagal[] = [
                    'id_aset' => $idAset,
                    'nama' => $nama,
                    'alasan' => 'Terjadi kesalahan pada server saat memfinalisasi aset ini.',
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => count($berhasil) > 0
                ? count($berhasil).' penempatan berhasil difinalisasi'
                    .(count($gagal) > 0 ? ', '.count($gagal).' aset gagal.' : '.')
                : 'Tidak ada aset yang berhasil difinalisasi.',
            'berhasil' => count($berhasil),
            'gagal' => count($gagal),
            'detail_berhasil' => $berhasil,
            'detail_gagal' => $gagal,
        ]);
    }

    /**
     * Finalisasi penempatan aktif untuk SATU aset.
     *
     * Dipakai oleh endpoint individual maupun finalisasi massal, sehingga
     * aturan validasi & pencatatan `finalized_at`/`finalized_by` selalu sama.
     *
     * Melempar ValidationException bila penempatan tidak memenuhi syarat
     * (tidak ada penempatan aktif, status NULL, atau sudah difinalisasi).
     */
    private function finalisasiPenempatanSatu(Aset $aset): PenempatanAset
    {
        return DB::transaction(function () use ($aset) {
            $penempatan = PenempatanAset::where('id_aset', $aset->id_aset)
                ->where('status', 'aktif')
                ->lockForUpdate()
                ->first();

            if (! $penempatan) {
                throw ValidationException::withMessages([
                    'penempatan' => 'Penempatan aktif tidak ditemukan untuk aset ini.',
                ]);
            }

            if (is_null($penempatan->is_finalized)) {
                throw ValidationException::withMessages([
                    'penempatan' => 'Penempatan lama berstatus Perlu Verifikasi dan tidak difinalisasi otomatis.',
                ]);
            }

            if ((int) $penempatan->is_finalized === 1) {
                throw ValidationException::withMessages([
                    'penempatan' => 'Penempatan ini sudah difinalisasi.',
                ]);
            }

            $penempatan->update([
                'is_finalized' => 1,
                'finalized_at' => now(),
                'finalized_by' => auth()->id(),
            ]);

            return $penempatan->fresh();
        });
    }

    /**
     * Edit penempatan aktif (bukan mutasi).
     *
     * Dipakai untuk MEMPERBAIKI data penempatan yang keliru:
     *   - mode `pegawai` : pemilik-aset (pegawai) pada penempatan aktif,
     *   - mode `ruangan` : ruangan penempatan untuk aset tanpa pemegang.
     *
     * Berbeda dari Mutasi, endpoint ini TIDAK membuat `mutasi_aset`,
     * `detail_mutasi_aset`, BAST, maupun SPPBI. Yang diubah hanya data
     * penempatan itu sendiri: pemegang aktif (mode pegawai) atau ruangan
     * penempatan (mode ruangan).
     *
     * SKPD & Lokasi fisik TIDAK pernah menjadi input: keduanya dihitung
     * server lewat `AsetPenempatan::sinkronAset()` dari master yang dipakai.
     *
     * Penempatan yang sudah difinalisasi (is_finalized = 1) TIDAK boleh
     * diedit. Penempatan lama (is_finalized = NULL / Perlu Verifikasi)
     * juga tidak boleh diedit — nilainya tidak pernah diubah otomatis.
     */
    public function editPenempatan(Request $request, Aset $aset)
    {
        $this->authorizePengelolaAset();
        $this->authorizeAset($aset, 'mengedit penempatan aset');

        abort_if(
            (bool) $aset->is_kendaraan,
            422,
            'Aset kendaraan tidak dapat diedit penempatannya.'
        );

        $validated = $request->validate([
            'mode' => ['required', Rule::in(['pegawai', 'ruangan'])],
            'id_pegawai' => ['nullable', 'exists:pegawai,id_pegawai'],
            'id_ruangan' => ['nullable', 'exists:ruangan,id_ruangan'],
        ], [
            'mode.required' => 'Pilih jenis edit penempatan.',
            'mode.in' => 'Jenis edit penempatan tidak dikenal.',
            'id_pegawai.exists' => 'Pemegang yang dipilih tidak ditemukan.',
            'id_ruangan.exists' => 'Ruangan yang dipilih tidak ditemukan.',
        ]);

        $mode = $validated['mode'];

        $result = DB::transaction(function () use ($aset, $mode, $validated) {
            // Kunci baris aset + penempatan aktif supaya dua admin tidak
            // mengedit penempatan yang sama bersamaan.
            $asetLock = Aset::where('id_aset', $aset->id_aset)->lockForUpdate()->first();

            $penempatan = PenempatanAset::where('id_aset', $asetLock->id_aset)
                ->where('status', 'aktif')
                ->lockForUpdate()
                ->first();

            if (! $penempatan) {
                throw ValidationException::withMessages([
                    'penempatan' => 'Penempatan aktif tidak ditemukan untuk aset ini.',
                ]);
            }

            if (! AsetPenempatan::kolomFinalisasiTersedia()) {
                abort(422, 'Kolom finalisasi penempatan belum tersedia. Jalankan migration finalisasi terlebih dahulu.');
            }

            if (is_null($penempatan->is_finalized)) {
                throw ValidationException::withMessages([
                    'penempatan' => 'Penempatan lama berstatus Perlu Verifikasi dan tidak dapat diedit dari daftar ini.',
                ]);
            }

            if ((int) $penempatan->is_finalized === 1) {
                throw ValidationException::withMessages([
                    'penempatan' => 'Penempatan sudah difinalisasi dan tidak dapat diedit.',
                ]);
            }

            $pemegangAktif = PemegangAset::where('id_aset', $asetLock->id_aset)
                ->where('status', 'aktif')
                ->lockForUpdate()
                ->first();

            if ($mode === 'pegawai') {
                return $this->editPemegangPenempatan($asetLock, $penempatan, $pemegangAktif, $validated);
            }

            return $this->editRuanganPenempatan($asetLock, $penempatan, $pemegangAktif, $validated);
        });

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'warning' => $result['warning'] ?? null,
        ]);
    }

    /**
     * Edit Pemegang: mengganti pemegang aktif pada penempatan aktif.
     *
     * Ruangan mengikuti ruang kerja pemegang baru (aturan yang sama dengan
     * Penempatan Awal). Bila pemegang baru belum punya ruang kerja, ruangan
     * TIDAK diubah dan hal itu dikinformasikan lewat warning.
     */
    private function editPemegangPenempatan(Aset $aset, PenempatanAset $penempatan, ?PemegangAset $pemegangAktif, array $validated): array
    {
        if (! $pemegangAktif) {
            throw ValidationException::withMessages([
                'mode' => 'Aset ini tidak memiliki pemegang aktif. Gunakan Edit Ruangan.',
            ]);
        }

        $idPegawai = $validated['id_pegawai'] ?? null;

        if (! $idPegawai) {
            throw ValidationException::withMessages([
                'id_pegawai' => 'Pilih pemegang dari master Pegawai.',
            ]);
        }

        $pegawaiBaru = Pegawai::findOrFail($idPegawai);

        if ($this->isAdminUpt()) {
            $idLokasiCakupan = $this->scope->idLokasi() ?: [];
            $pegawaiLokasi = $pegawaiBaru->ruangan?->id_lokasi;
            $pegawaiSkpd = $pegawaiBaru->id_skpd;
            $dalamCakupan = ($pegawaiLokasi && in_array($pegawaiLokasi, $idLokasiCakupan, true))
                || ($pegawaiSkpd && (int) $pegawaiSkpd === (int) $this->scope->skpdId());
            abort_unless($dalamCakupan, 403, 'Pegawai ini berada di luar cakupan UPT Anda.');
        }

        // Ruangan kerja pemegang baru harus sah (Aktif) dan dalam cakupan.
        $ruanganBaru = AsetPenempatan::ruanganKerjaPemegang($pegawaiBaru->id_pegawai);

        if ($ruanganBaru) {
            $this->authorizeRuangan($ruanganBaru, 'mengedit penempatan aset');
        }

        if ((int) $pemegangAktif->id_pegawai === (int) $pegawaiBaru->id_pegawai) {
            return [
                'message' => 'Pemegang aset sudah '.$pegawaiBaru->nama_pegawai.'. Tidak ada perubahan.',
                'warning' => null,
            ];
        }

        // Perbaikan data pemegang: record AKTIF yang sama diperbarui, bukan
        // membuat record baru (dan bukan mutasi).
        $pemegangAktif->update([
            'id_pegawai' => $pegawaiBaru->id_pegawai,
            'tanggal_mulai' => now()->toDateString(),
            'tanggal_selesai' => null,
            'status' => 'aktif',
        ]);

        $warning = null;

        // Ruangan penempatan mengikuti ruang kerja pemegang baru. Bila pemegang
        // baru belum punya ruang kerja, penempatan tetap di ruangan sekarang.
        $ruanganEfektif = $penempatan->id_ruangan;

        if ($ruanganBaru && (int) $penempatan->id_ruangan !== (int) $ruanganBaru) {
            $penempatan->update(['id_ruangan' => $ruanganBaru]);
            $ruanganEfektif = $ruanganBaru;
        }

        if (! $ruanganBaru) {
            $warning = 'Pemegang baru belum memiliki ruang kerja. Ruangan aset tidak diubah.';
        }

        // SKPD & Lokasi mengikuti master (pemegang baru + effective room).
        AsetPenempatan::sinkronAset($aset, $pegawaiBaru->id_pegawai, $ruanganEfektif);

        return [
            'message' => 'Pemegang aset diperbaiki menjadi '.$pegawaiBaru->nama_pegawai.'.',
            'warning' => $warning,
        ];
    }

    /**
     * Edit Ruangan: memindahkan penempatan aktif ke ruangan lain.
     *
     * Hanya untuk aset TANPA pemegang aktif — barang berpemegang mengikuti
     * ruang kerja pemegangnya, jadi ruangnya tidak berdiri sendiri.
     */
    private function editRuanganPenempatan(Aset $aset, PenempatanAset $penempatan, ?PemegangAset $pemegangAktif, array $validated): array
    {
        if ($pemegangAktif) {
            throw ValidationException::withMessages([
                'mode' => 'Aset memiliki pemegang aktif; ruangan mengikuti pemegang. Gunakan Edit Pemegang.',
            ]);
        }

        $idRuangan = $validated['id_ruangan'] ?? null;

        if (! $idRuangan) {
            throw ValidationException::withMessages([
                'id_ruangan' => 'Pilih ruangan dari master Ruangan.',
            ]);
        }

        $this->authorizeRuangan((int) $idRuangan, 'mengedit penempatan aset');

        if ((int) $penempatan->id_ruangan === (int) $idRuangan) {
            return [
                'message' => 'Aset sudah berada di ruangan tersebut. Tidak ada perubahan.',
                'warning' => null,
            ];
        }

        // Perbaikan data penempatan: baris penempatan AKTIF yang sama diubah,
        // status finalisasi (0) tetap utuh.
        $penempatan->update(['id_ruangan' => (int) $idRuangan]);

        // SKPD & Lokasi mengikuti ruangan baru (aset tanpa pemegang).
        AsetPenempatan::sinkronAset($aset, null, (int) $idRuangan);

        return [
            'message' => 'Ruangan aset diperbaiki menjadi '.Ruangan::find($idRuangan)?->nama_ruangan.'.',
            'warning' => null,
        ];
    }

    /**
     * Tambah aset dari tabel flat (tanpa pegawai di route).
     *
     * SATU-SATUNYA tempat pembuatan master aset (Aturan 2). Detail Pegawai
     * dan Detail Ruangan hanya memasang master yang sudah ada.
     */
    public function storeFlat(Request $request)
    {
        $this->authorizePengelolaAset();

        // Penempatan awal hanya melalui tombol +; tambah aset hanya membuat data induk.
        $warning = $this->createAsetFor($request, null);

        return response()->json([
            'success' => true,
            'message' => 'Aset berhasil ditambahkan.',
            'warning' => $warning,
        ]);
    }

    private function createAsetFor(Request $request, ?int $pegawaiId): ?string
    {
        $data = TanggalAset::samakanUntukSimpan($this->validateData($request));
        $data['id_barang'] = $this->resolveBarang($request->input('nama_barang'));

        if (isset($data['tanggal_pengadaan']) && isset($data['tanggal_habis_pakai'])) {
            if (strtotime($data['tanggal_habis_pakai']) < strtotime($data['tanggal_pengadaan'])) {
                throw ValidationException::withMessages([
                    'tanggal_habis_pakai' => 'Tanggal habis pakai tidak boleh lebih awal daripada tanggal pengadaan.',
                ]);
            }
        }

        // Admin UPT: Aset baru langsung ditautkan ke SKPD dan lokasi UPT-nya
        // agar tidak fail-closed pada scope filter lokasi.
        if ($this->isAdminUpt()) {
            $data['id_skpd'] = $this->scope->skpdId();
            $lokasiUpt = $this->scope->idLokasi();
            if ($lokasiUpt && count($lokasiUpt) > 0) {
                $data['id_lokasi'] = $lokasiUpt[0];
            }
        } elseif ($this->scope->isAdminAset()) {
            // Lokasi yang dipilih saat input aset oleh Admin Aset tidak otomatis ditimpa menjadi lokasi Dinas.
            $chosenLokasi = $request->input('id_lokasi');
            $chosenSkpd = $request->input('id_skpd');
            if ($chosenLokasi) {
                $data['id_lokasi'] = (int) $chosenLokasi;
                if (! $chosenSkpd) {
                    $lokasiObj = Lokasi::find($chosenLokasi);
                    $data['id_skpd'] = $lokasiObj?->id_skpd;
                } else {
                    $data['id_skpd'] = (int) $chosenSkpd;
                }
            } else {
                $data['id_lokasi'] = $data['id_lokasi'] ?? (Lokasi::dinas()?->id_lokasi ?? 1);
                if ($chosenSkpd) {
                    $data['id_skpd'] = (int) $chosenSkpd;
                }
            }
        }

        // Penambahan aset hanya membuat data induk aset (tanpa penempatan/pemegang)
        // Ruangan/pegawai tidak diproses untuk penempatan awal pada tahap tambah.
        $aset = Aset::create($data);

        // Jika request penambahan menyertakan id_ruangan spesifik, lakukan penempatan ruangan
        if ($request->filled('id_ruangan')) {
            $ruanganId = (int) $request->input('id_ruangan');
            $this->placeAtRoom($aset, $ruanganId, false);
            AsetPenempatan::sinkronAset($aset, null, $ruanganId);
        }

        // Pemicu otomatis: aset baru dengan kondisi Rusak Berat langsung
        // masuk antrean usulan penghapusan (keluar dari daftar aktif).
        $this->usulan()->usulkanKarenaRusakBerat(
            $aset,
            'Otomatis: aset dibuat dengan kondisi Rusak Berat.'
        );

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
     *
     * `$langsungDifinalisasi = true` HANYA untuk alur mutasi (Pindah Ruangan
     * dan Ganti Pemegang): penempatan baru langsung berstatus "Sudah
     * Difinalisasi". Penempatan awal & Pasang Aset memakai default (0).
     */
    private function placeAtRoom(Aset $aset, ?int $ruanganId, bool $langsungDifinalisasi = false): ?int
    {
        return $aset->placeAtRoom($ruanganId, $langsungDifinalisasi);
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
        abort_unless(
            $this->canManageAset(),
            403,
            'Hanya pengelola aset yang dapat melihat data modal aset.'
        );
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

        $isAdminAset = $this->isAdminAset();
        $canViewQr = $this->canPrintLabel();

        return view('aset-barang.detail', compact(
            'aset',
            'riwayatMutasi',
            'isAdminAset',
            'canViewQr'
        ));
    }

    /**
     * Label QR untuk aset: menampilkan QR code nomor kartu barang sebagai PNG.
     */
    public function qrLabel(Aset $aset)
    {
        abort_unless(
            $this->canPrintLabel(),
            403,
            'Hanya Admin Aset dan Admin UPT yang berwenang melihat QR code aset.'
        );

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
        abort_unless(
            $this->canPrintLabel(),
            403,
            'Hanya Admin Aset dan Admin UPT yang berwenang mengunduh label aset.'
        );

        $pegawai = Pegawai::with([
            'pemegangAset' => function ($q) {
                $q->where('status', 'aktif')
                    ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false))
                    ->with(['aset.barang', 'aset.penempatanAktif.ruangan']);
            },
            'ruangan',
        ])->findOrFail($id_pegawai);

        if ($this->isAdminUpt()) {
            $idLokasiCakupan = $this->scope->idLokasi() ?: [];
            $pegawaiLokasi = $pegawai->ruangan?->id_lokasi;
            $pegawaiSkpd = $pegawai->id_skpd;

            $dalamCakupan = ($pegawaiLokasi && in_array($pegawaiLokasi, $idLokasiCakupan, true))
                || ($pegawaiSkpd && (int) $pegawaiSkpd === (int) $this->scope->skpdId());

            abort_unless(
                $dalamCakupan,
                403,
                'Pegawai ini berada di luar cakupan UPT Anda.'
            );
        }

        $asetList = $pegawai->pemegangAset
            ->map(fn ($pemegang) => $pemegang->aset)
            ->filter()
            ->values();

        if ($asetList->isEmpty()) {
            return back()->with('error', 'Pegawai ini belum memegang aset aktif.');
        }

        $template = TemplateDokumen::where('kode_template', 'label')->first();
        if (! $template || ! $template->fileExists()) {
            return back()->with('error', 'File template label belum diunggah.');
        }

        try {
            $spreadsheet = IOFactory::load($template->realPath());

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
                    $xlsxPath = $template->realPath();
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
                $tahun = $aset->tanggal_pengadaan ? Carbon::parse($aset->tanggal_pengadaan)->format('Y') : date('Y');
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
        abort_unless(
            $this->canPrintLabel(),
            403,
            'Hanya Admin Aset dan Admin UPT yang berwenang mencetak label aset.'
        );

        $aset = Aset::with(['barang', 'penempatanAktif.ruangan'])->findOrFail($id);
        $this->authorizeAset($aset, 'mencetak label aset');

        $template = TemplateDokumen::where('kode_template', 'label')->first();
        if (! $template || ! $template->fileExists()) {
            return back()->with('error', 'File template label belum diunggah.');
        }

        try {
            $spreadsheet = IOFactory::load($template->realPath());
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
            $tahun = $aset->tanggal_pengadaan ? Carbon::parse($aset->tanggal_pengadaan)->format('Y') : date('Y');
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
        $this->authorizePengelolaAset();
        $this->authorizeAset($aset, 'memperbarui aset');

        // Status sistem milik workflow penghapusan tidak boleh ditimpa lewat
        // form edit (Aturan 15) — pengajuannya lewat aksi "Usulkan Hapus".
        abort_if(
            in_array($aset->status_aset, self::STATUS_SISTEM, true),
            422,
            'Aset berstatus '.$aset->status_aset.' tidak dapat diubah. Kelola lewat workflow penghapusan.'
        );

        // Edit Aset Master HANYA mengubah data master aset (Aturan 2 & 3).
        // Pemegang, Ruangan, SKPD, dan Lokasi Fisik bukan input di sini:
        // penempatan dikelola Penempatan Awal / Edit Penempatan / Mutasi,
        // sementara SKPD & Lokasi selalu turunan yang disinkronkan
        // AsetPenempatan. Request yang mencoba mengirimnya ditolak eksplisit
        // — bukan sekadar diabaikan oleh form.
        foreach (['id_pegawai', 'id_ruangan', 'id_skpd', 'id_lokasi'] as $kolomPenempatan) {
            if ($request->has($kolomPenempatan)) {
                throw ValidationException::withMessages([
                    $kolomPenempatan => 'Pemegang, ruangan, SKPD, dan lokasi tidak diubah dari form Edit Aset. '
                        .'Perubahan pemegang/ruangan melalui Edit Penempatan atau Mutasi resmi; '
                        .'SKPD & lokasi mengikuti master penempatan.',
                ]);
            }
        }

        $data = TanggalAset::samakanUntukSimpan(
            $this->validateData($request, $aset)
        );

        // Validasi yang SAMA dengan form Tambah: habis pakai tidak boleh
        // lebih awal daripada pengadaan.
        if (isset($data['tanggal_pengadaan']) && isset($data['tanggal_habis_pakai'])) {
            if (strtotime($data['tanggal_habis_pakai']) < strtotime($data['tanggal_pengadaan'])) {
                throw ValidationException::withMessages([
                    'tanggal_habis_pakai' => 'Tanggal habis pakai tidak boleh lebih awal daripada tanggal pengadaan.',
                ]);
            }
        }

        $data['id_barang'] = $this->resolveBarang($request->input('nama_barang'));

        $aset->update($data);

        $this->usulan()->usulkanKarenaRusakBerat(
            $aset,
            'Otomatis: kondisi aset diubah menjadi Rusak Berat.'
        );

        // Penempatan aktif, status finalisasi, dan turunan SKPD/Lokasi TIDAK
        // disentuh oleh Edit Aset Master — semuanya tetap pada nilai yang
        // sudah ada (dikelola lewat jalur penempatan/mutasi masing-masing).

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
     * Aksi mutasi terpisah: Ganti Pemegang (`tipe=pegawai`) atau
     * Pindah Ruangan (`tipe=ruangan`). Setiap perubahan tercatat di
     * tabel mutasi_aset + detail_mutasi_aset.
     */
    public function mutasi(Request $request, Aset $aset)
    {
        $this->authorizePengelolaAset();
        $this->authorizeAset($aset, 'memutasi aset');

        // Aset yang belum pernah ditempatkan (tanpa pemegang aktif DAN tanpa
        // penempatan aktif) TIDAK bisa dimutasi: belum ada "dari" untuk
        // dipindahkan. Penempatan pertama lewat tombol + (Penempatan Awal).
        // Penolakan ini ditegakkan di server — tombol di tabel hanya
        // penyaman tampilan, bukan penjaga. Sumber kebenaran ada di sini.
        if (! AsetPenempatan::bolehMutasi(
            $aset->pemegangSaatIni !== null,
            $aset->penempatanAktif !== null
        )) {
            throw ValidationException::withMessages([
                'tipe' => 'Tetapkan pemegang atau lokasi terlebih dahulu sebelum melakukan mutasi.',
            ]);
        }

        // Mutasi (Ganti Pemegang maupun Pindah Ruangan) hanya sah bila
        // penempatan aktif SUDAH difinalisasi (is_finalized = 1). Status 0
        // (Belum Difinalisasi) dan NULL (Perlu Verifikasi) ditolak.
        // Aset tanpa penempatan aktif (mis. pemegangnya belum punya ruang
        // kerja) tidak punya penempatan yang perlu difinalisasi, jadi tidak
        // digate. Aturan ini sama dengan ikon Mutasi di tabel Aset Barang.
        if (! AsetPenempatan::bolehMutasiFinalisasi($aset->penempatanAktif)) {
            throw ValidationException::withMessages([
                'tipe' => AsetPenempatan::pesanMutasiBelumDifinalisasi(),
            ]);
        }

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

            $mutasi = $this->mutasiService->mutasiGantiPemegang(
                $aset,
                (int) $pemegangBaru,
                $data['keterangan'] ?? null,
                auth()->id()
            );

            $downloadBastUrl = route('mutasi-aset.bast.download', $mutasi->id_mutasi);

            $warning = null;
            $penempatanAktif = PenempatanAset::where('id_aset', $aset->id_aset)->where('status', 'aktif')->exists();
            if (! $penempatanAktif) {
                $warning = 'Pegawai belum punya ruangan. Aset akan tampil tanpa ruangan sampai ruangan pegawai diisi.';
            }

            return response()->json([
                'success' => true,
                'message' => 'Pemegang aset berhasil diganti.',
                'warning' => $warning,
                'download_bast_url' => $downloadBastUrl,
                'id_mutasi' => $mutasi->id_mutasi,
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

        $hasil = $this->mutasiService->mutasiPindahRuangan(
            $aset,
            (int) $ruanganBaru,
            $data['keterangan'] ?? null,
            auth()->id()
        );

        if ($hasil['sama']) {
            return response()->json([
                'success' => true,
                'message' => 'Aset sudah berada di ruangan tersebut.',
            ]);
        }

        $pesan = 'Aset berhasil dipindah ruangan.';

        if ($hasil['lokasi_baru'] !== $hasil['lokasi_lama']) {
            $lokasi = Lokasi::find($hasil['lokasi_baru']);
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
        $this->authorizePengelolaAset();
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

        // Tombol Hapus = usulan penghapusan: status aset masuk antrean
        // (diusulkan_hapus) dan kondisi ditandai Rusak Berat, semua dalam
        // satu transaksi lewat service agar konsisten dengan pemicu kondisi.
        $this->usulan()->usulkan(
            $aset,
            alasan: 'Lainnya',
            keterangan: 'Diusulkan melalui tombol hapus pada daftar aset.',
            jadikanRusakBerat: true,
        );

        return response()->json([
            'success' => true,
            'message' => 'Aset diusulkan penghapusan dan dikeluarkan dari daftar aset aktif.',
        ]);
    }



    /**
     * Validasi data master Aset Barang untuk tambah dan edit
     */
    private function validateData(Request $request, ?Aset $aset = null): array
    {
        $asetId = $aset?->id_aset;

        $data = $request->validate([
            // Tambah aset hanya membuat data master aset; penempatan tidak melalui form ini
            // 'id_pegawai' dan 'id_ruangan' tidak diwajibkan dan tidak diproses untuk penempatan
            // `id_skpd` dan `id_lokasi` bukan input admin
            'nama_barang' => ['required', 'string', 'max:100'],
            'nomor_kartu_barang' => ['required', 'string', 'max:255', Rule::unique('aset', 'nomor_kartu_barang')->ignore($asetId, 'id_aset')],
            'merk' => ['nullable', 'string', 'max:100'],
            'tanggal_pengadaan' => ['required', 'date'],
            'tanggal_habis_pakai' => ['required', 'date'],
            'nilai_perolehan' => ['required', 'numeric', 'min:0'],
            'kondisi' => ['required', Rule::in(self::KONDISI)],
            'status_aset' => ['nullable', 'string', 'max:50'],
        ], [
            'tanggal_pengadaan.required' => 'Tanggal pengadaan wajib diisi.',
            'tanggal_pengadaan.date' => 'Format tanggal pengadaan tidak valid.',
            'tanggal_habis_pakai.required' => 'Tanggal habis pakai wajib diisi.',
            'tanggal_habis_pakai.date' => 'Format tanggal habis pakai tidak valid.',
            'nilai_perolehan.required' => 'Nilai perolehan wajib diisi.',
            'nilai_perolehan.numeric' => 'Nilai perolehan harus berupa angka yang valid.',
            'nilai_perolehan.min' => 'Nilai perolehan tidak boleh negatif.',
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

    /**
     * Unduh template Excel baku isian import master Aset Barang (1 worksheet + petunjuk pengisian di atas).
     */
    public function downloadTemplate()
    {
        $this->authorizeAdminAset();

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Import Aset Barang');

        // Pastikan kolom A (Nomor Kartu Barang) diformat sebagai Text (@) agar 0 di depan tidak hilang
        $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

        // Petunjuk Pengisian di bagian atas sheet (Baris 1 - 6)
        $sheet->setCellValue('A1', 'PETUNJUK PENGISIAN IMPORT ASET BARANG');
        $sheet->setCellValue('A2', '1. Kolom Wajib (*): Nomor Kartu Inventaris Barang, Tanggal Pengadaan, Nama Barang, dan Nilai Perolehan wajib diisi.');
        $sheet->setCellValue('A3', '2. Format Tanggal: Gunakan format YYYY-MM-DD atau DD/MM/YYYY (contoh: 2025-12-23).');
        $sheet->setCellValue('A4', '3. Nilai Perolehan: Diisi angka rupiah murni TANPA simbol mata uang (Rp), TANPA titik pemisah ribuan, dan TANPA koma desimal (contoh: 1462000).');
        $sheet->setCellValue('A5', '4. Kondisi Barang: Diisi dengan status kondisi fisik baku seperti BAIK, RUSAK RINGAN, atau RUSAK BERAT.');
        $sheet->setCellValue('A6', '5. Baris Contoh: Baris yang diawali dengan kata "CONTOH -" akan otomatis dilewati oleh sistem.');

        // Styling Petunjuk
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('0F5132');
        $sheet->getStyle('A2:A6')->getFont()->setSize(9)->setItalic(true)->getColor()->setRGB('334155');

        // Baris 7 dibiarkan kosong sebagai jeda pemisah

        // Baris Header Tabel pada Baris 8
        $sheet->fromArray(self::KOLOM_TEMPLATE_IMPORT, null, 'A8');

        // Baris Contoh pada Baris 9 (dilewati otomatis saat import)
        $sheet->fromArray([
            'CONTOH - KIB.01.01.01.001',
            'CONTOH - Laptop Lenovo ThinkPad',
            'Lenovo',
            '01/01/2026',
            '31/12/2030',
            1462000,
            'Baik',
            'aktif',
        ], null, 'A9');

        $sheet->getStyle('A8:H8')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '0F5132']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(8)->setRowHeight(28);
        $sheet->getRowDimension(9)->setRowHeight(20);

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return self::streamXlsx($spreadsheet, 'Template-Import-Aset-Barang.xlsx');
    }

    /**
     * Impor data master Aset Barang dari file Excel (Mekanisme Partial Import).
     *
     * HANYA membuat master `aset` (+ `master_barang`/`kategori_aset` bila nama
     * barang belum terdaftar). TIDAK PERNAH membuat/mengubah PemegangAset,
     * PenempatanAset, MutasiAset, DetailMutasiAset, UsulanPenghapusan, maupun
     * relasi lain — pemegang/penempatan tetap melalui alur Mutasi/Penempatan.
     *
     * Aturan yang ditegakkan (Partial Import):
     *  - Setiap baris dievaluasi dan disimpan secara independen (DB::transaction per baris).
     *  - Kolom wajib: Nomor Kartu Barang / Kode Barang, Nama Barang, Tanggal Pengadaan.
     *  - Duplikat terhadap database atau duplikat antarbaris di file yang sama ditolak (gagal).
     *  - Kolom wajib kosong ditolak (gagal).
     *  - Kondisi "Rusak Berat" DITOLAK agar tidak bypass alur usulan penghapusan.
     *  - Status aset hanya boleh "aktif".
     *  - Baris yang gagal tidak membatalkan baris lain yang valid.
     *  - Menghasilkan ringkasan dan detail kegagalan per baris untuk JSON / Flash session.
     */
    public function import(Request $request)
    {
        $this->authorizeAdminAset();

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ]);

        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        // Deteksi baris header: baris pertama (<=10) yang mengenali >=2 kolom.
        $headerRow = null;
        $colMap = [];
        foreach ($rows as $index => $row) {
            if ($index > 10) {
                break;
            }

            $map = $this->buildImportHeaderMap($row);
            if (count($map) >= 2) {
                $headerRow = $index;
                $colMap = $map;
                break;
            }
        }

        if ($headerRow === null) {
            $msg = 'Header kolom tidak ditemukan pada 10 baris pertama. Pastikan file sesuai template import aset barang.';
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'title' => 'Import Gagal',
                    'message' => $msg,
                    'berhasil' => 0,
                    'gagal' => 0,
                    'detail_kegagalan' => [$msg],
                ], 422);
            }

            return redirect()->route('aset-barang.index')->with('error', $msg);
        }

        $created = 0;
        $errors = [];
        $nomorDalamFile = [];

        foreach ($rows as $index => $row) {
            if ($index <= $headerRow) {
                continue;
            }

            if ($this->isContohImportRow($row)) {
                continue;
            }

            // Lewati baris yang benar-benar kosong di dokumen Excel
            if ($this->isRowBlank($row)) {
                continue;
            }

            $nomorRaw = $this->importCell($row, $colMap['nomor_kartu_barang'] ?? '');
            $nomor = trim(preg_replace('/\s+/', ' ', $nomorRaw));

            $namaRaw = $this->importCell($row, $colMap['nama_barang'] ?? '');
            $nama = trim(preg_replace('/\s+/', ' ', $namaRaw));

            // 1. Validasi Kode Barang / Nomor Kartu Barang (Wajib)
            if ($nomor === '') {
                $errors[] = "Baris {$index}: kolom kode barang wajib diisi.";
                continue;
            }

            if (mb_strlen($nomor) > 255) {
                $errors[] = "Baris {$index} — Kode {$nomor}: kode barang melebihi 255 karakter.";
                continue;
            }

            // 2. Deteksi duplikasi di dalam file Excel yang sama
            $kunci = mb_strtolower($nomor);
            if (isset($nomorDalamFile[$kunci])) {
                $barisAwal = $nomorDalamFile[$kunci];
                $errors[] = "Baris {$index} — Kode {$nomor}: kode barang duplikat di dalam file (kembar dengan baris {$barisAwal}).";
                continue;
            }
            $nomorDalamFile[$kunci] = $index;

            // 3. Deteksi duplikasi terhadap database
            $sudahAdaDiDb = Aset::whereRaw('LOWER(TRIM(nomor_kartu_barang)) = ?', [$kunci])->exists();
            if ($sudahAdaDiDb) {
                $errors[] = "Baris {$index} — Kode {$nomor}: kode barang sudah terdaftar.";
                continue;
            }

            // 4. Validasi Nama Barang (Wajib)
            if ($nama === '' || $nama === '-') {
                $errors[] = "Baris {$index} — Kode {$nomor}: kolom nama barang wajib diisi.";
                continue;
            }

            if (mb_strlen($nama) > 100) {
                $errors[] = "Baris {$index} — Kode {$nomor}: nama barang melebihi 100 karakter.";
                continue;
            }

            // 5. Validasi Merk (Opsional)
            $merk = trim(preg_replace('/\s+/', ' ', $this->importCell($row, $colMap['merk'] ?? '')));
            if (mb_strlen($merk) > 100) {
                $errors[] = "Baris {$index} — Kode {$nomor}: merk melebihi 100 karakter.";
                continue;
            }

            // 6. Validasi Kondisi (Opsional, default: "Baik")
            $kondisiRaw = $this->importCell($row, $colMap['kondisi'] ?? '');
            $kondisi = $this->importKondisi($kondisiRaw);
            if ($kondisi === null) {
                $kondisi = 'Baik';
            } elseif (! in_array($kondisi, self::KONDISI, true)) {
                $errors[] = "Baris {$index} — Kode {$nomor}: kondisi \"{$kondisiRaw}\" tidak dikenal. Gunakan: ".implode(', ', self::KONDISI).'.';
                continue;
            } elseif ($kondisi === 'Rusak Berat') {
                $errors[] = "Baris {$index} — Kode {$nomor}: kondisi \"Rusak Berat\" tidak boleh diimpor (kelola lewat alur usulan penghapusan).";
                continue;
            }

            // 7. Validasi Status Aset (Opsional, default: "aktif")
            $statusRaw = trim($this->importCell($row, $colMap['status_aset'] ?? ''));
            $status = mb_strtolower($statusRaw);
            if ($status !== '' && $status !== 'aktif') {
                $errors[] = "Baris {$index} — Kode {$nomor}: status aset \"{$statusRaw}\" tidak diizinkan. Import hanya membuat aset berstatus \"aktif\".";
                continue;
            }

            // 8. Validasi Nilai Perolehan (Opsional, >= 0)
            $nilaiRaw = $this->importCell($row, $colMap['nilai_perolehan'] ?? '');
            $nilai = $this->importNumber($nilaiRaw);
            if ($nilaiRaw !== '' && $nilai === null) {
                $errors[] = "Baris {$index} — Kode {$nomor}: format nilai perolehan tidak valid.";
                continue;
            }
            if ($nilai !== null && $nilai < 0) {
                $errors[] = "Baris {$index} — Kode {$nomor}: nilai perolehan tidak boleh negatif.";
                continue;
            }

            // 9. Validasi Tanggal Pengadaan (Wajib)
            $tanggalPengadaanRaw = $this->importCell($row, $colMap['tanggal_pengadaan'] ?? '');
            $tanggalPerolehanRaw = $this->importCell($row, $colMap['tanggal_perolehan'] ?? '');
            $tanggalHabisRaw = $this->importCell($row, $colMap['tanggal_habis_pakai'] ?? '');

            if ($tanggalPengadaanRaw === '' && $tanggalPerolehanRaw === '') {
                $errors[] = "Baris {$index} — Kode {$nomor}: kolom tanggal pengadaan wajib diisi.";
                continue;
            }

            $tanggalPengadaan = $this->importDate($tanggalPengadaanRaw);
            $tanggalPerolehan = $this->importDate($tanggalPerolehanRaw);
            $tanggalHabis = $this->importDate($tanggalHabisRaw);

            $hasilTanggal = TanggalAset::untukImport($tanggalPengadaan, $tanggalPerolehan);
            if ($hasilTanggal['konflik']) {
                $errors[] = "Baris {$index} — Kode {$nomor}: Tanggal Pengadaan ({$tanggalPengadaanRaw}) dan Tanggal Perolehan ({$tanggalPerolehanRaw}) berbeda. Samakan kedua tanggal atau isi salah satu saja.";
                continue;
            }
            $tanggal = $hasilTanggal['tanggal'];

            if ($tanggal === null) {
                $errors[] = "Baris {$index} — Kode {$nomor}: format tanggal pengadaan tidak valid.";
                continue;
            }

            // 10. Simpan per baris secara atomik dalam transaksi database independen
            try {
                DB::transaction(function () use ($nama, $nomor, $merk, $tanggal, $tanggalHabis, $nilai, $kondisi) {
                    Aset::create([
                        'id_barang' => $this->resolveBarang($nama),
                        'nomor_kartu_barang' => $nomor,
                        'merk' => $merk !== '' ? $merk : null,
                        'tanggal_pengadaan' => $tanggal,
                        'tanggal_habis_pakai' => $tanggalHabis,
                        'nilai_perolehan' => $nilai,
                        'kondisi' => $kondisi,
                        'status_aset' => 'aktif',
                        'is_kendaraan' => false,
                    ]);
                });
                $created++;
            } catch (\Throwable $e) {
                $errors[] = "Baris {$index} — Kode {$nomor}: {$e->getMessage()}";
            }
        }

        $failedCount = count($errors);

        if ($created === 0 && $failedCount === 0) {
            $status = 'error';
            $title = 'Import Gagal';
            $pesan = 'Tidak ada baris data barang yang ditemukan untuk diimpor. Pastikan file berisi data selain header dan contoh.';
        } elseif ($failedCount === 0) {
            $status = 'success';
            $title = 'Import Berhasil';
            $pesan = "Berhasil mengimpor {$created} aset barang.";
        } elseif ($created > 0) {
            $status = 'warning';
            $title = 'Import Selesai dengan Peringatan';
            $pesan = "Import Selesai dengan Peringatan\n\nBerhasil: {$created} barang\nGagal: {$failedCount} barang\n\nDetail kegagalan:\n".implode("\n", array_map(fn ($e) => "- {$e}", $errors));
        } else {
            $status = 'error';
            $title = 'Import Gagal';
            $pesan = "Import Gagal\n\nTidak ada data yang berhasil diimpor.\nBerhasil: 0 barang\nGagal: {$failedCount} barang\n\nDetail kegagalan:\n".implode("\n", array_map(fn ($e) => "- {$e}", $errors));
        }

        $responseData = [
            'success' => $created > 0,
            'status' => $status,
            'title' => $title,
            'berhasil' => $created,
            'gagal' => $failedCount,
            'message' => $pesan,
            'detail_kegagalan' => $errors,
        ];

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json($responseData);
        }

        return redirect()->route('aset-barang.index')
            ->with($status === 'error' ? 'error' : ($status === 'warning' ? 'warning' : 'success'), $pesan)
            ->with('import_summary', [
                'status' => $status,
                'title' => $title,
                'berhasil' => $created,
                'gagal' => $failedCount,
                'errors' => $errors,
            ]);
    }

    /**
     * Petakan judul kolom (baris header) ke field import, toleran urutan.
     */
    private function buildImportHeaderMap(array $headerRow): array
    {
        $aliases = [
            'nomor_kartu_barang' => [
                'nomor kartu barang', 'no kartu barang', 'kartu barang', 'nomor kartu', 'no kartu',
                'kode barang', 'kode inventaris', 'nomor inventaris', 'no inventaris', 'kib', 'nomor kib', 'no kib',
            ],
            'nama_barang' => ['nama barang', 'nama', 'jenis barang', 'uraian barang', 'uraian', 'nama aset'],
            'merk' => ['merk', 'merek', 'merk tipe', 'merk/tipe', 'tipe', 'model'],
            'tanggal_pengadaan' => ['tanggal pengadaan', 'tgl pengadaan', 'tgl. pengadaan', 'tanggal perolehan', 'tgl perolehan', 'tgl. perolehan', 'tanggal pembelian', 'tanggal beli'],
            'tanggal_habis_pakai' => ['tanggal habis pakai', 'tgl habis pakai', 'habis pakai', 'akhir masa pakai'],
            'nilai_perolehan' => ['nilai perolehan', 'nilai perolehan (rp)', 'harga perolehan', 'nilai', 'harga', 'harga beli', 'harga satuan'],
            'kondisi' => ['kondisi'],
            'status_aset' => ['status', 'status aset'],
        ];

        $normalize = fn ($v) => strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '', (string) $v));

        $map = [];
        foreach ($headerRow as $letter => $value) {
            $label = $normalize($value);
            if ($label === '') {
                continue;
            }

            foreach ($aliases as $field => $list) {
                foreach ($list as $alias) {
                    if ($normalize($alias) === $label) {
                        $map[$field] = $letter;
                        break 2;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Ambil nilai sel sebagai string (trim), aman bila kolom tak dipetakan.
     */
    private function importCell(array $row, string $letter): string
    {
        if ($letter === '' || ! array_key_exists($letter, $row)) {
            return '';
        }

        return trim((string) $row[$letter]);
    }

    /**
     * Apakah baris contoh bawaan template (mengandung "CONTOH").
     */
    private function isContohImportRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (is_string($cell) && stripos($cell, 'CONTOH') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apakah seluruh sel pada baris kosong atau hanya berisi whitespace.
     */
    private function isRowBlank(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Normalisasi kondisi ke salah satu nilai KONDISI (case-insensitive).
     * Nilai tak dikenal dikembalikan apa adanya agar bisa dilaporkan.
     */
    private function importKondisi(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        foreach (self::KONDISI as $kondisi) {
            if (strcasecmp($kondisi, $value) === 0) {
                return $kondisi;
            }
        }

        return $value;
    }

    /**
     * Parse tanggal Excel/teks menjadi Y-m-d (null bila tak terbaca).
     */
    private function importDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_numeric($value)) {
            $serial = (float) $value;
            if ($serial >= 25569 && $serial <= 2958465) {
                try {
                    return SpreadsheetDate::excelToDateTimeObject($serial)->format('Y-m-d');
                } catch (\Throwable $e) {
                    return null;
                }
            }
        }

        $value = trim((string) $value);
        if ($value === '' || $value === '-') {
            return null;
        }

        // Format DD/MM/YYYY, DD-MM-YYYY, atau DD.MM.YYYY (Format Baku Indonesia / Template)
        if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $value, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }

            return null;
        }

        // Format YYYY-MM-DD, YYYY/MM/DD, atau YYYY.MM.DD
        if (preg_match('/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', $value, $m)) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            $day = (int) $m[3];
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }

            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Parse angka rupiah toleran (ribuan titik / desimal koma).
     */
    private function importNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $value = preg_replace('/[^0-9,.\-]/', '', trim((string) $value));
        if ($value === '' || $value === '-' || $value === '.' || $value === ',') {
            return null;
        }

        $hasComma = str_contains($value, ',');
        $hasDot = str_contains($value, '.');

        if ($hasComma && $hasDot) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif ($hasComma) {
            $value = str_replace(',', '.', $value);
        } elseif ($hasDot && preg_match('/^\d{1,3}(\.\d{3})+$/', $value)) {
            $value = str_replace('.', '', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private static function streamXlsx(Spreadsheet $spreadsheet, string $filename)
    {
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            static fn () => $writer->save('php://output'),
            $filename
        );
    }
}
