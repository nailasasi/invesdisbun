<?php

namespace App\Http\Controllers;

use App\Models\MutasiAset;
use Illuminate\Http\Request;

class MutasiAsetController extends Controller
{
    /**
     * Riwayat mutasi aset (read-only) untuk Admin Aset.
     */
    public function index(Request $request)
    {
        $search = $request->query('search');

        $mutasiList = MutasiAset::with(['details.aset.barang', 'details.pegawaiLama', 'details.pegawaiBaru', 'details.ruanganLama', 'details.ruanganBaru', 'userPenginput.pegawai'])
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('jenis_mutasi', 'like', "%{$search}%")
                        ->orWhere('keterangan', 'like', "%{$search}%")
                        ->orWhereHas('details.aset.barang', fn ($b) => $b->where('nama_barang', 'like', "%{$search}%"))
                        ->orWhereHas('details.pegawaiBaru', fn ($p) => $p->where('nama_pegawai', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('id_mutasi')
            ->paginate(10)
            ->withQueryString();

        return view('mutasi-aset.index', compact('mutasiList'));
    }
}
