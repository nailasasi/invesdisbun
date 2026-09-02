<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\Role;
use App\Models\Skpd;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        return view('user.index', compact('pegawaiList', 'skpdList', 'roleList'));
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
        ]);

        $pegawai->user()->create([
            'username' => $data['username'] ?: $data['nip'],
            'password' => $data['password'] ?: $data['nip'],
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
        $data = $this->validateData($request, $pegawai);

        $pegawai->update([
            'nip' => $data['nip'],
            'nama_pegawai' => $data['nama_pegawai'],
            'jabatan' => $data['jabatan'] ?? null,
            'id_skpd' => $data['id_skpd'] ?? null,
        ]);

        if ($pegawai->user) {
            $userData = [
                'username' => $data['username'] ?: ($pegawai->user->username ?? ''),
                'id_role' => $data['id_role'],
            ];

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
            'username' => ['nullable', 'string', 'max:255', Rule::unique('users', 'username')->ignore($userId, 'id_user')],
            'password' => ['nullable', 'sometimes', 'string', 'min:8'],
            'id_role' => ['required', 'exists:role,id_role'],
        ]);
    }
}