<?php

use App\Http\Controllers\AsetBarangController;
use App\Http\Controllers\AsetRuanganController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BuktiOtorisasiController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DokumenPbbTanahController;
use App\Http\Controllers\DokumenSppbiController;
use App\Http\Controllers\IzinKendaraanController;
use App\Http\Controllers\InventarisRuanganController;
use App\Http\Controllers\KendaraanController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\MonitoringAsetController;
use App\Http\Controllers\MutasiAsetController;
use App\Http\Controllers\PajakKendaraanController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\PenghapusanAsetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RetribusiTanahController;
use App\Http\Controllers\RkbmdController;
use App\Http\Controllers\SensusAsetController;
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

Route::middleware(['auth', 'prevent-back-history'])->group(function () {

    Route::get('dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');
    Route::post('dashboard/tutup-notifikasi', [DashboardController::class, 'dismissNotification'])
        ->name('dashboard.dismiss-notification');

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

        // Route statis import/template WAJIB sebelum route berparameter
        // {pegawai} (dan sebelum {aset}) di bawah, agar '/aset-barang/template'
        // tidak tertangkap sebagai {pegawai} = 'template'.
        Route::middleware('role:Admin Aset')->group(function () {
            Route::get('template', [AsetBarangController::class, 'downloadTemplate'])
                ->name('template');
            Route::post('import', [AsetBarangController::class, 'import'])
                ->name('import');
            Route::get('{pegawai}/sppbi/download-word', [DokumenSppbiController::class, 'downloadWord'])
                ->name('sppbi.download.word');
        });

        Route::get('{pegawai}', [AsetBarangController::class, 'show'])
            ->name('show');

        // Cetak label dan QR: Admin Aset & Admin UPT (cakupan diatur oleh AsetScope/backend).
        // Admin Bidang dan Pegawai DITOLAK (403).
        Route::middleware('role:Admin Aset,Admin UPT')->group(function () {
            Route::get('aset/{aset}/label', [AsetBarangController::class, 'cetakLabelSatuan'])
                ->name('cetak.label.single');
            Route::get('{pegawai}/label/download', [AsetBarangController::class, 'downloadSemuaLabel'])
                ->name('label.download');
            Route::get('aset/{aset}/qr', [AsetBarangController::class, 'qrLabel'])
                ->name('aset.qr');
        });

        // SEMUA role generic boleh MELIHAT detail aset. Pembatasan cakupan lokasi
        // ditegakkan di backend oleh App\Services\AsetScope — bukan dengan
        // menyembunyikan menu.
        Route::middleware('role:Admin Aset,Admin Bidang,Admin UPT,Pegawai')->group(function () {
            Route::get('aset/{aset}/detail', [AsetBarangController::class, 'detailAset'])
                ->name('aset.detail');
        });

        // CUD + mutasi: Admin Aset (global) dan Admin UPT (cakupan UPT).
        // Admin Bidang dan Pegawai sifatnya read-only.
        Route::middleware('role:Admin Aset,Admin UPT')->group(function () {
            Route::get('aset/{aset}', [AsetBarangController::class, 'showAset'])
                ->name('aset.show');
            Route::post('store', [AsetBarangController::class, 'storeFlat'])
                ->name('store.flat');
            // Penempatan awal: memasang MASTER aset yang sudah ada ke pegawai.
            // Tidak pernah membuat record `aset` baru (lihat pasangAsetKePegawai).
            Route::post('{pegawai}/aset/{aset}/pasang', [AsetBarangController::class, 'pasangAsetKePegawai'])
                ->name('aset.pasang.pegawai');
            Route::post('aset/{aset}', [AsetBarangController::class, 'update'])
                ->name('aset.update');
            Route::post('aset/{aset}/mutasi', [AsetBarangController::class, 'mutasi'])
                ->name('aset.mutasi');
            Route::post('aset/{aset}/penempatan-awal', [AsetBarangController::class, 'penempatanAwal'])
                ->name('aset.penempatan-awal');
            Route::post('aset/{aset}/finalisasi-penempatan', [AsetBarangController::class, 'finalisasiPenempatan'])
                ->name('aset.finalisasi-penempatan');
            // Verifikasi + finalisasi untuk penempatan LAMA (is_finalized NULL
            // / "Perlu Verifikasi"). Aksi eksplisit per aset — bukan backfill.
            Route::post('aset/{aset}/verifikasi-finalisasi', [AsetBarangController::class, 'verifikasiFinalisasiPenempatan'])
                ->name('aset.verifikasi-finalisasi');
            // Finalisasi massal: beberapa aset sekaligus (bukan satu aset per
            // request, jadi dipakai path terpisah yang tidak bentrok dengan
            // aset/{aset}).
            Route::post('finalisasi-penempatan-massal', [AsetBarangController::class, 'finalisasiPenempatanMassal'])
                ->name('aset.finalisasi-penempatan-massal');
            // Edit penempatan = memperbaiki data penempatan yang keliru.
            // BUKAN mutasi: tidak membuat mutasi_aset/detail_mutasi_aset,
            // BAST, maupun SPPBI.
            Route::post('aset/{aset}/edit-penempatan', [AsetBarangController::class, 'editPenempatan'])
                ->name('aset.edit-penempatan');
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
        Route::get('{ruangan}', [AsetRuanganController::class, 'show'])
            ->name('show');

        // Label Ruangan & KIR dapat diunduh oleh Admin Aset (seluruh cakupan global)
        // dan Admin UPT (khusus ruangan dalam cakupan UPT sendiri — dicek via authorizeRuangan).
        Route::middleware('role:Admin Aset,Admin UPT')->group(function () {
            Route::get('{ruangan}/label/download', [AsetRuanganController::class, 'downloadLabelRuangan'])
                ->name('label.download');
            Route::get('{ruangan}/kir/download', [AsetRuanganController::class, 'downloadKIR'])
                ->name('kir.download');
        });

        // Hanya Admin Aset & Admin UPT yang boleh mengelola master ruangan.
        Route::middleware('role:Admin Aset,Admin UPT')->group(function () {
            Route::post('', [AsetRuanganController::class, 'store'])
                ->name('store');
            Route::post('{ruangan}', [AsetRuanganController::class, 'update'])
                ->name('update');
            Route::post('{ruangan}/toggle-status', [AsetRuanganController::class, 'toggleStatus'])
                ->name('toggle-status');
            Route::delete('{ruangan}/aset/{aset}', [AsetRuanganController::class, 'detachAset'])
                ->name('aset.detach');

            // Mutasi pindah ruangan untuk aset melekat (Admin Aset & Admin UPT cakupan lokasinya).
            Route::post('{ruangan}/aset/{aset}/mutasi-ruangan', [AsetRuanganController::class, 'mutasiRuangan'])
                ->name('aset.mutasi-ruangan');

            // Inventaris lokal ruangan (barang non-aset). Admin Aset penuh;
            // Admin UPT terbatas cakupan lokasinya (dicek di controller).
            Route::post('{ruangan}/inventaris', [InventarisRuanganController::class, 'store'])
                ->name('inventaris.store');
            Route::put('{ruangan}/inventaris/{inventaris}', [InventarisRuanganController::class, 'update'])
                ->name('inventaris.update');
            Route::post('{ruangan}/inventaris/{inventaris}/pindah', [InventarisRuanganController::class, 'move'])
                ->name('inventaris.move');
            Route::post('{ruangan}/inventaris/{inventaris}/usulkan-penghapusan', [InventarisRuanganController::class, 'usulkanPenghapusan'])
                ->name('inventaris.usulkan-penghapusan');
            Route::delete('{ruangan}/inventaris/{inventaris}', [InventarisRuanganController::class, 'destroy'])
                ->name('inventaris.destroy');
            Route::get('{ruangan}/inventaris/{inventaris}/label', [InventarisRuanganController::class, 'cetakLabel'])
                ->name('inventaris.label');
        });

        // Hanya Admin Aset yang boleh memasang master aset ke ruangan.
        Route::middleware('role:Admin Aset')->group(function () {
            // Kandidat jenis barang (untuk dropdown modal Pasang Aset) & pasang
            // beberapa aset sejenis sekaligus.
            Route::get('{ruangan}/aset/pasang/kandidat', [AsetRuanganController::class, 'kandidatPasang'])
                ->name('aset.pasang.kandidat');
            Route::post('{ruangan}/aset/pasang-massal', [AsetRuanganController::class, 'pasangAsetMassal'])
                ->name('aset.pasang-massal');
            Route::post('{ruangan}/aset/{aset}/pasang', [AsetRuanganController::class, 'pasangAset'])
                ->name('aset.pasang');
        });
    });

    // ==========================================
    // TEMPLATE DOKUMEN (Eksklusif Admin Aset)
    // ==========================================
    Route::middleware('role:Admin Aset')->group(function () {
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
    // TANAH (Admin Aset & Admin UPT)
    // ==========================================
    Route::middleware('role:Admin Aset,Admin UPT')->group(function () {
        Route::get('tanah', [TanahController::class, 'index'])
            ->name('tanah.index');
        Route::get('tanah/create', [TanahController::class, 'create'])
            ->name('tanah.create');
        Route::post('tanah', [TanahController::class, 'store'])
            ->name('tanah.store');
        Route::get('tanah/template', [TanahController::class, 'downloadTemplate'])
            ->name('tanah.template');
        Route::post('tanah/import', [TanahController::class, 'import'])
            ->name('tanah.import');
        Route::get('tanah/export', [TanahController::class, 'export'])
            ->name('tanah.export');
        Route::get('tanah/{tanah}', [TanahController::class, 'show'])
            ->name('tanah.show');
        Route::get('tanah/{tanah}/edit', [TanahController::class, 'edit'])
            ->name('tanah.edit');
        Route::put('tanah/{tanah}', [TanahController::class, 'update'])
            ->name('tanah.update');

        // Bukti otorisasi (foto kamera) — hanya lewat route terautentikasi,
        // file tersimpan di disk privat.
        Route::get('bukti-otorisasi/{history}', [BuktiOtorisasiController::class, 'show'])
            ->name('bukti-otorisasi.show');
    });

    // Retribusi Tanah (hanya Admin Aset & Admin UPT)
    Route::middleware('role:Admin Aset,Admin UPT')->group(function () {
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

        // Dokumen PBB Tanah
        Route::post('/tanah/{tanah}/dokumen-pbb', [DokumenPbbTanahController::class, 'store'])
            ->name('tanah.dokumen.store');
        Route::delete('/dokumen-pbb/{dokumen}', [DokumenPbbTanahController::class, 'destroy'])
            ->name('tanah.dokumen.destroy');
    });

    // ==========================================
    // LAYANAN - SURAT IZIN KENDARAAN DINAS
    // ==========================================
    Route::prefix('layanan/izin-kendaraan')->name('layanan.izin-kendaraan.')->group(function () {
        Route::get('', [IzinKendaraanController::class, 'index'])
            ->name('index');
        Route::get('ketersediaan', [IzinKendaraanController::class, 'ketersediaan'])
            ->name('ketersediaan');
        Route::post('', [IzinKendaraanController::class, 'store'])
            ->name('store');
        Route::post('{izin}/status', [IzinKendaraanController::class, 'updateStatus'])
            ->name('status');
        Route::post('{izin}/batal', [IzinKendaraanController::class, 'batal'])
            ->name('batal');
        Route::post('{izin}/selesai', [IzinKendaraanController::class, 'returnKendaraan'])
            ->name('selesai');
        Route::get('{izin}/download', [IzinKendaraanController::class, 'downloadSurat'])
            ->name('download');
    });

    // ==========================================
    // MASTER DATA - DATA PEGAWAI
    // Data orang (NIP, nama, jabatan, SKPD, ruangan). Tanpa akun login.
    // Admin Aset: seluruh pegawai. Admin UPT: hanya pegawai UPT miliknya.
    // Admin Bidang & Pegawai: tidak punya akses (dicegah middleware role).
    // ==========================================
    Route::prefix('master-data/pegawai')->name('master-data.pegawai.')->middleware('role:Admin Aset,Admin UPT')->group(function () {
        Route::get('search', [PegawaiController::class, 'search'])
            ->name('search');
        Route::get('', [PegawaiController::class, 'index'])
            ->name('index');
        Route::get('create', [PegawaiController::class, 'create'])
            ->name('create');
        Route::post('', [PegawaiController::class, 'store'])
            ->name('store');
        // Route statis import/template WAJIB sebelum route berparameter
        // {pegawai} di bawah, agar '/master-data/pegawai/template'
        // tidak tertangkap sebagai {pegawai} = 'template'.
        Route::get('template', [PegawaiController::class, 'downloadTemplate'])
            ->name('template');
        Route::post('import', [PegawaiController::class, 'import'])
            ->name('import');
        Route::get('{pegawai}', [PegawaiController::class, 'show'])
            ->name('show');
        Route::get('{pegawai}/edit', [PegawaiController::class, 'edit'])
            ->name('edit');
        Route::put('{pegawai}', [PegawaiController::class, 'update'])
            ->name('update');
    });

    // ==========================================
    // USER MANAGEMENT (akun login milik pegawai yang sudah ada)
    // Eksklusif Admin Aset. Admin UPT, Admin Bidang, dan Pegawai tidak memiliki akses.
    // ==========================================
    Route::prefix('user')->name('user.')->middleware('role:Admin Aset')->group(function () {
        Route::get('', [UserController::class, 'index'])
            ->name('index');
        // Buat akun dari pegawai YANG SUDAH ADA (bukan membuat pegawai baru).
        Route::post('buat-akun/{pegawai}', [UserController::class, 'storeAccount'])
            ->name('akun.store');
        Route::get('{pegawai}', [UserController::class, 'show'])
            ->name('show');
        Route::post('{pegawai}', [UserController::class, 'update'])
            ->name('update');
        Route::post('{pegawai}/role', [UserController::class, 'updateRole'])
            ->name('role.update');
        Route::post('{pegawai}/status', [UserController::class, 'updateStatus'])
            ->name('status.update');
    });

    // ==========================================
    // MODUL LAPORAN EKSEKUTIF (Eksklusif Admin Aset)
    // ==========================================
    Route::middleware('role:Admin Aset')->group(function () {
        Route::get('laporan', [LaporanController::class, 'index'])
            ->name('laporan.index');

        Route::get('laporan/export/excel', [LaporanController::class, 'exportExcel'])
            ->name('laporan.export.excel');

        Route::get('laporan/export/pdf', [LaporanController::class, 'exportPdf'])
            ->name('laporan.export.pdf');

        // Kompatibilitas mundur dengan route laporan.bulanan lama
        Route::get('laporan/bulanan', [LaporanController::class, 'index'])
            ->name('laporan.bulanan');
    });

    // ==========================================
    // RIWAYAT MUTASI ASET
    // ==========================================
    Route::prefix('mutasi-aset')->name('mutasi-aset.')->group(function () {
        Route::get('', [MutasiAsetController::class, 'index'])
            ->middleware('role:Admin Aset,Admin UPT')
            ->name('index');

        Route::get('{id_mutasi}/bast', [MutasiAsetController::class, 'downloadBAST'])
            ->middleware('role:Admin Aset,Admin UPT')
            ->name('bast.download');
    });

    // ==========================================
    // USULAN PENGHAPUSAN ASET
    // ==========================================
    Route::prefix('penghapusan')->name('penghapusan.')->group(function () {
        // Akses Antrean, Pengajuan, dan Riwayat untuk Admin Aset dan Admin UPT
        Route::middleware('role:Admin Aset,Admin UPT')->group(function () {
            Route::get('', [PenghapusanAsetController::class, 'index'])
                ->name('index');
            Route::post('', [PenghapusanAsetController::class, 'store'])
                ->name('store');
            Route::get('riwayat', [PenghapusanAsetController::class, 'riwayat'])
                ->name('riwayat');
        });

        // Fitur khusus eksekusi permanen SK dan bukti otorisasi (Eksklusif Admin Aset)
        // Catatan: reaktifkan() tetap di Admin Aset sebelum dirombak pada Tahap 2
        Route::middleware('role:Admin Aset')->group(function () {
            Route::post('{usulan}/reaktifkan', [PenghapusanAsetController::class, 'reaktifkan'])
                ->name('reaktifkan');
            Route::delete('{usulan}/eksekusi', [PenghapusanAsetController::class, 'eksekusi'])
                ->name('eksekusi');
            Route::get('riwayat/{riwayat}/bukti', [PenghapusanAsetController::class, 'showBukti'])
                ->name('riwayat.bukti');
        });
    });

    // ==========================================
    // MONITORING ASET
    // ==========================================
    Route::prefix('monitoring-aset')->name('monitoring-aset.')->middleware('role:Admin Aset,Admin UPT')->group(function () {
        Route::get('', [MonitoringAsetController::class, 'index'])
            ->name('index');
        Route::get('export', [MonitoringAsetController::class, 'export'])
            ->name('export');
        Route::get('export-per-pengguna', [MonitoringAsetController::class, 'exportPerPengguna'])
            ->name('export-per-pengguna');

        Route::get('{aset}/sensus', [SensusAsetController::class, 'edit'])
            ->name('sensus.edit');
        Route::post('{aset}/sensus', [SensusAsetController::class, 'update'])
            ->name('sensus.update');
        Route::get('{aset}/sensus/riwayat', [SensusAsetController::class, 'riwayat'])
            ->name('sensus.riwayat');
    });

    // ==========================================
    // KENDARAAN
    // ==========================================
    Route::prefix('kendaraan')->name('kendaraan.')->group(function () {
        Route::get('', [KendaraanController::class, 'index'])
            ->name('index');

        // Route statis WAJIB didaftarkan SEBELUM route berparameter
        // {kendaraan}. Jika tidak, '/kendaraan/template' dicocokkan sebagai
        // parameter {kendaraan} bernilai 'template' — route model binding
        // gagal dan hasilnya 404.
        Route::middleware('role:Admin Aset')->group(function () {
            Route::get('template', [KendaraanController::class, 'downloadTemplate'])
                ->name('template');

            // Ekspor daftar kendaraan (.xlsx). Route statis ini WAJIB sebelum
            // route berparameter {kendaraan} di bawah, agar '/kendaraan/ekspor'
            // tidak tertangkap sebagai {kendaraan} = 'ekspor'.
            // GET -> mode semua/segera (tautan); POST -> mode terpilih (form ids[]).
            Route::match(['get', 'post'], 'ekspor', [KendaraanController::class, 'export'])
                ->name('export');
        });

        // Finalisasi massal (Admin Aset & Admin UPT): path statis WAJIB sebelum route
        // berparameter {kendaraan} di bawah, agar '/kendaraan/finalisasi-penempatan-massal'
        // tidak tertangkap sebagai {kendaraan} = 'finalisasi-penempatan-massal'.
        Route::middleware('role:Admin Aset,Admin UPT')->group(function () {
            Route::post('finalisasi-penempatan-massal', [KendaraanController::class, 'finalisasiPenempatanMassal'])
                ->name('finalisasi-penempatan-massal');
        });

        // Tindakan Penempatan & Finalisasi Kendaraan serta Unduh BAST (Admin Aset & Admin UPT)
        Route::middleware('role:Admin Aset,Admin UPT')->group(function () {
            Route::post('{kendaraan}/finalisasi-penempatan', [KendaraanController::class, 'finalisasiPenempatan'])
                ->name('finalisasi-penempatan');
            Route::post('{kendaraan}/verifikasi-finalisasi', [KendaraanController::class, 'verifikasiFinalisasiPenempatan'])
                ->name('verifikasi-finalisasi');
            Route::post('{kendaraan}/edit-pemegang-penempatan', [KendaraanController::class, 'editPemegangPenempatan'])
                ->name('edit-pemegang-penempatan');

            // Unduh Berita Acara Serah Terima (BAST) Kendaraan (Admin Aset global, Admin UPT dibatasi scope)
            Route::get('{kendaraan}/mutasi/{id_mutasi}/bast', [MutasiAsetController::class, 'downloadBASTKendaraan'])
                ->name('mutasi.bast.download');
            Route::get('mutasi/{id_mutasi}/bast', [MutasiAsetController::class, 'downloadBASTKendaraanById'])
                ->name('bast.download');
        });

        Route::middleware('role:Admin Aset')->group(function () {
            Route::get('{kendaraan}', [KendaraanController::class, 'show'])
                ->name('show');
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

            // Penempatan awal kendaraan
            Route::post('{kendaraan}/penempatan-awal', [KendaraanController::class, 'penempatanAwal'])
                ->name('penempatan-awal');

            Route::post('{kendaraan}/mutasi-pemegang', [KendaraanController::class, 'mutasiPemegang'])
                ->name('mutasi-pemegang.store');

            Route::get('{kendaraan}/mutasi/{id_mutasi}/sppkd', [MutasiAsetController::class, 'downloadSPPKD'])
                ->name('mutasi.sppkd.download');

            // Unduh dokumen mutasi kendaraan cukup dengan id_mutasi (dipakai modal sukses & menu Mutasi Aset).
            Route::get('mutasi/{id_mutasi}/sppkd', [MutasiAsetController::class, 'downloadSPPKDById'])
                ->name('sppkd.download');

            // SPPKD untuk pemegang yang sedang tercantum pada tabel utama Kendaraan.
            // Tidak memakai record mutasi; nama diambil dari `kendaraan.pemegang`
            // saat unduh diproses.
            Route::get('{kendaraan}/sppkd-aktif', [MutasiAsetController::class, 'downloadSPPKDPemegang'])
                ->name('sppkd-aktif.download');

            Route::post('{kendaraan}/pajak', [PajakKendaraanController::class, 'store'])
                ->name('pajak.store');
            Route::put('{kendaraan}/pajak/{pajak}', [PajakKendaraanController::class, 'update'])
                ->name('pajak.update');
            Route::get('{kendaraan}/pajak/{pajak}/bukti', [PajakKendaraanController::class, 'downloadBukti'])
                ->name('pajak.bukti');
            Route::get('{kendaraan}/pajak/{pajak}/preview', [PajakKendaraanController::class, 'previewBukti'])
                ->name('pajak.preview');
            Route::delete('{kendaraan}/pajak/{pajak}', [PajakKendaraanController::class, 'destroy'])
                ->name('pajak.destroy');
        });
    });

    // ==========================================
    // RKBMD (Rencana Kebutuhan Barang Milik Daerah)
    // Pegawai TIDAK punya akses (keputusan Fase 1B): pengusul = Admin Aset
    // (Pengurus Barang), Admin Bidang, Admin UPT — masing-masing usulan
    // tetap di-scope ke SKPD-nya oleh RkbmdController.
    // ==========================================
    Route::prefix('rkbmd')->name('rkbmd.')
        ->middleware('role:Admin Aset,Super Admin,Admin Bidang,Admin UPT')
        ->group(function () {
        Route::get('', [RkbmdController::class, 'index'])
            ->name('index');
        Route::get('master-barang/search', [RkbmdController::class, 'searchMasterBarang'])
            ->name('master.search');
        Route::get('ekspor', [RkbmdController::class, 'export'])
            ->name('export');
        Route::post('', [RkbmdController::class, 'store'])
            ->name('store');
        Route::get('{usulan}', [RkbmdController::class, 'show'])
            ->whereNumber('usulan')
            ->name('show');
        Route::patch('{usulan}', [RkbmdController::class, 'update'])
            ->whereNumber('usulan')
            ->name('update');
        Route::post('{usulan}/ajukan', [RkbmdController::class, 'submit'])
            ->whereNumber('usulan')
            ->name('submit');
        Route::post('{usulan}/keputusan', [RkbmdController::class, 'decide'])
            ->whereNumber('usulan')
            ->name('decide');
        Route::delete('{usulan}', [RkbmdController::class, 'destroy'])
            ->whereNumber('usulan')
            ->name('destroy');

        Route::post('arsip', [RkbmdController::class, 'storeArsip'])
            ->name('arsip.store');

        // Akses file arsip WAJIB lewat controller (otorisasi per SKPD).
        // Tidak ada URL langsung ke disk publik untuk arsip.
        Route::get('arsip/{arsip}/pratinjau', [RkbmdController::class, 'previewArsip'])
            ->name('arsip.preview');
        Route::get('arsip/{arsip}/unduh', [RkbmdController::class, 'downloadArsip'])
            ->name('arsip.download');

        Route::delete('arsip/{arsip}', [RkbmdController::class, 'destroyArsip'])
            ->name('arsip.destroy');
    });
});
