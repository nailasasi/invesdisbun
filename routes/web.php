<?php

use App\Http\Controllers\AsetBarangController;
use App\Http\Controllers\AsetRuanganController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DokumenPbbTanahController;
use App\Http\Controllers\DokumenSppbiController;
use App\Http\Controllers\IzinKendaraanController;
use App\Http\Controllers\KendaraanController;
use App\Http\Controllers\LaporanBulananController;
use App\Http\Controllers\MutasiAsetController;
use App\Http\Controllers\MonitoringAsetController;
use App\Http\Controllers\PenghapusanAsetController;
use App\Http\Controllers\PajakKendaraanController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RetribusiTanahController;
use App\Http\Controllers\TanahController;
use App\Http\Controllers\TemplateDokumenController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])
        ->name('login');
    Route::post('login', [LoginController::class, 'login'])
        ->middleware('throttle:5,1')
        ->name('login.attempt');
});

Route::middleware('auth')->group(function () {

    Route::get('dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::post('logout', [LoginController::class, 'logout'])
        ->name('logout');

    // Ganti password (akses semua role yang login)
    Route::get('profil/ganti-password', [ProfileController::class, 'showChangePassword'])
        ->name('profile.password');
    Route::post('profil/ganti-password', [ProfileController::class, 'changePassword'])
        ->name('profile.password.update');

    // ==========================================
    // ASET BARANG (Termasuk Detail Pegawai & SPPBI)
    // ==========================================
    Route::prefix('aset-barang')->name('aset-barang.')->group(function () {
        Route::get('', [AsetBarangController::class, 'index'])
            ->name('index');
        Route::get('aset/{aset}', [AsetBarangController::class, 'showAset'])
            ->name('aset.show');
        Route::get('{pegawai}', [AsetBarangController::class, 'show'])
            ->name('show');
        Route::get('{pegawai}/label/download', [AsetBarangController::class, 'downloadSemuaLabel'])
            ->name('label.download');
        Route::get('aset/{aset}/label', [AsetBarangController::class, 'cetakLabelSatuan'])
            ->name('cetak.label.single');

        // Dokumen SPPBI (Bisa diakses untuk cetak/unduh)
        Route::get('{pegawai}/sppbi/cetak', [DokumenSppbiController::class, 'print'])
            ->name('sppbi.print');
        Route::get('{pegawai}/sppbi/download-word', [DokumenSppbiController::class, 'downloadWord'])
            ->name('sppbi.download.word');

        // Khusus Admin Aset
        Route::middleware('role:Admin Aset')->group(function () {
            Route::post('{pegawai}/sppbi/update', [DokumenSppbiController::class, 'updateOrCreate'])
                ->name('sppbi.update');
            Route::post('store', [AsetBarangController::class, 'storeFlat'])
                ->name('store.flat');
            Route::get('aset/{aset}/detail', [AsetBarangController::class, 'detailAset'])
                ->name('aset.detail');
            Route::get('aset/{aset}/qr', [AsetBarangController::class, 'qrLabel'])
                ->name('aset.qr');
            Route::post('{pegawai}/aset', [AsetBarangController::class, 'store'])
                ->name('store');
            Route::post('aset/{aset}', [AsetBarangController::class, 'update'])
                ->name('aset.update');
            Route::post('aset/{aset}/mutasi', [AsetBarangController::class, 'mutasi'])
                ->name('aset.mutasi');
            Route::delete('aset/{aset}', [AsetBarangController::class, 'destroy'])
                ->name('aset.destroy');
        });
    });

    // ==========================================
    // ASET RUANGAN
    // ==========================================
    Route::prefix('aset-ruangan')->name('aset-ruangan.')->group(function () {
        Route::get('', [AsetRuanganController::class, 'index'])
            ->name('index');
        Route::get('{ruangan}/label/download', [AsetRuanganController::class, 'downloadLabelRuangan'])
            ->name('label.download');
        Route::get('{ruangan}/kir/download', [AsetRuanganController::class, 'downloadKIR'])
            ->name('kir.download');
        Route::get('{ruangan}', [AsetRuanganController::class, 'show'])
            ->name('show');

        Route::middleware('role:Admin Aset')->group(function () {
            Route::post('', [AsetRuanganController::class, 'store'])
                ->name('store');
            Route::post('{ruangan}', [AsetRuanganController::class, 'update'])
                ->name('update');
            Route::post('{ruangan}/toggle-status', [AsetRuanganController::class, 'toggleStatus'])
                ->name('toggle-status');
            Route::delete('{ruangan}/aset/{aset}', [AsetRuanganController::class, 'detachAset'])
                ->name('aset.detach');
        });
    });

    // ==========================================
    // TEMPLATE DOKUMEN (Admin Aset & Admin UPT)
    // ==========================================
    Route::middleware('role:Admin Aset,Admin UPT P2DP,Admin UPT PSBP')->group(function () {
        Route::get('template-dokumen', [TemplateDokumenController::class, 'index'])
            ->name('template-dokumen.index');
        Route::post('template-dokumen/{template}', [TemplateDokumenController::class, 'update'])
            ->name('template-dokumen.update');
        Route::get('template-dokumen/{template}/download', [TemplateDokumenController::class, 'download'])
            ->name('template-dokumen.download');
        Route::delete('template-dokumen/{template}', [TemplateDokumenController::class, 'destroy'])
            ->name('template-dokumen.destroy');
    });

    // ==========================================
    // TANAH (Admin Aset & Admin UPT P2DP)
    // ==========================================
    Route::middleware('role:Admin Aset,Admin UPT P2DP')->group(function () {
        Route::get('tanah', [TanahController::class, 'index'])
            ->name('tanah.index');
        Route::get('tanah/create', [TanahController::class, 'create'])
            ->name('tanah.create');
        Route::post('tanah', [TanahController::class, 'store'])
            ->name('tanah.store');
        Route::get('tanah/{tanah}', [TanahController::class, 'show'])
            ->name('tanah.show');
        Route::get('tanah/{tanah}/edit', [TanahController::class, 'edit'])
            ->name('tanah.edit');
        Route::put('tanah/{tanah}', [TanahController::class, 'update'])
            ->name('tanah.update');
    });

    // Retribusi Tanah
    Route::prefix('tanah/{tanah}/retribusi')->name('tanah.retribusi.')->group(function () {
        Route::get('/create', [RetribusiTanahController::class, 'create'])
            ->name('create');
        Route::post('/', [RetribusiTanahController::class, 'store'])
            ->name('store');
    });

    Route::get('/retribusi/{retribusi}/edit', [RetribusiTanahController::class, 'edit'])
        ->name('tanah.retribusi.edit');
    Route::put('/retribusi/{retribusi}', [RetribusiTanahController::class, 'update'])
        ->name('tanah.retribusi.update');
    Route::delete('/retribusi/{retribusi}', [RetribusiTanahController::class, 'destroy'])
        ->name('tanah.retribusi.destroy');

    // Dokumen PBB Tanah
    Route::get('/tanah/{tanah}/dokumen-pbb/create', [DokumenPbbTanahController::class, 'create'])
        ->name('tanah.dokumen.create');
    Route::post('/tanah/{tanah}/dokumen-pbb', [DokumenPbbTanahController::class, 'store'])
        ->name('tanah.dokumen.store');
    Route::delete('/dokumen-pbb/{dokumen}', [DokumenPbbTanahController::class, 'destroy'])
        ->name('tanah.dokumen.destroy');

    // ==========================================
    // USER MANAGEMENT
    // ==========================================
    Route::prefix('user')->name('user.')->middleware('role:Admin Aset')->group(function () {
        Route::get('', [UserController::class, 'index'])
            ->name('index');
        Route::post('', [UserController::class, 'store'])
            ->name('store');
        Route::get('{pegawai}', [UserController::class, 'show'])
            ->name('show');
        Route::post('{pegawai}', [UserController::class, 'update'])
            ->name('update');
        Route::post('{pegawai}/role', [UserController::class, 'updateRole'])
            ->name('role.update');
        Route::delete('{pegawai}', [UserController::class, 'destroy'])
            ->name('destroy');
    });

    // ==========================================
    // LAPORAN BULANAN
    // ==========================================
    Route::get('laporan/bulanan', [LaporanBulananController::class, 'index'])
        ->name('laporan.bulanan');

    // ==========================================
    // RIWAYAT MUTASI ASET
    // ==========================================
    Route::prefix('mutasi-aset')->name('mutasi-aset.')->middleware('role:Admin Aset')->group(function () {
        Route::get('', [MutasiAsetController::class, 'index'])
            ->name('index');

        Route::get('{id_mutasi}/bast', [MutasiAsetController::class, 'downloadBAST'])
            ->name('bast.download');
    });

    // ==========================================
    // USULAN PENGHAPUSAN ASET
    // ==========================================
    Route::prefix('penghapusan')->name('penghapusan.')->middleware('role:Admin Aset')->group(function () {
        Route::get('', [PenghapusanAsetController::class, 'index'])
            ->name('index');
        Route::post('', [PenghapusanAsetController::class, 'store'])
            ->name('store');
        Route::post('{usulan}/reaktifkan', [PenghapusanAsetController::class, 'reaktifkan'])
            ->name('reaktifkan');
        Route::delete('{usulan}/eksekusi', [PenghapusanAsetController::class, 'eksekusi'])
            ->name('eksekusi');
    });

    // ==========================================
    // MONITORING ASET
    // ==========================================
    Route::prefix('monitoring-aset')->name('monitoring-aset.')->middleware('role:Admin Aset')->group(function () {
        Route::get('', [MonitoringAsetController::class, 'index'])
            ->name('index');
        Route::get('export', [MonitoringAsetController::class, 'export'])
            ->name('export');
    });

    // ==========================================
    // KENDARAAN
    // ==========================================
    Route::prefix('kendaraan')->name('kendaraan.')->group(function () {
        Route::get('', [KendaraanController::class, 'index'])
            ->name('index');
        Route::get('{kendaraan}', [KendaraanController::class, 'show'])
            ->name('show');

        // Pengajuan izin pakai kendaraan
        Route::post('{kendaraan}/izin', [IzinKendaraanController::class, 'store'])
            ->name('izin.store');

        Route::middleware('role:Admin Aset')->group(function () {
            Route::post('import', [KendaraanController::class, 'import'])
                ->name('import');
            Route::get('create/data', [KendaraanController::class, 'create'])
                ->name('create');
            Route::post('', [KendaraanController::class, 'store'])
                ->name('store');
            Route::get('{kendaraan}/edit', [KendaraanController::class, 'edit'])
                ->name('edit');
            Route::post('{kendaraan}', [KendaraanController::class, 'update'])
                ->name('update');
            Route::delete('{kendaraan}', [KendaraanController::class, 'destroy'])
                ->name('destroy');

            Route::post('{kendaraan}/plat', [KendaraanController::class, 'storePlat'])
                ->name('plat.store');

            Route::post('{kendaraan}/mutasi-pemegang', [KendaraanController::class, 'mutasiPemegang'])
                ->name('mutasi-pemegang.store');

            Route::get('{kendaraan}/mutasi/{id_mutasi}/sppkd', [MutasiAsetController::class, 'downloadSPPKD'])
                ->name('mutasi.sppkd.download');
            Route::get('{kendaraan}/mutasi/{id_mutasi}/bast', [MutasiAsetController::class, 'downloadBASTKendaraan'])
                ->name('mutasi.bast.download');

            Route::post('{kendaraan}/pajak', [PajakKendaraanController::class, 'store'])
                ->name('pajak.store');
            Route::delete('{kendaraan}/pajak/{pajak}', [PajakKendaraanController::class, 'destroy'])
                ->name('pajak.destroy');

            Route::post('{kendaraan}/izin/{izin}', [IzinKendaraanController::class, 'approve'])
                ->name('izin.approve');
            Route::delete('{kendaraan}/izin/{izin}', [IzinKendaraanController::class, 'destroy'])
                ->name('izin.destroy');
        });
    });
});