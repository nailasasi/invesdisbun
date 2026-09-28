<?php

namespace App\Http\Controllers;

use App\Models\Aset;
use App\Models\IzinKendaraan;
use App\Models\KategoriAset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the application dashboard.
     */
    public function index(): View
    {
        $isAdminAset = auth()->user()?->role?->nama_role === 'Admin Aset';

        // Only Admin Aset needs the pending requests widget.
        $izinMenungguCount = 0;
        $izinMenungguTerbaru = collect();

        // Non-admin sees a status-tracking widget for their own requests,
        // prioritizing waiting / most recent approved submissions.
        $izinTracking = collect();

        if ($isAdminAset) {
            $izinMenungguCount = IzinKendaraan::where('status_approval', 'Menunggu')->count();
            $izinMenungguTerbaru = IzinKendaraan::where('status_approval', 'Menunggu')
                ->with(['kendaraan.aset.barang', 'kendaraan.platAktif', 'pengaju'])
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();
        } else {
            $izinTracking = IzinKendaraan::where('id_pegawai_pengaju', auth()->user()?->pegawai?->id_pegawai)
                ->whereIn('status_approval', ['Menunggu', 'Disetujui', 'Ditolak'])
                ->whereNull('dismissed_at')
                ->with(['kendaraan.aset.barang', 'kendaraan.platAktif'])
                ->orderByRaw("CASE status_approval WHEN 'Menunggu' THEN 0 WHEN 'Disetujui' THEN 1 WHEN 'Ditolak' THEN 2 ELSE 3 END")
                ->orderByDesc('id_izin')
                ->limit(5)
                ->get();
        }

        return view('dashboard.index', [
            'totalAset' => Aset::count(),
            'totalKategori' => KategoriAset::count(),
            'totalPengguna' => User::count(),
            'totalNilaiAset' => Aset::sum('nilai_perolehan'),
            'asetBaik' => Aset::where('kondisi', 'Baik')->count(),
            'asetRusakRingan' => Aset::where('kondisi', 'Rusak Ringan')->count(),
            'asetRusakBerat' => Aset::where('kondisi', 'Rusak Berat')->count(),
            'isAdminAset' => $isAdminAset,
            'izinMenungguCount' => $izinMenungguCount,
            'izinMenungguTerbaru' => $izinMenungguTerbaru,
            'izinTracking' => $izinTracking,
        ]);
    }

    /**
     * Dismiss a status-tracking card shown on the employee dashboard.
     *
     * Menandai pengajuan milik user yang login sebagai "dibaca/ditutup"
     * (dismissed_at) sehingga tidak muncul lagi pada widget tracking,
     * termasuk setelah halaman di-refresh.
     */
    public function dismissNotification(Request $request)
    {
        $data = $request->validate([
            'id_izin' => ['required', 'integer'],
        ]);

        $izin = IzinKendaraan::findOrFail((int) $data['id_izin']);
        abort_unless($izin->id_pegawai_pengaju === auth()->user()?->pegawai?->id_pegawai, 403);

        $izin->update(['dismissed_at' => now()]);

        return back();
    }
}
