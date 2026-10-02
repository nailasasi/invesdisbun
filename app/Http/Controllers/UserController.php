<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Aset;
use App\Models\DokumenSppbi;
use App\Models\MutasiAset;
use App\Models\Lokasi;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Skpd;
use App\Models\UsulanRkbmd;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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

    public function __construct(private readonly ActivityLogger $logger)
    {
    }

    /**
     * Daftar pegawai beserta akun login & role (halaman User Management).
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $status = $request->query('status');

        if (! in_array($status, ['aktif', 'nonaktif'], true)) {
            $status = null;
        }

        $pegawaiList = Pegawai::with('skpd', 'ruangan', 'user.role')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama_pegawai', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($uq) => $uq->where('username', 'like', "%{$search}%"));
                });
            })
            ->when($status, fn ($query, $status) => $query->whereHas('user', fn ($uq) => $uq->where('status_user', $status)))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $roleList = Role::orderBy('id_role')->get(['id_role', 'nama_role']);
        $skpdList = $this->skpdList();
        $ruanganList = $this->ruanganList();
        $lokasiDinas = Lokasi::dinas();

        return view('user.index', compact('pegawaiList', 'skpdList', 'roleList', 'ruanganList', 'lokasiDinas'));
    }

    /**
     * Halaman detail user: tab Profil & tab Riwayat.
     */
    public function show(Pegawai $pegawai)
    {
        $pegawai->load('skpd', 'ruangan', 'user.role');

        $riwayatLogs = ActivityLog::query()
            ->where('subject_type', Pegawai::class)
            ->where('subject_id', $pegawai->id_pegawai)
            ->orderByDesc('created_at')
            ->orderByDesc('id_activity_log')
            ->get();

        $roleList = Role::orderBy('id_role')->get(['id_role', 'nama_role']);
        $skpdList = $this->skpdList();
        $ruanganList = $this->ruanganList();
        $lokasiDinas = Lokasi::dinas();
        $fieldLabels = self::FIELD_LABELS;
        $jumlahAsetAktif = PemegangAset::where('id_pegawai', $pegawai->id_pegawai)
            ->where('status', 'aktif')
            ->count();

        return view('user.show', compact('pegawai', 'riwayatLogs', 'roleList', 'skpdList', 'ruanganList', 'lokasiDinas', 'fieldLabels', 'jumlahAsetAktif'));
    }

    /**
     * Tambah pegawai + akun login.
     * Username dipakai NIP bila tidak diisi; password awal = NIP.
     */
    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $pegawai = Pegawai::create([
            'nip' => $data['nip'],
            'nama_pegawai' => $data['nama_pegawai'],
            'jabatan' => $data['jabatan'] ?? null,
            'id_skpd' => $data['id_skpd'] ?? null,
            'id_ruangan' => $data['id_ruangan'] ?? null,
        ]);

        $pegawai->user()->create([
            'username' => ($data['username'] ?? null) ?: $data['nip'],
            'password' => ($data['password'] ?? null) ?: $data['nip'],
            'id_role' => $data['id_role'],
            'status_user' => 'aktif',
        ]);

        $pegawai->refresh()->load('skpd', 'ruangan', 'user.role');

        $this->logger->log(
            'user.created',
            $pegawai,
            [],
            $this->snapshot($pegawai),
            sprintf('Menambahkan user %s (%s) dengan role %s.', $pegawai->nama_pegawai, $pegawai->nip, $pegawai->user?->role?->nama_role)
        );

        return $this->berhasil('User berhasil ditambahkan.', route('user.index'));
    }

    /**
     * Perbarui pegawai + akun (username bisa diubah, password opsional).
     *
     * Catatan: perpindahan skpd TIDAK memindahkan aset. Penempatan
     * aset tetap mengikuti ruangan kerja, sesuai alur yang sudah ada.
     */
    public function update(Request $request, Pegawai $pegawai)
    {
        $data = $this->validateUpdateData($request, $pegawai);

        $ruanganBaru = $data['id_ruangan'] ?? null;
        $passwordDiubah = ! empty($data['password']);

        // Aset yang sedang dipegang pegawai mengikuti ruangan kerja pegawai.
        $asetPemegangIds = PemegangAset::where('id_pegawai', $pegawai->id_pegawai)
            ->where('status', 'aktif')
            ->pluck('id_aset');

        if ($ruanganBaru === null && $asetPemegangIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'id_ruangan' => "Tidak dapat mengosongkan ruangan: masih ada {$asetPemegangIds->count()} aset yang dipegang pegawai ini. Pindahkan pemegangnya terlebih dahulu.",
            ]);
        }

        $sebelum = $this->snapshot($pegawai);

        $pegawai->update([
            'nip' => $data['nip'],
            'nama_pegawai' => $data['nama_pegawai'],
            'jabatan' => $data['jabatan'] ?? null,
            'id_skpd' => $data['id_skpd'] ?? null,
            'id_ruangan' => $ruanganBaru,
        ]);

        // Semua aset yang sedang dipegang pegawai selalu disinkronkan ke ruangan kerja
        // pegawai (aturan: ruangan aset ber-pemegang mengikuti ruangan pegawainya).
        // Bila posisi berubah, catat mutasi 'Pindah Ruangan'.
        if ($ruanganBaru !== null && $asetPemegangIds->isNotEmpty()) {
            $perluPindah = [];
            foreach ($asetPemegangIds as $idAset) {
                $aset = Aset::find($idAset);
                if ($aset && $aset->penempatanAktif?->id_ruangan != $ruanganBaru) {
                    $perluPindah[] = $aset;
                }
            }

            if (! empty($perluPindah)) {
                $mutasi = MutasiAset::create([
                    'tanggal_mutasi' => now()->toDateString(),
                    'jenis_mutasi' => 'Pindah Ruangan',
                    'keterangan' => 'Ruangan kerja pegawai diubah',
                    'id_user_penginput' => auth()->id(),
                    'status_mutasi' => 'selesai',
                ]);

                foreach ($perluPindah as $aset) {
                    $aset->moveToRoom($ruanganBaru, $pegawai->id_pegawai, null, $mutasi->id_mutasi);
                }
            }
        }

        if ($pegawai->user) {
            $userData = [
                'username' => ($data['username'] ?? null) ?: ($pegawai->user->username ?? ''),
            ];

            if (! empty($data['id_role'])) {
                $userData['id_role'] = $data['id_role'];
            }

            if ($passwordDiubah) {
                $userData['password'] = $data['password'];
            }

            $pegawai->user->update($userData);
        }

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
                $this->describe($diff['changed'], 'Memperbarui data user '.$pegawai->nama_pegawai)
            );
        }

        return $this->berhasil('User berhasil diperbarui.');
    }

    /**
     * Ubah role akun pegawai (aksi terpisah dari edit).
     *
     * Sengaja tidak menyentuh unit kerja maupun aset yang dipegang.
     */
    public function updateRole(Request $request, Pegawai $pegawai)
    {
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

        $roleLama = $user->role?->nama_role;
        $roleBaru = Role::find($data['id_role'])?->nama_role;

        if ((string) $user->id_role === (string) $data['id_role']) {
            return $this->berhasil('Role sudah tidak berubah.');
        }

        $user->update(['id_role' => $data['id_role']]);

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
     * Hapus pegawai beserta akun loginnya (permanen).
     *
     * Ditolak bila masih terikat dengan aset, dokumen SPPBI, atau usulan
     * RKBMD — karena `pegawai` ber-cascade pada tabel-tabel tersebut.
     * Gunakan Nonaktifkan bila hanya ingin menutup akses login.
     */
    public function destroy(Pegawai $pegawai)
    {
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
     * kembali ke halaman detail dengan flash message untuk form biasa.
     */
    private function berhasil(string $message, ?string $redirectTo = null)
    {
        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return redirect($redirectTo ?? route('user.show', request()->route('pegawai')))
            ->with('success', $message);
    }

    /**
     * Gagal. Permintaan non-JSON di-redirect balik ke halaman detail dengan
     * pesan error — mengembalikan 422 pada form biasa hanya akan menampilkan
     * halaman error mentah.
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
            'lokasi' => $this->namaLokasi($pegawai),
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

    private function skpdList()
    {
        return Skpd::whereNotNull('jenis_skpd')
            ->with('lokasi')
            ->orderBy('jenis_skpd')
            ->orderBy('nama_skpd')
            ->get(['id_skpd', 'nama_skpd', 'jenis_skpd']);
    }

    private function ruanganList()
    {
        return Ruangan::with('skpd')
            ->orderBy('nama_ruangan')
            ->get(['id_ruangan', 'nama_ruangan', 'id_skpd', 'id_lokasi']);
    }

    private function validateData(Request $request, ?Pegawai $pegawai = null): array
    {
        $pegawaiId = $pegawai?->id_pegawai;
        $userId = $pegawai?->user?->id_user;

        return $request->validate([
            'nip' => ['required', 'string', 'max:30', Rule::unique('pegawai', 'nip')->ignore($pegawaiId, 'id_pegawai')],
            'nama_pegawai' => ['required', 'string', 'max:100'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'id_skpd' => ['nullable', 'exists:skpd,id_skpd'],
            'id_ruangan' => [...$this->ruanganRules($request)],
            'username' => ['nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($userId, 'id_user')],
            'password' => ['nullable', 'sometimes', 'string', 'min:8'],
            'id_role' => ['required', 'exists:role,id_role'],
        ]);
    }

    private function validateUpdateData(Request $request, ?Pegawai $pegawai = null): array
    {
        $pegawaiId = $pegawai?->id_pegawai;
        $userId = $pegawai?->user?->id_user;

        return $request->validate([
            'nip' => ['required', 'string', 'max:30', Rule::unique('pegawai', 'nip')->ignore($pegawaiId, 'id_pegawai')],
            'nama_pegawai' => ['required', 'string', 'max:100'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'id_skpd' => ['nullable', 'exists:skpd,id_skpd'],
            'id_ruangan' => [...$this->ruanganRules($request)],
            'username' => ['nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($userId, 'id_user')],
            'password' => ['nullable', 'sometimes', 'string', 'min:8'],
            'id_role' => ['nullable', 'exists:role,id_role'],
        ]);
    }

    /**
     * Ruangan wajib berada pada lokasi kerja SKPD yang dipilih.
     * Ruang bersama (id_skpd NULL) tetap boleh dipakai semua SKPD.
     */
    private function ruanganRules(Request $request): array
    {
        return [
            'nullable',
            'exists:ruangan,id_ruangan',
            function (string $attribute, $value, \Closure $fail) use ($request) {
                if ($value === null || $value === '') {
                    return;
                }

                $ruangan = Ruangan::find($value);
                $skpdId = $request->input('id_skpd');

                if (! $ruangan) {
                    return;
                }

                // Ruang bersama (tanpa skpd) boleh dipakai siapa saja.
                if ($ruangan->id_skpd === null && $ruangan->id_lokasi !== null) {
                    return;
                }

                $lokasiSkpd = Lokasi::untukSkpd($skpdId !== null && $skpdId !== '' ? (int) $skpdId : null);

                if (! $lokasiSkpd) {
                    return;
                }

                if ((int) $ruangan->id_lokasi !== (int) $lokasiSkpd->id_lokasi) {
                    $fail('Ruangan yang dipilih tidak berada pada lokasi SKPD tersebut. Pilih ruang lain atau ganti SKPD.');
                }
            },
        ];
    }

    /**
     * Nama lokasi kerja pegawai, diturunkan dari skpd-nya (UPT -> kantor
     * UPT-nya; Sekretariat & Bidang -> kantor dinas).
     */
    private function namaLokasi(?Pegawai $pegawai): ?string
    {
        if (! $pegawai) {
            return null;
        }

        return Lokasi::untukSkpd($pegawai->id_skpd)?->nama_lokasi;
    }
}