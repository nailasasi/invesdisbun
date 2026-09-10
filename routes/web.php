<?php

use App\Http\Controllers\AsetBarangController;
use App\Http\Controllers\AsetRuanganController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LaporanBulananController;
use App\Http\Controllers\MutasiAsetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TanahController;
use App\Http\Controllers\RetribusiTanahController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DokumenPbbTanahController;
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

    // Aset Barang (semua role lihat; tambah/edit/hapus khusus Admin Aset)
    Route::prefix('aset-barang')->name('aset-barang.')->group(function () {
        Route::get('', [AsetBarangController::class, 'index'])
            ->name('index');
        Route::get('aset/{aset}', [AsetBarangController::class, 'showAset'])
            ->name('aset.show');
        Route::get('{pegawai}', [AsetBarangController::class, 'show'])
            ->name('show');

        Route::middleware('role:Admin Aset')->group(function () {
        Route::post('store', [AsetBarangController::class, 'storeFlat'])
            ->name('store.flat');
        Route::get('aset/{aset}/detail', [AsetBarangController::class, 'detailAset'])
            ->name('aset.detail');
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

    // Aset Ruangan (semua role lihat; CRUD & penempatan khusus Admin Aset)
    Route::prefix('aset-ruangan')->name('aset-ruangan.')->group(function () {
        Route::get('', [AsetRuanganController::class, 'index'])
            ->name('index');
        Route::get('{ruangan}', [AsetRuanganController::class, 'show'])
            ->name('show');

        Route::middleware('role:Admin Aset')->group(function () {
            Route::post('', [AsetRuanganController::class, 'store'])
                ->name('store');
            Route::post('{ruangan}', [AsetRuanganController::class, 'update'])
                ->name('update');
            Route::delete('{ruangan}', [AsetRuanganController::class, 'destroy'])
                ->name('destroy');
            Route::delete('{ruangan}/aset/{aset}', [AsetRuanganController::class, 'detachAset'])
                ->name('aset.detach');
        });
    });

    // ==========================
    // TANAH
    // hanya Admin Aset & Admin UPT P2DP
    // ==========================

    Route::middleware('role:Admin Aset,Admin UPT P2DP')
    ->group(function(){


    Route::get('tanah', [TanahController::class,'index'])
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

    // retribusi tanah
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

    // Dokumen PBB
    Route::get(
        '/tanah/{tanah}/dokumen-pbb/create',
        [DokumenPbbTanahController::class,'create']
    )->name('tanah.dokumen.create');


    Route::post(
        '/tanah/{tanah}/dokumen-pbb',
        [DokumenPbbTanahController::class,'store']
    )->name('tanah.dokumen.store');


    Route::delete(
        '/dokumen-pbb/{dokumen}',
        [DokumenPbbTanahController::class,'destroy']
    )->name('tanah.dokumen.destroy');

    // User Management (khusus Admin Aset; ubah role juga diatur di sini)
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

    // Laporan
    Route::get('laporan/bulanan', [LaporanBulananController::class, 'index'])
        ->name('laporan.bulanan');

    // Riwayat Mutasi Aset (khusus Admin Aset; pembuatan mutasi via edit aset)
    Route::prefix('mutasi-aset')->name('mutasi-aset.')->middleware('role:Admin Aset')->group(function () {
        Route::get('', [MutasiAsetController::class, 'index'])
            ->name('index');
    });
});
