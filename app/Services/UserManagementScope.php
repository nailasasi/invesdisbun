<?php

namespace App\Services;

use App\Models\Pegawai;
use App\Models\Role;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Scope lokal untuk modul Data Pegawai & User Management.
 *
 * Sengaja dibuat terpisah dari AsetScope: file ini TIDAK mengubah perilaku
 * global aplikasi dan hanya dipanggil dari PegawaiController, UserController,
 * serta view Master Data.
 *
 * Aturan bisnis Fase 1A:
 *   - Admin Aset  = seluruh sistem (Dinas + UPT P2BTP + UPT PSBP).
 *   - Admin UPT   = hanya pegawai/akun pada SKPD UPT miliknya sendiri.
 *   - Admin Bidang & Pegawai: tanpa akses modul ini (dicegah middleware role).
 */
class UserManagementScope
{
    public const ROLE_ASET = 'Admin Aset';
    public const ROLE_BIDANG = 'Admin Bidang';
    public const ROLE_UPT = 'Admin UPT';
    public const ROLE_PEGAWAI = 'Pegawai';

    private ?User $user = null;
    private bool $skpdResolved = false;
    private ?int $skpdAdminUpt = null;

    public function user(): ?User
    {
        if ($this->user === null) {
            $this->user = Auth::user();
        }

        return $this->user;
    }

    public function namaRole(): string
    {
        return (string) ($this->user()?->role?->nama_role ?? '');
    }

    public function isAdminAset(): bool
    {
        return $this->namaRole() === self::ROLE_ASET;
    }

    public function isAdminUpt(): bool
    {
        return $this->namaRole() === self::ROLE_UPT;
    }

    /**
     * id_skpd UPT milik admin UPT yang sedang login.
     *
     * Mengembalikan null bila akun login bukan Admin UPT, tidak punya
     * pegawai, tidak punya penempatan, atau penempatannya bukan SKPD
     * jenis UPT. Kondisi null = tidak ada cakupan (deny, bukan fail-open).
     */
    public function skpdAdminUpt(): ?int
    {
        if (! $this->skpdResolved) {
            $this->skpdResolved = true;

            $idSkpd = $this->user()?->pegawai?->id_skpd;
            $jenis = $idSkpd !== null && $idSkpd !== ''
                ? Skpd::where('id_skpd', $idSkpd)->value('jenis_skpd')
                : null;

            $this->skpdAdminUpt = $idSkpd !== null && $jenis === 'UPT'
                ? (int) $idSkpd
                : null;
        }

        return $this->skpdAdminUpt;
    }

    public function bolehKelolaModul(): bool
    {
        return $this->isAdminAset() || ($this->isAdminUpt() && $this->skpdAdminUpt() !== null);
    }

    public function bolehLihatPegawai(?Pegawai $pegawai): bool
    {
        if (! $pegawai) {
            return false;
        }

        if ($this->isAdminAset()) {
            return true;
        }

        $skpdAdminUpt = $this->skpdAdminUpt();

        if ($this->isAdminUpt() && $skpdAdminUpt !== null) {
            return $pegawai->id_skpd !== null && (int) $pegawai->id_skpd === $skpdAdminUpt;
        }

        return false;
    }

    /**
     * Batasi query pegawai ke cakupan admin yang sedang login.
     * Admin UPT yang penempatannya tidak sah tidak melihat data apa pun.
     */
    public function batasiQuery($query)
    {
        if ($this->isAdminAset()) {
            return $query;
        }

        $skpdAdminUpt = $this->skpdAdminUpt();

        if ($this->isAdminUpt() && $skpdAdminUpt !== null) {
            return $query->where('id_skpd', $skpdAdminUpt);
        }

        return $query->whereRaw('1 = 0');
    }

    /**
     * Daftar nama role yang boleh ditetapkan oleh akun login ini.
     *
     * Admin Aset: seluruh role. Admin UPT: hanya Pegawai & Admin UPT
     * (dan tetap lolos validasi role terhadap SKPD pegawainya).
     *
     * @return array<int, string>
     */
    public function roleBolehDipilih(): array
    {
        if ($this->isAdminAset()) {
            return [self::ROLE_ASET, self::ROLE_BIDANG, self::ROLE_UPT, self::ROLE_PEGAWAI];
        }

        if ($this->isAdminUpt()) {
            return [self::ROLE_UPT, self::ROLE_PEGAWAI];
        }

        return [];
    }

    /**
     * Daftar record role untuk dropdown (sudah dibatasi kewenangan aktor).
     *
     * @return \Illuminate\Support\Collection<int, Role>
     */
    public function roleListBoleh()
    {
        $namaRole = $this->roleBolehDipilih();

        return Role::orderBy('id_role')
            ->get(['id_role', 'nama_role'])
            ->filter(fn (Role $role) => in_array($role->nama_role, $namaRole, true))
            ->values();
    }

    /**
     * Daftar role yang valid untuk pegawai tertentu (disesuaikan dengan SKPD pegawai).
     *
     * @return \Illuminate\Support\Collection<int, Role>
     */
    public function rolesUntukPegawai(?Pegawai $pegawai)
    {
        $allowed = $this->roleListBoleh();
        if (! $pegawai) {
            return $allowed;
        }

        $jenis = $pegawai->skpd?->jenis_skpd;

        return $allowed->filter(function (Role $role) use ($jenis) {
            return match ($role->nama_role) {
                self::ROLE_ASET => $jenis === 'Sekretariat',
                self::ROLE_BIDANG => $jenis === 'Bidang',
                self::ROLE_UPT => $jenis === 'UPT',
                self::ROLE_PEGAWAI => true,
                default => false,
            };
        })->values();
    }

    /**
     * Daftar SKPD yang boleh dipilih saat menambah/mengubah pegawai.
     * Admin Aset: seluruh SKPD. Admin UPT: hanya UPT miliknya.
     *
     * @return \Illuminate\Support\Collection<int, Skpd>
     */
    public function skpdBolehDipilih()
    {
        if ($this->isAdminAset()) {
            return Skpd::whereNotNull('jenis_skpd')
                ->with('lokasi')
                ->orderBy('jenis_skpd')
                ->orderBy('nama_skpd')
                ->get(['id_skpd', 'nama_skpd', 'jenis_skpd']);
        }

        $skpdAdminUpt = $this->skpdAdminUpt();

        if ($this->isAdminUpt() && $skpdAdminUpt !== null) {
            return Skpd::where('id_skpd', $skpdAdminUpt)
                ->with('lokasi')
                ->get(['id_skpd', 'nama_skpd', 'jenis_skpd']);
        }

        return collect();
    }

    /**
     * Validasi role terhadap penempatan (SKPD) pegawai DAN kewenangan aktor.
     *
     * Server-side, tidak bergantung pada dropdown. Melempar ValidationException
     * dengan field `id_role` bila ditolak.
     *
     * @throws ValidationException
     */
    public function validasiRoleUntukPegawai(string $namaRole, ?Pegawai $pegawai): void
    {
        if (! $pegawai) {
            throw ValidationException::withMessages([
                'id_role' => 'Pegawai tidak ditemukan.',
            ]);
        }

        if (! in_array($namaRole, $this->roleBolehDipilih(), true)) {
            throw ValidationException::withMessages([
                'id_role' => 'Anda tidak berwenang menetapkan role "'.$namaRole.'".',
            ]);
        }

        $jenisSkpd = $pegawai->skpd?->jenis_skpd;

        if ($namaRole === self::ROLE_ASET && $jenisSkpd !== 'Sekretariat') {
            throw ValidationException::withMessages([
                'id_role' => 'Role Admin Aset hanya dapat diberikan kepada pegawai dengan penempatan SKPD Sekretariat.',
            ]);
        }

        if ($namaRole === self::ROLE_BIDANG && $jenisSkpd !== 'Bidang') {
            throw ValidationException::withMessages([
                'id_role' => 'Role Admin Bidang hanya dapat diberikan kepada pegawai dengan penempatan SKPD jenis Bidang.',
            ]);
        }

        if ($namaRole === self::ROLE_UPT && $jenisSkpd !== 'UPT') {
            throw ValidationException::withMessages([
                'id_role' => 'Role Admin UPT hanya dapat diberikan kepada pegawai dengan penempatan SKPD jenis UPT.',
            ]);
        }

        if (! $this->isAdminAset()) {
            $skpdAdminUpt = $this->skpdAdminUpt();

            if ($skpdAdminUpt === null) {
                throw ValidationException::withMessages([
                    'id_role' => 'Penempatan UPT Anda tidak sah. Hubungi Admin Aset.',
                ]);
            }

            if ($pegawai->id_skpd === null || (int) $pegawai->id_skpd !== $skpdAdminUpt) {
                throw ValidationException::withMessages([
                    'id_role' => 'Anda hanya dapat mengelola pegawai pada UPT milik Anda sendiri.',
                ]);
            }
        }
    }
}
