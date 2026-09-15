<?php

namespace App\Http\Controllers;

use App\Models\Aset;
use App\Models\MutasiAset;
use App\Models\PemegangAset;
use App\Models\PenempatanAset;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LaporanBulananController extends Controller
{
    public function index(Request $request)
    {
        $bulan = $request->input('bulan', Carbon::now()->format('Y-m'));
        $startOfMonth = Carbon::parse($bulan)->startOfMonth();
        $endOfMonth = Carbon::parse($bulan)->endOfMonth();

        $asetPerPegawai = PemegangAset::with(['pegawai.skpd', 'aset.barang.kategori'])
            ->where('status', 'aktif')
            ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false))
            ->get()
            ->groupBy(fn ($item) => $item->pegawai->nama_pegawai ?? 'Tidak Diketahui');

        $asetPerRuangan = PenempatanAset::with(['ruangan.skpd', 'aset.barang.kategori'])
            ->where('status', 'aktif')
            ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false))
            ->get()
            ->groupBy(fn ($item) => $item->ruangan->nama_ruangan ?? 'Tidak Diketahui');

        $mutasiBulanIni = MutasiAset::with(['details.aset.barang', 'details.pegawaiLama', 'details.pegawaiBaru', 'details.ruanganLama', 'details.ruanganBaru'])
            ->whereHas('details.aset', fn ($a) => $a->where('is_kendaraan', false))
            ->whereBetween('tanggal_mutasi', [$startOfMonth, $endOfMonth])
            ->latest('tanggal_mutasi')
            ->get();

        $totalAset = Aset::barang()->count();
        $totalAsetPegawai = PemegangAset::where('status', 'aktif')
            ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false))
            ->count();
        $totalAsetRuangan = PenempatanAset::where('status', 'aktif')
            ->whereHas('aset', fn ($a) => $a->where('is_kendaraan', false))
            ->count();
        $totalMutasi = $mutasiBulanIni->count();

        return view('laporan.laporan_bulanan', compact(
            'asetPerPegawai',
            'asetPerRuangan',
            'mutasiBulanIni',
            'totalAset',
            'totalAsetPegawai',
            'totalAsetRuangan',
            'totalMutasi',
            'bulan',
        ));
    }
}
