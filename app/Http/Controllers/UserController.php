<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Lokasi;
use App\Models\Pegawai;
use App\Models\Role;
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
     * Daftar AKUN login (dialihkan ke modul Manajemen Pegawai).
     */
    public function index(Request $request)
    {
        abort_unless($this->scope->bolehKelolaModul(), 403, 'Anda tidak memiliki hak akses untuk menu ini.');

        return redirect()->route('master-data.pegawai.index', $request->query());
    }

    /**
     * Halaman detail akun (dialihkan ke detail Manajemen Pegawai tab Informasi Akun).
     */
    public function show(Pegawai $pegawai)
    {
        abort_unless($this->scope->bolehLihatPegawai($pegawai), 403, 'Anda tidak memiliki hak akses untuk pegawai ini.');

        return redirect()->route('master-data.pegawai.show', ['pegawai' => $pegawai, 'tab' => 'tab-akun']);
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

        $nipLength = strlen(trim((string) $pegawai->nip));
        $passwordRules = ['nullable', 'string', 'min:8'];
        if ($nipLength < 8) {
            $passwordRules = ['required', 'string', 'min:8'];
        }

        $data = $request->validate([
            'id_role' => ['required', 'exists:role,id_role'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username'],
            'password' => $passwordRules,
        ], [
            'password.required' => 'NIP pegawai kurang dari 8 karakter. Anda wajib memasukkan password manual minimal 8 karakter.',
            'password.min' => 'Password minimal harus 8 karakter.',
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

        return $this->berhasil('Akun pengguna berhasil dibuat.', route('master-data.pegawai.show', ['pegawai' => $pegawai, 'tab' => 'tab-akun']));
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

        return $this->berhasil('Akun berhasil diperbarui.', route('master-data.pegawai.show', ['pegawai' => $pegawai, 'tab' => 'tab-akun']));
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
            return $this->berhasil('Role sudah tidak berubah.', route('master-data.pegawai.show', ['pegawai' => $pegawai, 'tab' => 'tab-akun']));
        }

        $user->update(['id_role' => $role->id_role]);

        $this->logger->log(
            'user.role_changed',
            $pegawai,
            ['role' => $roleLama],
            ['role' => $roleBaru],
            sprintf('Mengubah role %s dari "%s" menjadi "%s".', $pegawai->nama_pegawai, $roleLama ?? '-', $roleBaru ?? '-')
        );

        return $this->berhasil('Role berhasil diperbarui.', route('master-data.pegawai.show', ['pegawai' => $pegawai, 'tab' => 'tab-akun']));
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
            return $this->berhasil('Status akun sudah '.$sesudah.'.', route('master-data.pegawai.show', ['pegawai' => $pegawai, 'tab' => 'tab-akun']));
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
                : 'Akun berhasil dinonaktifkan.',
            route('master-data.pegawai.show', ['pegawai' => $pegawai, 'tab' => 'tab-akun'])
        );
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

        return redirect($redirectTo ?? route('master-data.pegawai.index'))
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

        $fallbackRoute = request()->route('pegawai')
            ? route('master-data.pegawai.show', ['pegawai' => request()->route('pegawai'), 'tab' => 'tab-akun'])
            : route('master-data.pegawai.index');

        return redirect($redirectTo ?? $fallbackRoute)
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
