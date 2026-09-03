<?php

namespace App\Http\Controllers;

use App\Models\Aset;
use App\Models\MutasiAset;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Skpd;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    /**
     * Daftar pegawai beserta akun login & role (halaman User Management).
     */
    public function index(Request $request)
    {
        $search = $request->query('search');

        $pegawaiList = Pegawai::with('skpd', 'user.role')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama_pegawai', 'like', "%{$search}%")
                        ->orWhere('nip', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $skpdList = Skpd::orderBy('nama_skpd')->get(['id_skpd', 'nama_skpd']);
        $roleList = Role::orderBy('id_role')->get(['id_role', 'nama_role']);
        $ruanganList = Ruangan::with('skpd')->orderBy('nama_ruangan')->get(['id_ruangan', 'nama_ruangan', 'id_skpd']);

        return view('user.index', compact('pegawaiList', 'skpdList', 'roleList', 'ruanganList'));
    }

    /**
     * Data pegawai + akun (termasuk username & status) untuk modal edit.
     */
    public function show(Pegawai $pegawai)
    {
        $pegawai->load('skpd', 'user.role');

        return response()->json([
            'id_pegawai' => $pegawai->id_pegawai,
            'nip' => $pegawai->nip,
            'nama_pegawai' => $pegawai->nama_pegawai,
            'jabatan' => $pegawai->jabatan,
            'id_skpd' => $pegawai->id_skpd,
            'id_ruangan' => $pegawai->id_ruangan,
            'id_role' => $pegawai->user?->id_role,
            'username' => $pegawai->user?->username,
            'status_user' => $pegawai->user?->status_user,
        ]);
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

        return response()->json([
            'success' => true,
            'message' => 'User berhasil ditambahkan.',
        ]);
    }

    /**
     * Perbarui pegawai + akun (username bisa diubah, password opsional).
     */
    public function update(Request $request, Pegawai $pegawai)
    {
        $data = $this->validateUpdateData($request, $pegawai);

        $ruanganBaru = $data['id_ruangan'] ?? null;

        // Aset yang sedang dipegang pegawai mengikuti ruangan kerja pegawai.
        $asetPemegangIds = PemegangAset::where('id_pegawai', $pegawai->id_pegawai)
            ->where('status', 'aktif')
            ->pluck('id_aset');

        if ($ruanganBaru === null && $asetPemegangIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'id_ruangan' => "Tidak dapat mengosongkan ruangan: masih ada {$asetPemegangIds->count()} aset yang dipegang pegawai ini. Pindahkan pemegangnya terlebih dahulu.",
            ]);
        }

        $pegawai->update([
            'nip' => $data['nip'],
            'nama_pegawai' => $data['nama_pegawai'],
            'jabatan' => $data['jabatan'] ?? null,
            'id_skpd' => $data['id_skpd'] ?? null,
            'id_ruangan' => $data['id_ruangan'] ?? null,
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

            if (!empty($perluPindah)) {
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

            if (! empty($data['password'])) {
                $userData['password'] = $data['password'];
            }

            $pegawai->user->update($userData);
        }

        return response()->json([
            'success' => true,
            'message' => 'User berhasil diperbarui.',
        ]);
    }

    /**
     * Ubah role akun pegawai (aksi terpisah dari edit).
     */
    public function updateRole(Request $request, Pegawai $pegawai)
    {
        $data = $request->validate([
            'id_role' => ['required', 'exists:role,id_role'],
        ]);

        $pegawai->user?->update(['id_role' => $data['id_role']]);

        return response()->json([
            'success' => true,
            'message' => 'Role berhasil diperbarui.',
        ]);
    }

    /**
     * Hapus pegawai beserta akun loginnya.
     */
    public function destroy(Pegawai $pegawai)
    {
        if ($pegawai->user) {
            $pegawai->user->delete();
        }

        $pegawai->delete();

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dihapus.',
        ]);
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
            'id_ruangan' => ['nullable', 'exists:ruangan,id_ruangan'],
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
            'id_ruangan' => ['nullable', 'exists:ruangan,id_ruangan'],
            'username' => ['nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($userId, 'id_user')],
            'password' => ['nullable', 'sometimes', 'string', 'min:8'],
            'id_role' => ['nullable', 'exists:role,id_role'],
        ]);
    }
}