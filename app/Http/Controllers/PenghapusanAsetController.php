<?php

namespace App\Http\Controllers;

use App\Models\Aset;
use App\Models\UsulanPenghapusan;
use Illuminate\Http\Request;

class PenghapusanAsetController extends Controller
{
    /**
     * Daftar antrean usulan penghapusan (status aset: diusulkan_hapus).
     * Aset tidak pernah di-hard delete; cukup diganti statusnya agar tetap
     * tersimpan untuk audit.
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));

        $usulanList = UsulanPenghapusan::with([
                'aset.barang',
                'aset.penempatanAktif.ruangan',
                'aset.pemegangSaatIni.pegawai',
            ])
            ->where('status_usulan', 'diajukan')
            ->when($search !== '', fn ($q) => $q->where(function ($query) use ($search) {
                $query->whereHas('aset.barang', fn ($b) => $b->where('nama_barang', 'like', "%{$search}%"))
                    ->orWhereHas('aset', fn ($a) => $a->where('nomor_kartu_barang', 'like', "%{$search}%"));
            }))
            ->orderByDesc('id_usulan_hapus')
            ->paginate(10)
            ->withQueryString();

        // Aset berstatus aktif yang belum punya usulan aktif, untuk dropdown modal.
        $asetAktifList = Aset::with('barang')
            ->where('status_aset', 'aktif')
            ->whereNotIn('id_aset', UsulanPenghapusan::where('status_usulan', 'diajukan')->pluck('id_aset'))
            ->orderBy('id_aset')
            ->get(['id_aset', 'id_barang', 'nomor_kartu_barang']);

        return view('penghapusan.index', compact('usulanList', 'asetAktifList'));
    }

    /**
     * Usulan penghapusan: aset masuk status "diusulkan_hapus" (keluar dari
     * daftar aktif), tanpa menghapus baris dari tabel aset.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'id_aset' => ['required', 'exists:aset,id_aset'],
            'alasan_penghapusan' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $aset = Aset::findOrFail($data['id_aset']);

        if ($aset->status_aset !== 'aktif') {
            return back()->with('error', 'Hanya aset berstatus aktif yang dapat diusulkan penghapusan.');
        }

        if (UsulanPenghapusan::where('id_aset', $aset->id_aset)->where('status_usulan', 'diajukan')->exists()) {
            return back()->with('error', 'Aset sudah memiliki usulan penghapusan yang aktif.');
        }

        $aset->update(['status_aset' => 'diusulkan_hapus']);

        UsulanPenghapusan::create([
            'id_aset' => $aset->id_aset,
            'id_pegawai_penghapus' => auth()->user()?->pegawai?->id_pegawai,
            'tanggal_usulan' => now()->toDateString(),
            'alasan_penghapusan' => $data['alasan_penghapusan'],
            'status_usulan' => 'diajukan',
            'keterangan' => $data['keterangan'] ?? null,
        ]);

        return back()->with('success', 'Aset berhasil diusulkan penghapusan.');
    }

    /**
     * Batalkan usulan (klik salah oleh staf): aset kembali aktif, baris usulan dihapus.
     */
    public function reaktifkan(UsulanPenghapusan $usulan)
    {
        if ($usulan->aset) {
            $usulan->aset->update(['status_aset' => 'aktif']);
        }

        $usulan->delete();

        return back()->with('success', 'Usulan dibatalkan dan aset dikembalikan ke status aktif.');
    }

    /**
     * Eksekusi final setelah SK turun: aset ditandai "dihapuskan" dan usulan
     * diarsipkan dengan tanggal SK. Data tetap utuh untuk rekap audit tahunan.
     */
    public function eksekusi(UsulanPenghapusan $usulan)
    {
        if ($usulan->aset) {
            $usulan->aset->update(['status_aset' => 'dihapuskan']);
        }

        $usulan->update([
            'status_usulan' => 'disetujui',
            'tanggal_sk' => now()->toDateString(),
        ]);

        return back()->with('success', 'Aset diarsipkan sebagai hapus. Data tetap tersimpan untuk rekap audit.');
    }
}