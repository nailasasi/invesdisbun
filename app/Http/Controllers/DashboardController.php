<?php

namespace App\Http\Controllers;

use App\Models\Aset;
use App\Models\IzinKendaraan;
use App\Models\Kendaraan;
use App\Models\MutasiAset;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\Tanah;
use App\Models\UsulanRkbmd;
use App\Services\AsetScope;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly AsetScope $scope)
    {
    }

    const ROLE_ADMIN_ASET = 'Admin Aset';

    const ROLE_ADMIN_UPT = 'Admin UPT';

    const ROLE_PEGAWAI = 'Pegawai';

    /**
     * Display the application dashboard.
     */
    public function index(): View
    {
        $role = auth()->user()?->role?->nama_role;

        return match ($role) {
            self::ROLE_ADMIN_ASET => $this->adminAsetDashboard(),
            self::ROLE_PEGAWAI => $this->pegawaiDashboard(),
            default => $this->unitDashboard(),
        };
    }

    /**
     * Dashboard Admin Aset: statistik seluruh dinas +
     * peringatan SPPBI, pajak kendaraan, dan RKBMD pending.
     */
    private function adminAsetDashboard(): View
    {
        $totalAset = Aset::whereIn('status_aset', ['aktif', 'Aktif'])->count();
        $totalNilai = Aset::whereIn('status_aset', ['aktif', 'Aktif'])->sum('nilai_perolehan');

        $kendaraan = Kendaraan::query()
            ->selectRaw(
                "SUM(CASE WHEN status_penggunaan = 'Tersedia' THEN 1 ELSE 0 END) AS tersedia,
                 SUM(CASE WHEN status_penggunaan = 'Dipakai' THEN 1 ELSE 0 END) AS dipakai"
            )
            ->first();

        $asetBaik = Aset::where('kondisi', 'Baik')->whereIn('status_aset', ['aktif', 'Aktif'])->count();
        $asetRusakRingan = Aset::where('kondisi', 'Rusak Ringan')->whereIn('status_aset', ['aktif', 'Aktif'])->count();
        $asetRusakBerat = Aset::where('kondisi', 'Rusak Berat')->whereIn('status_aset', ['aktif', 'Aktif'])->count();

        $izinMenungguCount = IzinKendaraan::where('status_approval', 'Menunggu')->count();
        $izinMenungguTerbaru = IzinKendaraan::where('status_approval', 'Menunggu')
            ->with(['kendaraan.aset.barang', 'kendaraan.platAktif', 'pengaju'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $pegawaiTanpaSppbi = Pegawai::query()
            ->whereHas('pemegangAset', fn ($q) => $q->where('status', 'aktif'))
            ->whereDoesntHave('dokumenSppbi', fn ($q) => $q->where('status', 'aktif')->whereNotNull('file_path'))
            ->with('skpd')
            ->withCount(['pemegangAset as jumlah_aset' => fn ($q) => $q->where('status', 'aktif')])
            ->orderBy('nama_pegawai')
            ->get();

        $kendaraanPajakCount = Kendaraan::whereHas('pajakAktif', fn ($q) => $q->where('tanggal_berakhir', '<=', now()->addDays(30)))->count();

        $kendaraanPajak = Kendaraan::with(['aset.barang', 'platAktif', 'pajakAktif'])
            ->whereHas('pajakAktif', fn ($q) => $q->where('tanggal_berakhir', '<=', now()->addDays(30)))
            ->get()
            ->sortBy(fn (Kendaraan $k) => $k->pajakAktif?->tanggal_berakhir?->timestamp ?? PHP_INT_MAX)
            ->take(5)
            ->values();

        $rkbmdPendingCount = UsulanRkbmd::where('status_usulan', 'Diajukan')->count();
        $rkbmdPending = UsulanRkbmd::with(['skpd', 'pegawai'])
            ->where('status_usulan', 'Diajukan')
            ->orderByDesc('id_usulan')
            ->limit(5)
            ->get();

        $totalPemegang = PemegangAset::where('status', 'aktif')->distinct()->count('id_pegawai');

        $aktivitasTerbaru = MutasiAset::with(['userPenginput'])
            ->withCount('details')
            ->orderByDesc('id_mutasi')
            ->limit(6)
            ->get();

        return view('dashboard.index', [
            'role' => self::ROLE_ADMIN_ASET,
            'totalAset' => $totalAset,
            'totalNilai' => $totalNilai,
            'kendaraanTersedia' => (int) ($kendaraan->tersedia ?? 0),
            'kendaraanDipakai' => (int) ($kendaraan->dipakai ?? 0),
            'tanahKibA' => Tanah::count(),
            'asetBaik' => $asetBaik,
            'asetRusakRingan' => $asetRusakRingan,
            'asetRusakBerat' => $asetRusakBerat,
            'izinMenungguCount' => $izinMenungguCount,
            'izinMenungguTerbaru' => $izinMenungguTerbaru,
            'pegawaiTanpaSppbi' => $pegawaiTanpaSppbi,
            'totalPemegang' => $totalPemegang,
            'kendaraanPajakCount' => $kendaraanPajakCount,
            'kendaraanPajak' => $kendaraanPajak,
            'rkbmdPendingCount' => $rkbmdPendingCount,
            'rkbmdPending' => $rkbmdPending,
            'aktivitasTerbaru' => $aktivitasTerbaru,
        ]);
    }

    /**
     * Dashboard Admin Unit / Bidang / UPT: aset di lingkungan unit
     * (via pemegang aset aktif pegawai unit) + status RKBMD unit.
     */
    private function unitDashboard(): View
    {
        // Unit = skpd pegawai (Sekretariat / Bidang / UPT).
        $skpd = auth()->user()?->pegawai?->skpd;

        if (! $skpd) {
            return view('dashboard.index', [
                'role' => 'unit',
                'unit' => null,
            ]);
        }

        // Cakupan aset ditentukan LOKASI FISIK, bukan pemegang aset. Aset
        // ber-pemegang yang ditaruh di lokasi lain ikut terhitung di lokasi
        // tempatnya berada.
        $asetUnit = Aset::with(['barang', 'kendaraan', 'lokasi', 'skpd'])
            ->whereIn('status_aset', ['aktif', 'Aktif'])
            ->tap(fn ($q) => $this->scope->terapkan($q));

        $idAsetUnit = (clone $asetUnit)->pluck('id_aset')->unique();

        $pemegangUnit = PemegangAset::with(['pegawai', 'aset.barang', 'aset.kendaraan'])
            ->where('status', 'aktif')
            ->whereIn('id_aset', $idAsetUnit)
            ->get();

        $asetBaikUnit = (clone $asetUnit)->where('kondisi', 'Baik')->count();
        $asetRusakRinganUnit = (clone $asetUnit)->where('kondisi', 'Rusak Ringan')->count();
        $asetRusakBeratUnit = (clone $asetUnit)->where('kondisi', 'Rusak Berat')->count();

        $kendaraanUnitTersedia = Kendaraan::where('status_penggunaan', 'Tersedia')->whereIn('id_aset', $idAsetUnit)->count();
        $kendaraanUnitDipakai = Kendaraan::where('status_penggunaan', 'Dipakai')->whereIn('id_aset', $idAsetUnit)->count();

        $rkbmdUnit = UsulanRkbmd::with(['pegawai'])
            ->whereHas('pegawai', fn ($q) => $q->where('id_skpd', $skpd->id_skpd))
            ->orderByDesc('id_usulan')
            ->get();
        $rkbmdUnitStatuses = $rkbmdUnit->groupBy('status_usulan')->map->count();
        $rkbmdUnitPending = $rkbmdUnit->where('status_usulan', 'Diajukan')->values();

        return view('dashboard.index', [
            'role' => 'unit',
            'unit' => $skpd,
            'pemegangUnit' => $pemegangUnit,
            'totalAsetUnit' => $idAsetUnit->count(),
            'nilaiAsetUnit' => $asetUnit->sum('nilai_perolehan'),
            'asetBaikUnit' => $asetBaikUnit,
            'asetRusakRinganUnit' => $asetRusakRinganUnit,
            'asetRusakBeratUnit' => $asetRusakBeratUnit,
            'kendaraanUnitTersedia' => $kendaraanUnitTersedia,
            'kendaraanUnitDipakai' => $kendaraanUnitDipakai,
            'rkbmdUnitStatuses' => $rkbmdUnitStatuses,
            'rkbmdUnitPending' => $rkbmdUnitPending,
        ]);
    }

    /**
     * Dashboard Pegawai: aset yang dipegang sendiri, status SPPBI,
     * dan status pengajuan izin kendaraan miliknya.
     */
    private function pegawaiDashboard(): View
    {
        $diri = auth()->user()?->pegawai;

        $asetSaya = collect();
        $sppbiSaya = null;

        if ($diri) {
            $asetSaya = PemegangAset::with(['aset.barang', 'aset.kendaraan.platAktif'])
                ->where('id_pegawai', $diri->id_pegawai)
                ->where('status', 'aktif')
                ->get();
            $sppbiSaya = $diri->dokumenSppbi;
        }

        $izinTracking = IzinKendaraan::where('id_pegawai_pengaju', $diri?->id_pegawai)
            ->whereIn('status_approval', ['Menunggu', 'Disetujui', 'Ditolak'])
            ->whereNull('dismissed_at')
            ->with(['kendaraan.aset.barang', 'kendaraan.platAktif'])
            ->orderByRaw("CASE status_approval WHEN 'Menunggu' THEN 0 WHEN 'Disetujui' THEN 1 WHEN 'Ditolak' THEN 2 ELSE 3 END")
            ->orderByDesc('id_izin')
            ->limit(5)
            ->get();

        return view('dashboard.index', [
            'role' => self::ROLE_PEGAWAI,
            'diri' => $diri,
            'asetSaya' => $asetSaya,
            'sppbiSaya' => $sppbiSaya,
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
