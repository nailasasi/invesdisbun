<?php

namespace App\Services;

use App\Models\Aset;
use App\Models\Lokasi;
use App\Models\Ruangan;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Cakupan akses aset — satu-satunya sumber kebenaran hak akses aset.
 *
 * Semua query aset WAJIB melewati `terapkan()`. Menyembunyikan menu di
 * frontend bukan keamanan; filter harus ada di query/controller.
 *
 * Role generik (lihat migration 2026_10_01_000009) + penempatan skpd user:
 *   Admin Aset  -> cakupan penuh, seluruh aset lintas unit
 *   Admin Bidang-> HANYA lokasi Dinas (bila penempatannya Bidang);
 *                  selain itu / identitas NULL -> DENY
 *   Admin UPT   -> HANYA lokasi UPT miliknya (bila penempatannya UPT);
 *                  selain itu / identitas NULL -> DENY
 *   Pegawai     -> mengikuti skpd-nya:
 *                    skpd UPT          -> hanya aset di lokasi UPT tsb
 *                    skpd Bidang/Sekretariat -> hanya aset di lokasi Dinas
 *                  identitas NULL/tanpa pegawai -> DENY
 *   Role tak dikenal / tanpa role -> DENY (deny by default).
 *
 * Penentuan UPT memakai `skpd.jenis_skpd` (migration 2026_10_01_000010),
 * bukan inferensi nama. Tabel role <-> placement di `idLokasi()`.
 *
 * Aset tanpa lokasi fisik (belum pernah ditempatkan) tetap mengikuti aturan
 * yang sama: hanya terlihat oleh user dengan cakupan penuh.
 */
class AsetScope
{
    public const ROLE_ADMIN_ASET = 'Admin Aset';

    public const ROLE_ADMIN_BIDANG = 'Admin Bidang';

    public const ROLE_ADMIN_UPT = 'Admin UPT';

    public const ROLE_PEGAWAI = 'Pegawai';

    /**
     * Role yang diakui. Role lain (mis. sisa data lama) mendapat akses nol.
     */
    public const ROLE_VALID = [
        self::ROLE_ADMIN_ASET,
        self::ROLE_ADMIN_BIDANG,
        self::ROLE_ADMIN_UPT,
        self::ROLE_PEGAWAI,
    ];

    private ?User $user = null;

    private bool $userSudahDiambil = false;

    private ?int $skpdId = null;

    private ?string $jenisSkpd = null;

    private bool $skpdSudahDiresolve = false;

    /** @var int[]|null sudah dihitung: array lokasi UPT, atau null */
    private ?array $idLokasiUpt = null;

    private bool $idLokasiSudahDihitung = false;

    public function __construct(?User $user = null)
    {
        // User TIDAK boleh dicaptured di constructor. Container Laravel dapat
        // membangun controller (dan karena itu AsetScope ini) SEBELUM middleware
        // session/auth selesai memuat user — saat itu auth()->user() masih
        // null. Kalau null itu tersimpan, seluruh request berjalan tanpa
        // identitas: role terbaca null, cakupan dianggap tidak sah, dan
        // query aset difail-closed jadi `1 = 0` (akun Admin Aset pun ikut
        // terkunci). Karena itu user baru diambil saat benar-benar dibutuhkan.
        //
        // Parameter opsional juga bisa menerima model User KOSONG (exists =
        // false); objek seperti itu diabaikan danDiganti user yang login.
        if ($user !== null && $user->exists) {
            $this->user = $user;
            $this->userSudahDiambil = true;
            $this->resolveSkpd();
        }
    }

    // ------------------------------------------------------------------
    // Identitas & role
    // ------------------------------------------------------------------

    /**
     * User yang sedang login, diambil secara lazy lalu di-cache.
     */
    public function user(): ?User
    {
        $current = auth()->user();

        if (! $this->userSudahDiambil || ($current && $this->user?->id_user !== $current->id_user)) {
            $this->userSudahDiambil = true;
            $this->user = $current;
            $this->idLokasiSudahDihitung = false;
            $this->idLokasiUpt = [];
            $this->skpdSudahDiresolve = false;
            $this->skpdId = null;
            $this->jenisSkpd = null;
            $this->resolveSkpd();
        }

        return $this->user;
    }

    public function namaRole(): ?string
    {
        return $this->user()?->role?->nama_role;
    }

    /**
     * Role ternormalisasi (di-trim + lowercase).
     *
     * Penentuan role harus tahan terhadap spasi ganda di akhir atau perbedaan
     * kapitalisasi pada data role lama — kalau tidak, akun Admin Aset bisa
     * diam-diam kehilangan hak aksesnya.
     */
    private function roleKey(): ?string
    {
        $nama = $this->namaRole();

        return $nama === null ? null : mb_strtolower(trim($nama));
    }

    public function isAdminAset(): bool
    {
        return $this->roleKey() === mb_strtolower(self::ROLE_ADMIN_ASET);
    }

    public function isAdminBidang(): bool
    {
        return $this->roleKey() === mb_strtolower(self::ROLE_ADMIN_BIDANG);
    }

    public function isAdminUpt(): bool
    {
        return $this->roleKey() === mb_strtolower(self::ROLE_ADMIN_UPT);
    }

    public function isPegawai(): bool
    {
        return $this->roleKey() === mb_strtolower(self::ROLE_PEGAWAI);
    }

    /**
     * True bila user login punya role yang dikenal sistem.
     */
    public function roleValid(): bool
    {
        $valid = array_map(
            fn ($r) => mb_strtolower(trim($r)),
            self::ROLE_VALID
        );

        return $this->user() !== null && in_array($this->roleKey(), $valid, true);
    }

    /**
     * User skpd UPT — baik Admin UPT maupun Pegawai — hanya boleh
     * menyentuh aset yang secara fisik berada di lokasi UPT-nya.
     */
    public function adalahUpt(): bool
    {
        return $this->jenisSkpd() === 'UPT';
    }

    /**
     * True bila user berwenang mengakses seluruh unit (lintas skpd).
     *
     * Cakupan penuh HANYA untuk Admin Aset. Role lain — termasuk Admin
     * Bidang dan Admin UPT — dibatasi lokasi di `idLokasi()`. Role tak
     * dikenal / user tanpa role TIDAK mendapat cakupan penuh (fail closed).
     */
    public function cakupanPenuh(): bool
    {
        return $this->roleValid() && $this->isAdminAset();
    }

    // ------------------------------------------------------------------
    // Cakupan lokasi
    // ------------------------------------------------------------------

    public function skpdId(): ?int
    {
        return $this->skpdId;
    }

    public function jenisSkpd(): ?string
    {
        return $this->jenisSkpd;
    }

    /**
     * ID lokasi fisik yang boleh diakses user.
     *
     * - Admin Aset (cakupan penuh): null = tanpa filter lokasi. Caller WAJIB
     *   mengecek `cakupanPenuh()` sebelum memakai nilai ini (konvensi lama).
     * - Role lain: array lokasi sah, ATAU null = DENY (fail closed) bila
     *   role tak dikenal / penempatan skpd tidak cocok dengan role /
     *   identitas NULL (tanpa pegawai, id_skpd kosong, dsb).
     *
     * Tabel penempatan (role <-> jenis_skpd):
     *   Admin Bidang : Bidang -> [lokasi Dinas]; selain itu -> deny
     *   Admin UPT    : UPT    -> [lokasi UPT-nya]; selain itu -> deny
     *   Pegawai      : UPT    -> [lokasi UPT-nya]
     *                  Bidang/Sekretariat -> [lokasi Dinas]; selain itu -> deny
     */
    public function idLokasi(): ?array
    {
        if ($this->idLokasiSudahDihitung) {
            return $this->idLokasiUpt ?: null;
        }

        $this->idLokasiSudahDihitung = true;
        $this->idLokasiUpt = [];

        // Cakupan penuh tidak difilter lokasi; tetap null agar konsisten
        // dengan konvensi lama (caller cek cakupanPenuh() lebih dulu).
        if ($this->cakupanPenuh()) {
            return null;
        }

        // Role tak dikenal / tanpa role -> deny.
        if (! $this->roleValid()) {
            return null;
        }

        $lokasi = match (true) {
            $this->roleKey() === mb_strtolower(self::ROLE_ADMIN_BIDANG) =>
                $this->jenisSkpd() === 'Bidang' ? Lokasi::dinas() : null,
            $this->isAdminUpt() =>
                $this->jenisSkpd() === 'UPT' ? Lokasi::untukSkpd($this->skpdId) : null,
            $this->roleKey() === mb_strtolower(self::ROLE_PEGAWAI) => match ($this->jenisSkpd()) {
                'UPT' => Lokasi::untukSkpd($this->skpdId),
                'Bidang', 'Sekretariat' => Lokasi::dinas(),
                default => null,
            },
            default => null,
        };

        $this->idLokasiUpt = $lokasi ? [$lokasi->id_lokasi] : [];

        return $this->idLokasiUpt ?: null;
    }

    /**
     * Batasi query aset sesuai cakupan user.
     *
     * @param  string  $kolomLokasi  Kolom lokasi pada query tersebut.
     *                             Wajib ter-qualified agar tidak ambigu saat di-join.
     */
    public function terapkan(Builder $query, string $kolomLokasi = 'aset.id_lokasi'): Builder
    {
        if ($this->cakupanPenuh()) {
            return $query;
        }

        $idLokasi = $this->idLokasi();

        // Fail closed: user tak dikenal / UPT tanpa lokasi sah → nol aset.
        if (! $idLokasi) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($kolomLokasi, $idLokasi);
    }

    /**
     * Sama seperti terapkan(), tapi untuk query RUANGAN (kolom id_lokasi
     * ada langsung di tabel ruangan).
     */
    public function terapkanRuangan(Builder $query, string $kolom = 'ruangan.id_lokasi'): Builder
    {
        return $this->terapkan($query, $kolom);
    }

    /**
     * Cakupan lokasi sebagai Collection — untuk dropdown filter UI.
     *
     * @return Collection<int, Lokasi>
     */
    public function lokasiTerlihat(): Collection
    {
        if ($this->cakupanPenuh()) {
            return Lokasi::where('status', 'Aktif')
                ->orderBy('jenis_lokasi')->orderBy('nama_lokasi')
                ->get();
        }

        $id = $this->idLokasi() ?: [];

        if ($id === []) {
            return new Collection;
        }

        return Lokasi::whereIn('id_lokasi', $id)->get();
    }

    /**
     * Ruangan yang boleh dipilih pada form (mutasi, penempatan, dsb).
     */
    public function ruanganTerlihat(): Collection
    {
        $query = Ruangan::where('status', 'Aktif')->orderBy('nama_ruangan');

        $this->terapkanRuangan($query, 'id_lokasi');

        return $query->get();
    }

    /**
     * Pastikan user boleh mengakses satu aset tertentu (untuk detail & aksi).
     */
    public function bolehAksesAset(?Aset $aset): bool
    {
        if (! $this->roleValid()) {
            return false;
        }

        if ($this->cakupanPenuh()) {
            return true;
        }

        if (! $aset) {
            return false;
        }

        $idLokasi = $this->idLokasi();

        // 1. Cek berdasarkan lokasi fisik aset jika tersedia
        if ($aset->id_lokasi !== null && $idLokasi && in_array((int) $aset->id_lokasi, $idLokasi, true)) {
            return true;
        }

        // 2. Cek berdasarkan lokasi ruangan penempatan aktif jika ada
        $penempatanLokasiId = $aset->penempatanAktif?->ruangan?->id_lokasi;
        if ($penempatanLokasiId !== null && $idLokasi && in_array((int) $penempatanLokasiId, $idLokasi, true)) {
            return true;
        }

        // 3. Khusus Admin UPT: boleh mengakses aset tanpa id_lokasi jika id_skpd sesuai cakupan SKPD/UPT miliknya
        if ($this->isAdminUpt() && $aset->id_lokasi === null && $this->skpdId() !== null) {
            if ((int) $aset->id_skpd === (int) $this->skpdId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pastikan user boleh memakai lokasi tertentu (dropdown, form aset).
     */
    public function bolehAksesLokasi(?int $idLokasi): bool
    {
        if (! $this->roleValid()) {
            return false;
        }

        if ($this->cakupanPenuh()) {
            return $idLokasi !== null
                && Lokasi::where('id_lokasi', $idLokasi)->exists();
        }

        $id = $this->idLokasi() ?: [];

        return $idLokasi !== null && in_array((int) $idLokasi, $id, true);
    }

    /**
     * Pastikan user boleh memakai ruangan tertentu sebagai tujuan.
     */
    public function bolehAksesRuangan(?int $idRuangan): bool
    {
        if (! $this->roleValid()) {
            return false;
        }

        if ($this->cakupanPenuh()) {
            return true;
        }

        $idLokasi = $this->idLokasi() ?: [];
        $lokasiRuangan = Ruangan::where('id_ruangan', $idRuangan)->value('id_lokasi');

        return $lokasiRuangan !== null && in_array((int) $lokasiRuangan, $idLokasi, true);
    }

    /**
     * Pesan 403 yang informatif sesuai penyebab penolakan.
     */
    public function pesanAkses(string $aksi = 'mengakses data aset'): string
    {
        if (! $this->roleValid()) {
            return 'Akun Anda belum memiliki role yang dikenali sistem.';
        }

        if ($this->adalahUpt()) {
            return 'Aset ini berada di luar cakupan UPT Anda. '
                .ucfirst($aksi).' hanya dapat dilakukan untuk aset dalam cakupan UPT Anda.';
        }

        return 'Anda tidak memiliki hak akses untuk '.ucfirst($aksi).' aset ini.';
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    private function resolveSkpd(): void
    {
        if ($this->skpdSudahDiresolve) {
            return;
        }

        $this->skpdSudahDiresolve = true;

        // Admin Aset adalah wewenang lintas unit: skpd tidak membatasi
        // cakupannya sama sekali.
        if ($this->isAdminAset()) {
            $this->skpdId = null;
            $this->jenisSkpd = null;

            return;
        }

        $this->skpdId = $this->user()?->pegawai?->id_skpd;

        if (! $this->skpdId) {
            $this->jenisSkpd = null;

            return;
        }

        $this->jenisSkpd = Skpd::where('id_skpd', $this->skpdId)->value('jenis_skpd');
    }
}
