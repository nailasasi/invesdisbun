<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\DokumenSppbi;
use App\Models\Lokasi;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\Role;
use App\Models\UsulanRkbmd;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\UserManagementScope;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * USER MANAGEMENT (akun login).
 *
 * Berurusan dengan AKUN milik pegawai yang SUDAH ADA: username, role,
 * status. Bukan master data orang — lihat PegawaiController.
 *
 * Konsep Fase 1A:
 *   Pegawai -> 0 atau 1 User -> Role -> Hak Akses
 *
 *   - Buat Akun   = mengikat akun ke pegawai yang belum punya akun.
 *   - Ubah Role   = mengubah akun yang SAMA (tidak pernah membuat akun baru).
 *   - Admin UPT   = hanya mengelola akun pada UPT miliknya.
 */
class UserController extends Controller
{
    /**
     * Label field untuk ditampilkan pada timeline riwayat.
     *
     * 'unit_kerja' tetap dipertahankan untuk entri riwayat lama yang
     * tercatat sebelum overlay unit_kerja dibongkar.
     */
    public const FIELD_LABELS = [
        'nama_pegawai' => 'Nama Pegawai',
        'nip' => 'NIP',
        'jabatan' => 'Jabatan',
        'skpd' => 'SKPD',
        'unit_kerja' => 'Unit Kerja',
        'lokasi' => 'Lokasi',
        'ruangan' => 'Ruangan',
        'username' => 'Username',
        'role' => 'Role',
        'status' => 'Status Akun',
        'password_diubah' => 'Password',
    ];

    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly UserManagementScope $scope,
    ) {
    }

    /**
     * Daftar AKUN login (bukan master pegawai).
     */
    public function index(Request $request)
    {
        abort_unless($this->scope->bolehKelolaModul(), 403, 'Anda tidak memiliki hak akses untuk menu ini.');

        $search = trim((string) $request->query('search'));
        $search = $search === '' ? null : $search;

        $idRole = $request->query('role');
        $idRole = $idRole !== null && $idRole !== '' && ctype_digit((string) $idRole) ? (int) $idRole : null;

        $status = $request->query('status');
        $status = in_array($status, ['aktif', 'nonaktif'], true) ? $status : null;

        $idSkpd = $request->query('skpd');
        $idSkpd = $idSkpd !== null && $idSkpd !== '' && ctype_digit((string) $idSkpd) ? (int) $idSkpd : null;

        $jenisSkpd = $request->query('jenis_skpd');
        $jenisSkpd = in_array($jenisSkpd, ['Sekretariat', 'Bidang', 'UPT'], true) ? $jenisSkpd : null;

        $userList = User::query()
            ->with(['pegawai.skpd', 'role'])
            ->whereHas('pegawai', fn ($q) => $this->scope->batasiQuery($q))
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('username', 'like', "%{$search}%")
                        ->orWhereHas('pegawai', function ($qp) use ($search) {
                            $qp->where('nip', 'like', "%{$search}%")
                                ->orWhere('nama_pegawai', 'like', "%{$search}%");
                        });
                });
            })
            ->when($idRole, fn ($query, $idRole) => $query->where('id_role', $idRole))
            ->when($status, fn ($query, $status) => $query->where('status_user', $status))
            ->when($idSkpd, fn ($query, $idSkpd) => $query->whereHas('pegawai', fn ($q) => $q->where('id_skpd', $idSkpd)))
            ->when($jenisSkpd, fn ($query, $jenisSkpd) => $query->whereHas('pegawai.skpd', fn ($q) => $q->where('jenis_skpd', $jenisSkpd)))
            ->orderBy('id_user')
            ->paginate(10)
            ->withQueryString();

        // Pegawai yang BELUM punya akun — dipakai modal "Buat Akun".
        $pegawaiTanpaAkun = $this->scope->batasiQuery(Pegawai::query())
            ->whereDoesntHave('user')
            ->with('skpd')
            ->orderBy('nama_pegawai')
            ->get(['id_pegawai', 'nip', 'nama_pegawai', 'jabatan', 'id_skpd']);

        $roleList = $this->scope->roleListBoleh();
        $skpdList = $this->scope->skpdBolehDipilih();

        return view('user.index', compact('userList', 'pegawaiTanpaAkun', 'roleList', 'skpdList'));
    }

    /**
     * Halaman detail akun: DATA PEGAWAI (read-only) + AKUN PENGGUNA.
     */
    public function show(Pegawai $pegawai)
    {
        abort_unless($this->scope->bolehLihatPegawai($pegawai), 403, 'Anda tidak memiliki hak akses untuk pegawai ini.');

        $pegawai->load('skpd', 'ruangan', 'user.role');

        $riwayatLogs = ActivityLog::query()
            ->where('subject_type', Pegawai::class)
            ->where('subject_id', $pegawai->id_pegawai)
            ->orderByDesc('created_at')
            ->orderByDesc('id_activity_log')
            ->get();

        $roleList = $this->scope->roleListBoleh();
        $fieldLabels = self::FIELD_LABELS;
        $jumlahAsetAktif = PemegangAset::where('id_pegawai', $pegawai->id_pegawai)
            ->where('status', 'aktif')
            ->count();

        return view('user.show', compact('pegawai', 'riwayatLogs', 'roleList', 'fieldLabels', 'jumlahAsetAktif'));
    }

    /**
     * BUAT AKUN untuk pegawai yang SUDAH ADA (belum punya akun).
     *
     * Tidak membuat pegawai baru, tidak mengubah data pegawai.
     * Username & password default = NIP.
     *
     * Pencegahan duplikat dilakukan di application layer (cek + lock baris
     * pegawai dalam transaksi) karena users.id_pegawai belum UNIQUE.
     */
    public function storeAccount(Request $request, Pegawai $pegawai)
    {
        abort_unless($this->scope->bolehLihatPegawai($pegawai), 403, 'Anda tidak memiliki hak akses untuk pegawai ini.');

        $data = $request->validate([
            'id_role' => ['required', 'exists:role,id_role'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $role = Role::find($data['id_role']);

        if (! $role) {
            throw ValidationException::withMessages([
                'id_role' => 'Role tidak ditemukan.',
            ]);
        }

        $this->scope->validasiRoleUntukPegawai($role->nama_role, $pegawai);

        if ($pegawai->user()->exists()) {
            throw ValidationException::withMessages([
                'pegawai' => 'Pegawai ini sudah memiliki akun pengguna.',
            ]);
        }

        if (! $pegawai->nip) {
            throw ValidationException::withMessages([
                'nip' => 'Pegawai ini belum memiliki NIP. Lengkapi data NIP terlebih dahulu di Data Pegawai.',
            ]);
        }

        try {
            $user = DB::transaction(function () use ($pegawai, $data, $role) {
                // Kunci baris pegawai agar dua request serentak tidak bisa
                // sama-sama lolos pemeriksaan "belum punya akun".
                $peg = Pegawai::whereKey($pegawai->id_pegawai)->lockForUpdate()->first();

                if (! $peg) {
                    throw ValidationException::withMessages([
                        'pegawai' => 'Pegawai tidak ditemukan.',
                    ]);
                }

                if ($peg->user()->exists()) {
                    throw ValidationException::withMessages([
                        'pegawai' => 'Pegawai ini sudah memiliki akun pengguna.',
                    ]);
                }

                return $peg->user()->create([
                    'id_pegawai' => $peg->id_pegawai,
                    'username' => ($data['username'] ?? '') !== '' ? $data['username'] : $peg->nip,
                    'password' => ($data['password'] ?? '') !== '' ? $data['password'] : $peg->nip,
                    'id_role' => $role->id_role,
                    'status_user' => 'aktif',
                ]);
            });
        } catch (QueryException $e) {
            // Cadangan bila terjadi race di luar kendali lock (mis. duplikat username).
            if ((string) $e->getCode() === '23000' || str_contains((string) $e->getMessage(), 'users_username_unique')) {
                throw ValidationException::withMessages([
                    'username' => 'Username tersebut sudah dipakai pengguna lain.',
                ]);
            }

            throw $e;
        }

        $pegawai->refresh()->load('skpd', 'ruangan', 'user.role');

        $this->logger->log(
            'user.created',
            $pegawai,
            [],
            $this->snapshot($pegawai),
            sprintf(
                'Membuat akun pengguna untuk %s (%s) dengan role %s.',
                $pegawai->nama_pegawai,
                $pegawai->nip,
                $role->nama_role
            )
        );

        return $this->berhasil('Akun pengguna berhasil dibuat.', route('user.show', $pegawai->id_pegawai));
    }

    /**
     * Perbarui field AKUN saja (username & password opsional).
     *
     * Data pegawai (NIP, nama, jabatan, SKPD, ruangan) diubah lewat
     * Data Pegawai — bukan dari halaman akun.
     */
    public function update(Request $request, Pegawai $pegawai)
    {
        abort_unless($this->scope->bolehLihatPegawai($pegawai), 403, 'Anda tidak memiliki hak akses untuk pegawai ini.');

        $user = $pegawai->user;

        if (! $user) {
            return $this->gagal('Pegawai ini belum memiliki akun login.');
        }

        $data = $request->validate([
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->id_user, 'id_user')],
            'password' => ['nullable', 'sometimes', 'string', 'min:8'],
        ]);

        $passwordDiubah = ! empty($data['password']);

        $sebelum = $this->snapshot($pegawai);

        $userData = ['username' => $data['username']];

        if ($passwordDiubah) {
            $userData['password'] = $data['password'];
        }

        $user->update($userData);

        $pegawai->refresh()->load('skpd', 'ruangan', 'user.role');
        $sesudah = $this->snapshot($pegawai);

        // Password tidak pernah dicatat nilainya — hanya penanda bahwa diubah.
        $sebelum['password_diubah'] = false;
        $sesudah['password_diubah'] = $passwordDiubah;

        $diff = $this->logger->diff($sebelum, $sesudah);

        if (! empty($diff['changed'])) {
            $this->logger->log(
                'user.updated',
                $pegawai,
                $diff['old'],
                $diff['new'],
                $this->describe($diff['changed'], 'Memperbarui akun '.$pegawai->nama_pegawai)
            );
        }

        return $this->berhasil('Akun berhasil diperbarui.');
    }

    /**
     * Ubah role akun pegawai — mengubah akun yang SAMA.
     *
     * Tidak membuat user baru, tidak membuat pegawai baru, tidak menghapus
     * akun lama. Berlaku untuk skenario pergantian admin.
     */
    public function updateRole(Request $request, Pegawai $pegawai)
    {
        abort_unless($this->scope->bolehLihatPegawai($pegawai), 403, 'Anda tidak memiliki hak akses untuk pegawai ini.');

        $data = $request->validate([
            'id_role' => ['required', 'exists:role,id_role'],
        ]);

        $user = $pegawai->user;

        if (! $user) {
            return $this->gagal('Pegawai ini belum memiliki akun login.');
        }

        if ($user->id_user === Auth::id()) {
            return $this->gagal('Anda tidak dapat mengubah role akun Anda sendiri.');
        }

        $role = Role::find($data['id_role']);

        if (! $role) {
            throw ValidationException::withMessages([
                'id_role' => 'Role tidak ditemukan.',
            ]);
        }

        // Validasi role <-> penempatan (SKPD) pegawai + kewenangan aktor.
        $this->scope->validasiRoleUntukPegawai($role->nama_role, $pegawai);

        $roleLama = $user->role?->nama_role;
        $roleBaru = $role->nama_role;

        if ((string) $user->id_role === (string) $data['id_role']) {
            return $this->berhasil('Role sudah tidak berubah.');
        }

        $user->update(['id_role' => $role->id_role]);

        $this->logger->log(
            'user.role_changed',
            $pegawai,
            ['role' => $roleLama],
            ['role' => $roleBaru],
            sprintf('Mengubah role %s dari "%s" menjadi "%s".', $pegawai->nama_pegawai, $roleLama ?? '-', $roleBaru ?? '-')
        );

        return $this->berhasil('Role berhasil diperbarui.');
    }

    /**
     * Aktifkan / nonaktifkan akun (soft, data & riwayat tetap terjaga).
     */
    public function updateStatus(Request $request, Pegawai $pegawai)
    {
        abort_unless($this->scope->bolehLihatPegawai($pegawai), 403, 'Anda tidak memiliki hak akses untuk pegawai ini.');

        $data = $request->validate([
            'status_user' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ]);

        $user = $pegawai->user;

        if (! $user) {
            return $this->gagal('Pegawai ini belum memiliki akun login.');
        }

        if ($user->id_user === Auth::id()) {
            return $this->gagal('Anda tidak dapat mengubah status akun Anda sendiri.');
        }

        $sebelum = $user->status_user;
        $sesudah = $data['status_user'];

        if ($sebelum === $sesudah) {
            return $this->berhasil('Status akun sudah '.$sesudah.'.');
        }

        $user->update(['status_user' => $sesudah]);

        $this->logger->log(
            'user.status_changed',
            $pegawai,
            ['status' => $sebelum],
            ['status' => $sesudah],
            sprintf(
                '%s akun %s dari "%s" menjadi "%s".',
                $sesudah === 'aktif' ? 'Mengaktifkan' : 'Menonaktifkan',
                $pegawai->nama_pegawai,
                $sebelum,
                $sesudah
            )
        );

        return $this->berhasil(
            $sesudah === 'aktif'
                ? 'Akun berhasil diaktifkan.'
                : 'Akun berhasil dinonaktifkan.'
        );
    }

    /**
     * Hapus pegawai beserta akun loginnya (permanen) — hanya Admin Aset.
     *
     * Ditolak bila masih terikat dengan aset, dokumen SPPBI, atau usulan
     * RKBMD — karena `pegawai` ber-cascade pada tabel-tabel tersebut.
     * Gunakan Nonaktifkan bila hanya ingin menutup akses login.
     */
    public function destroy(Pegawai $pegawai)
    {
        abort_unless($this->scope->isAdminAset(), 403, 'Anda tidak memiliki hak akses untuk menu ini.');

        if ($pegawai->user && $pegawai->user->id_user === Auth::id()) {
            return $this->gagal('Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $terikat = array_filter([
            'aset yang masih dipegang' => PemegangAset::where('id_pegawai', $pegawai->id_pegawai)->where('status', 'aktif')->count(),
            'dokumen SPPBI' => DokumenSppbi::where('id_pegawai', $pegawai->id_pegawai)->count(),
            'usulan RKBMD' => UsulanRkbmd::where('id_pegawai', $pegawai->id_pegawai)->count(),
        ]);

        if (! empty($terikat)) {
            $catatan = [];
            foreach ($terikat as $label => $jumlah) {
                $catatan[] = $jumlah.' '.$label;
            }

            return $this->gagal(
                'Tidak dapat menghapus permanen karena masih ada '.implode(', ', $catatan)
                .'. Gunakan tombol Nonaktifkan agar riwayat dan aset tetap terjaga.'
            );
        }

        $this->logger->log(
            'user.deleted',
            $pegawai,
            $this->snapshot($pegawai),
            [],
            'Menghapus permanen user '.$pegawai->nama_pegawai.' beserta akun loginnya.'
        );

        $pegawai->user?->delete();
        $pegawai->delete();

        return $this->berhasil('User berhasil dihapus.', route('user.index'));
    }

    /**
     * Sukses. Mengembalikan JSON untuk permintaan AJAX, atau redirect
     * dengan flash message untuk form biasa.
     */
    private function berhasil(string $message, ?string $redirectTo = null)
    {
        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return redirect($redirectTo ?? route('user.index'))
            ->with('success', $message);
    }

    /**
     * Gagal. Permintaan non-JSON di-redirect balik dengan pesan error.
     */
    private function gagal(string $message, int $status = 422, ?string $redirectTo = null)
    {
        if (request()->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], $status);
        }

        return redirect($redirectTo ?? route('user.show', request()->route('pegawai')))
            ->with('error', $message);
    }

    /**
     * Potret pegawai + akun dalam bentuk label yang mudah dibaca.
     * Nilai id sengaja diubah ke nama agar timeline tidak perlu join.
     * Kredensial tidak pernah disertakan.
     *
     * @return array<string, mixed>
     */
    private function snapshot(?Pegawai $pegawai): array
    {
        if (! $pegawai) {
            return [];
        }

        $pegawai->loadMissing('skpd', 'ruangan', 'user.role');

        return [
            'nama_pegawai' => $pegawai->nama_pegawai,
            'nip' => $pegawai->nip,
            'jabatan' => $pegawai->jabatan,
            'skpd' => $pegawai->skpd?->nama_skpd,
            'lokasi' => Lokasi::untukSkpd($pegawai->id_skpd)?->nama_lokasi,
            'ruangan' => $pegawai->ruangan?->nama_ruangan,
            'username' => $pegawai->user?->username,
            'role' => $pegawai->user?->role?->nama_role,
            'status' => $pegawai->user?->status_user,
        ];
    }

    /**
     * @param  array<int, string>  $changed
     */
    private function describe(array $changed, string $prefix): string
    {
        if (empty($changed)) {
            return $prefix.' tanpa perubahan data berarti.';
        }

        $parts = array_map(
            fn (string $key) => self::FIELD_LABELS[$key] ?? $key,
            $changed
        );

        return $prefix.': '.implode(', ', $parts).'.';
    }
}
