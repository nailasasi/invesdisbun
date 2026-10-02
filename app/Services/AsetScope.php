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
 * Role generik (lihat migration 2026_10_01_000009):
 *   Admin Aset  -> seluruh aset lintas unit
 *   Admin Bidang-> seluruh aset lingkungan dinas, termasuk aset di UPT
 *   Pegawai     -> mengikuti skpd-nya:
 *                    skpd non-UPT (Bidang/Sekretariat) -> seluruh aset dinas
 *                    skpd UPT                         -> hanya aset di lokasi UPT
 *   Admin UPT   -> hanya aset yang lokasinya di dalam lokasi UPT tersebut
 *
 * Penentuan UPT memakai `skpd.jenis_skpd` (migration 2026_10_01_000010),
 * bukan inferensi nama.
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
        if (! $this->userSudahDiambil) {
            $this->userSudahDiambil = true;
            $this->user = auth()->user();
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

    public function isAdminUpt(): bool
    {
        return $this->roleKey() === mb_strtolower(self::ROLE_ADMIN_UPT);
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
     * True bila user berwenang administers seluruh unit.
     *
     * Cakupan penuh hanya untuk role yang memang berwenang: Admin Aset,
     * Admin Bidang, dan Pegawai non-UPT. Role tak dikenal / user tanpa
     * role TIDAK mendapat cakupan penuh (fail closed).
     */
    public function cakupanPenuh(): bool
    {
        if (! $this->roleValid()) {
            return false;
        }

        if ($this->isAdminAset() || $this->roleKey() === mb_strtolower(self::ROLE_ADMIN_BIDANG)) {
            return true;
        }

        // Pegawai / Admin UPT: seluruh dinas KECUALI bila skpd-nya UPT.
        return ! $this->adalahUpt();
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
     * ID lokasi fisik yang boleh diakses user. Null = tidak ada lokasi
     * yang boleh diakses (fail closed), bukan "semua".
     */
    public function idLokasi(): ?array
    {
        if ($this->idLokasiSudahDihitung) {
            return $this->idLokasiUpt ?: null;
        }

        $this->idLokasiSudahDihitung = true;
        $this->idLokasiUpt = [];

        if ($this->cakupanPenuh() || ! $this->adalahUpt()) {
            // Cakupan penuh tidak difilter lokasi; user non-UPT tanpa lokasi
            // juga tidak perlu daftar lokasi.
            $this->idLokasiUpt = [];

            return null;
        }

        $lokasi = Lokasi::untukSkpd($this->skpdId);

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

        $idLokasi = $this->idLokasi();

        if (! $idLokasi || ! $aset) {
            return false;
        }

        // Aset tanpa lokasi fisik (belum ditempatkan) tidak terlihat oleh
        // user UPT — fail closed.
        return $aset->id_lokasi !== null
            && in_array((int) $aset->id_lokasi, $idLokasi, true);
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
