<?php

namespace App\Http\Controllers;

use App\Models\Aset;
use App\Models\PenempatanAset;
use App\Models\Ruangan;
use App\Models\Skpd;
use Illuminate\Http\Request;

class AsetRuanganController extends Controller
{
    /**
     * Apakah role saat ini adalah salah satu unit bidang/UPT (bukan Admin Aset).
     * Unit hanya boleh melihat data pada SKPD-nya sendiri.
     */
    private function isUnitUser(): bool
    {
        $role = auth()->user()?->role?->nama_role ?? '';
        return str_starts_with($role, 'Bidang') || str_starts_with($role, 'UPT');
    }

    /**
     * SKPD milik user (untuk filter unit). Null untuk Admin Aset.
     */
    private function userSkpdId(): ?int
    {
        return $this->isUnitUser() ? auth()->user()->pegawai?->id_skpd : null;
    }

    /**
     * Daftar ruangan + jumlah aset aktif di dalamnya.
     */
    public function index(Request $request)
    {
        $ruanganFilter = $request->query('ruangan');
        $userSkpd = $this->userSkpdId();

        $ruanganList = Ruangan::with('skpd')
            ->withCount([
                'penempatan as jumlah_aset' => fn ($q) => $q->where('status', 'aktif'),
            ])
            ->when($userSkpd, fn ($q) => $q->where(fn ($q2) => $q2->where('id_skpd', $userSkpd)->orWhereNull('id_skpd')))
            ->when(!$userSkpd && $ruanganFilter, fn ($q) => $q->where('id_ruangan', $ruanganFilter))
            ->orderBy('id_ruangan')
            ->paginate(10)
            ->withQueryString();

        // Dropdown filter ruangan: daftar ruangan (Admin Aset), atau ruangan SKPD user + umum (unit).
        $ruanganOptions = Ruangan::when($userSkpd, fn ($q) => $q->where(fn ($q2) => $q2->where('id_skpd', $userSkpd)->orWhereNull('id_skpd')))
            ->orderBy('nama_ruangan')
            ->get(['id_ruangan', 'nama_ruangan']);

        // Dropdown SKPD untuk modal tambah/edit ruangan.
        $skpdOptions = Skpd::orderBy('nama_skpd')
            ->get(['id_skpd', 'nama_skpd']);

        $isAdminAset = auth()->user()?->role?->nama_role === 'Admin Aset';

        return view('aset-ruangan.index', compact(
            'ruanganList',
            'ruanganOptions',
            'skpdOptions',
            'isAdminAset',
            'userSkpd',
            'ruanganFilter',
        ));
    }

    /**
     * Detail aset yang berada di dalam satu ruangan.
     */
    public function show(Ruangan $ruangan)
    {
        $userSkpd = $this->userSkpdId();
        if ($userSkpd && $ruangan->id_skpd !== null && $ruangan->id_skpd !== $userSkpd) {
            abort(403, 'Anda tidak memiliki hak akses ke ruangan ini.');
        }

        $ruangan->load('skpd');

        $asetList = PenempatanAset::with(['aset.barang.kategori', 'aset.pemegangSaatIni.pegawai'])
            ->where('id_ruangan', $ruangan->id_ruangan)
            ->where('status', 'aktif')
            ->get()
            ->map(fn ($p) => $p->aset)
            ->filter();

        $isAdminAset = auth()->user()?->role?->nama_role === 'Admin Aset';

        return view('aset-ruangan.show', compact(
            'ruangan',
            'asetList',
            'isAdminAset',
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama_ruangan' => ['required', 'string', 'max:100'],
            'lantai' => ['nullable', 'string', 'max:20'],
            'id_skpd' => ['nullable', 'exists:skpd,id_skpd'],
        ]);

        Ruangan::create($data);

        return redirect()->route('aset-ruangan.index')
            ->with('success', 'Ruangan berhasil ditambahkan.');
    }

    public function update(Request $request, Ruangan $ruangan)
    {
        $data = $request->validate([
            'nama_ruangan' => ['required', 'string', 'max:100'],
            'lantai' => ['nullable', 'string', 'max:20'],
            'id_skpd' => ['nullable', 'exists:skpd,id_skpd'],
        ]);

        $ruangan->update($data);

        return redirect()->route('aset-ruangan.index')
            ->with('success', 'Ruangan berhasil diperbarui.');
    }

    public function destroy(Ruangan $ruangan)
    {
        $jumlahAsetAktif = PenempatanAset::where('id_ruangan', $ruangan->id_ruangan)
            ->where('status', 'aktif')
            ->count();

        if ($jumlahAsetAktif > 0) {
            return redirect()->route('aset-ruangan.index')
                ->with('error', "Ruangan ini masih memiliki {$jumlahAsetAktif} aset aktif. Pindahkan aset terlebih dahulu sebelum menghapus ruangan.");
        }

        $ruangan->delete();

        return redirect()->route('aset-ruangan.index')
            ->with('success', 'Ruangan berhasil dihapus.');
    }

    /**
     * Keluarkan aset dari ruangan (nonaktifkan penempatan aktifnya).
     */
    public function detachAset(Ruangan $ruangan, Aset $aset)
    {
        // Aset yang sedang dipegang pegawai tidak boleh dikeluarkan manual,
        // karena ruangannya selalu mengikuti ruangan pegawai.
        if ($aset->pemegangSaatIni) {
            return redirect()->route('aset-ruangan.show', $ruangan->id_ruangan)
                ->with('error', 'Aset sedang dipegang pegawai, ruangan mengikuti pegawai dan tidak dapat dikeluarkan secara manual.');
        }

        PenempatanAset::where('id_aset', $aset->id_aset)
            ->where('status', 'aktif')
            ->update(['status' => 'tidak aktif', 'tanggal_selesai' => now()->toDateString()]);

        return redirect()->route('aset-ruangan.show', $ruangan->id_ruangan)
            ->with('success', 'Aset dikeluarkan dari ruangan.');
    }
}
