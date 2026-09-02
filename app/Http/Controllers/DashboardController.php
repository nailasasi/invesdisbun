<?php

namespace App\Http\Controllers;

use App\Models\Aset;
use App\Models\KategoriAset;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the application dashboard.
     */
    public function index(): View
    {
        return view('dashboard.index', [
            'totalAset' => Aset::count(),
            'totalKategori' => KategoriAset::count(),
            'totalPengguna' => User::count(),
            'totalNilaiAset' => Aset::sum('nilai_perolehan'),
            'asetBaik' => Aset::where('kondisi', 'Baik')->count(),
            'asetRusakRingan' => Aset::where('kondisi', 'Rusak Ringan')->count(),
            'asetRusakBerat' => Aset::where('kondisi', 'Rusak Berat')->count(),
        ]);
    }
}
