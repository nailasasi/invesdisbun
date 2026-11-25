<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\DetailMutasiAset;
use App\Models\Lokasi;
use App\Models\MasterBarang;
use App\Models\MutasiAset;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\PenempatanAset;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Skpd;
use App\Models\TemplateDokumen;
use App\Models\User;
use App\Services\AsetScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uji menyeluruh Hak Akses & Integritas Modul Aset Barang berdasarkan Role:
 * - Admin Aset: Akses global, CUD, mutasi, penempatan, finalisasi, QR & cetak label, SPPBI Word, template & import.
 * - Admin Bidang: Melihat aset di kantor Dinas (termasuk lintas Bidang sekantor), dilarang mengelola & cetak label (403).
 * - Admin UPT: Mengelola aset dalam UPT sendiri, cetak label UPT, terisolasi dari UPT lain & Dinas (403), dilarang SPPBI & import (403).
 * - Pegawai: Membaca aset sekantor, aset milik sendiri di urutan teratas saat pembukaan awal, dilarang mengelola & cetak label (403).
 * - Keamanan Mutasi: Validasi ruangan/pemegang sama sebelum mutasi, pembungkusan transaksi DB.
 */
class AsetBarangHakAksesTest extends TestCase
{
    protected array $filesDibuatSementara = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabase(),
        ]);

        DB::beginTransaction();

        $this->pastikanBerkasTemplateTersedia();
    }

    protected function tearDown(): void
    {
        DB::rollBack();

        foreach ($this->filesDibuatSementara as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    private function pastikanBerkasTemplateTersedia(): void
    {
        $sampleDocx = storage_path('app/private/templates/kir_20261119_154822_d125383b.docx');

        foreach (['label', 'sppbi'] as $kode) {
            $tpl = TemplateDokumen::where('kode_template', $kode)->first();
            if ($tpl && $tpl->file_path && ! $tpl->fileExists()) {
                $targetPath = Storage::disk(TemplateDokumen::DISK)->path($tpl->file_path);
                $dir = dirname($targetPath);
                if (! is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                }

                if ($tpl->tipe_berkas === 'excel') {
                    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                    $sheet = $spreadsheet->getActiveSheet();
                    $sheet->setTitle('Template');
                    for ($r = 1; $r <= 25; $r++) {
                        for ($c = 1; $c <= 10; $c++) {
                            $sheet->setCellValue([$c, $r], "Dummy cell data {$c}-{$r}");
                        }
                    }
                    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                    $writer->save($targetPath);
                    $this->filesDibuatSementara[] = $targetPath;
                } elseif (file_exists($sampleDocx)) {
                    copy($sampleDocx, $targetPath);
                    $this->filesDibuatSementara[] = $targetPath;
                }
            }
        }
    }

    private function namaDatabase(): string
    {
        return $this->namaDatabasePengujian();
    }

    private function loginSebagai(User $user): void
    {
        app()->forgetInstance(AsetScope::class);
        $this->actingAs($user);
    }

    // -------------------------------------------------------------------------
    // Factory Helpers
    // -------------------------------------------------------------------------

    private function ambilRole(string $nama): Role
    {
        return Role::firstOrCreate(
            ['nama_role' => $nama],
            ['deskripsi' => 'Role ' . $nama]
        );
    }

    private function buatUserDenganRole(string $namaRole, Skpd $skpd, ?Ruangan $ruangan = null): User
    {
        $role = $this->ambilRole($namaRole);

        $pegawai = Pegawai::create([
            'nama_pegawai' => $namaRole . ' Tester ' . uniqid(),
            'nip' => '199' . rand(10000000, 99999999),
            'id_skpd' => $skpd->id_skpd,
            'id_ruangan' => $ruangan?->id_ruangan,
            'jabatan' => 'Staff ' . $namaRole,
        ]);

        return User::create([
            'username' => strtolower(str_replace(' ', '_', $namaRole)) . '_' . uniqid(),
            'password' => bcrypt('password123'),
            'id_pegawai' => $pegawai->id_pegawai,
            'id_role' => $role->id_role,
            'status_user' => 'aktif',
        ]);
    }

    private function masterBarangTest(): MasterBarang
    {
        return MasterBarang::firstOrCreate(
            ['nama_barang' => 'Laptop Uji Hak Akses'],
            ['kode_barang' => 'TEST-HAK-AKSES-' . uniqid()]
        );
    }

    private function buatAset(Skpd $skpd, Ruangan $ruangan, ?Pegawai $pemegang = null, bool $finalized = true): Aset
    {
        $mb = $this->masterBarangTest();

        $aset = Aset::create([
            'id_master_barang' => $mb->id_master_barang,
            'id_skpd' => $skpd->id_skpd,
            'id_lokasi' => $ruangan->id_lokasi,
            'nomor_kartu_barang' => 'KIB-' . uniqid(),
            'merk' => 'Brand Test ' . uniqid(),
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'tanggal_pengadaan' => now()->toDateString(),
            'tanggal_habis_pakai' => now()->addYears(5)->toDateString(),
            'nilai_perolehan' => 15000000,
        ]);

        PenempatanAset::create([
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruangan->id_ruangan,
            'tanggal_penempatan' => now()->toDateString(),
            'status' => 'aktif',
            'is_finalized' => $finalized ? 1 : 0,
            'finalized_at' => $finalized ? now() : null,
        ]);

        if ($pemegang) {
            PemegangAset::create([
                'id_aset' => $aset->id_aset,
                'id_pegawai' => $pemegang->id_pegawai,
                'tanggal_mulai' => now()->toDateString(),
                'status' => 'aktif',
            ]);
        }

        return $aset;
    }

    // -------------------------------------------------------------------------
    // Test Cases: Admin Aset
    // -------------------------------------------------------------------------

    public function test_admin_aset_memiliki_akses_global_bisa_melihat_dan_mengelola_semua_aset(): void
    {
        $skpdDinas = Skpd::where('jenis_skpd', '!=', 'UPT')->first() ?? Skpd::create(['nama_skpd' => 'Dinas Perkebunan', 'jenis_skpd' => 'Sekretariat']);
        $lokasiDinas = Lokasi::dinas() ?? Lokasi::where('jenis_lokasi', 'Dinas')->first() ?? Lokasi::first();
        $ruanganDinas = Ruangan::where('id_lokasi', $lokasiDinas->id_lokasi)->first();

        $adminAset = $this->buatUserDenganRole('Admin Aset', $skpdDinas, $ruanganDinas);

        $skpdUpt = Skpd::where('jenis_skpd', 'UPT')->first();
        $ruanganUpt = Ruangan::where('id_skpd', $skpdUpt->id_skpd)->first();

        $asetDinas = $this->buatAset($skpdDinas, $ruanganDinas, $adminAset->pegawai);
        $asetUpt = $this->buatAset($skpdUpt, $ruanganUpt);

        $this->loginSebagai($adminAset);

        // Akses index & melihat aset Dinas dan UPT via pencarian
        $resDinas = $this->get(route('aset-barang.index', ['search' => $asetDinas->nomor_kartu_barang]));
        $resDinas->assertOk();
        $resDinas->assertSee($asetDinas->nomor_kartu_barang);

        $resUpt = $this->get(route('aset-barang.index', ['search' => $asetUpt->nomor_kartu_barang]));
        $resUpt->assertOk();
        $resUpt->assertSee($asetUpt->nomor_kartu_barang);

        // Akses detail kedua aset
        $this->get(route('aset-barang.aset.detail', $asetDinas->id_aset))->assertOk();
        $this->get(route('aset-barang.aset.detail', $asetUpt->id_aset))->assertOk();

        // Akses QR & single label
        $this->get(route('aset-barang.aset.qr', $asetDinas->id_aset))->assertOk();
        $this->get(route('aset-barang.cetak.label.single', $asetUpt->id_aset))->assertOk();

        // Akses SPPBI Word download (parameter adalah model Pegawai)
        $this->get(route('aset-barang.sppbi.download.word', $adminAset->pegawai->id_pegawai))->assertOk();

        // Akses template import
        $this->get(route('aset-barang.template'))->assertOk();

        // Tambah aset baru
        $storeRes = $this->postJson(route('aset-barang.store.flat'), [
            'nama_barang' => 'Monitor LED Baru',
            'nomor_kartu_barang' => 'KIB-ADMIN-' . uniqid(),
            'merk' => 'LG',
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'tanggal_pengadaan' => now()->toDateString(),
            'tanggal_habis_pakai' => now()->addYears(3)->toDateString(),
            'nilai_perolehan' => 2500000,
        ]);
        $storeRes->assertOk();
        $storeRes->assertJson(['success' => true]);
    }

    // -------------------------------------------------------------------------
    // Test Cases: Admin Bidang
    // -------------------------------------------------------------------------

    public function test_admin_bidang_dapat_melihat_aset_di_dinas_termasuk_lintas_bidang_sekantor_tetapi_tidak_upt(): void
    {
        $skpdBidangA = Skpd::where('jenis_skpd', 'Bidang')->first();
        $skpdBidangB = Skpd::where('jenis_skpd', 'Bidang')->where('id_skpd', '!=', $skpdBidangA->id_skpd)->first();
        if (! $skpdBidangB) {
            $skpdBidangB = Skpd::create(['nama_skpd' => 'Bidang Lain', 'jenis_skpd' => 'Bidang']);
        }

        $lokasiDinas = Lokasi::dinas() ?? Lokasi::where('jenis_lokasi', 'Dinas')->first();

        $ruanganBidangA = Ruangan::firstOrCreate(
            ['nama_ruangan' => 'Ruang Bidang A ' . uniqid()],
            ['id_skpd' => $skpdBidangA->id_skpd, 'id_lokasi' => $lokasiDinas->id_lokasi, 'status' => 'Aktif']
        );
        $ruanganBidangB = Ruangan::firstOrCreate(
            ['nama_ruangan' => 'Ruang Bidang B ' . uniqid()],
            ['id_skpd' => $skpdBidangB->id_skpd, 'id_lokasi' => $lokasiDinas->id_lokasi, 'status' => 'Aktif']
        );

        $skpdUpt = Skpd::where('jenis_skpd', 'UPT')->first();
        $ruanganUpt = Ruangan::where('id_skpd', $skpdUpt->id_skpd)->first();

        $asetBidangA = $this->buatAset($skpdBidangA, $ruanganBidangA);
        $asetBidangB = $this->buatAset($skpdBidangB, $ruanganBidangB);
        $asetUpt = $this->buatAset($skpdUpt, $ruanganUpt);

        $adminBidang = $this->buatUserDenganRole('Admin Bidang', $skpdBidangA, $ruanganBidangA);
        $this->loginSebagai($adminBidang);

        // Akses index dengan pencarian: aset Bidang A dan Bidang B terlihat (karena sama-sama di kantor Dinas)
        $resA = $this->get(route('aset-barang.index', ['search' => $asetBidangA->nomor_kartu_barang]));
        $resA->assertOk();
        $resA->assertSee($asetBidangA->nomor_kartu_barang);

        $resB = $this->get(route('aset-barang.index', ['search' => $asetBidangB->nomor_kartu_barang]));
        $resB->assertOk();
        $resB->assertSee($asetBidangB->nomor_kartu_barang);

        // Aset UPT TIDAK terlihat di index
        $resUpt = $this->get(route('aset-barang.index', ['search' => $asetUpt->nomor_kartu_barang]));
        $resUpt->assertOk();
        $this->assertCount(0, $resUpt->viewData('asetList'));

        // Detail aset di Dinas bisa dilihat
        $this->get(route('aset-barang.aset.detail', $asetBidangA->id_aset))->assertOk();
        $this->get(route('aset-barang.aset.detail', $asetBidangB->id_aset))->assertOk();

        // Detail aset UPT dilarang (403)
        $this->get(route('aset-barang.aset.detail', $asetUpt->id_aset))->assertForbidden();
    }

    public function test_admin_bidang_dilarang_mencetak_label_qr_dan_dilarang_mengelola_aset(): void
    {
        $skpdBidang = Skpd::where('jenis_skpd', 'Bidang')->first();
        $lokasiDinas = Lokasi::dinas() ?? Lokasi::where('jenis_lokasi', 'Dinas')->first();
        $ruangan = Ruangan::where('id_lokasi', $lokasiDinas->id_lokasi)->first();
        $aset = $this->buatAset($skpdBidang, $ruangan);

        $adminBidang = $this->buatUserDenganRole('Admin Bidang', $skpdBidang, $ruangan);
        $this->loginSebagai($adminBidang);

        // 403 pada QR & label
        $this->get(route('aset-barang.aset.qr', $aset->id_aset))->assertForbidden();
        $this->get(route('aset-barang.cetak.label.single', $aset->id_aset))->assertForbidden();
        $this->get(route('aset-barang.label.download', $adminBidang->pegawai->id_pegawai))->assertForbidden();

        // 403 pada SPPBI Word download
        $this->get(route('aset-barang.sppbi.download.word', $adminBidang->pegawai->id_pegawai))->assertForbidden();

        // 403 pada template & import
        $this->get(route('aset-barang.template'))->assertForbidden();

        // 403 pada CUD & mutasi
        $this->postJson(route('aset-barang.store.flat'), ['nama_barang' => 'Test'])->assertForbidden();
        $this->postJson(route('aset-barang.aset.update', $aset->id_aset), ['nama_barang' => 'Test'])->assertForbidden();
        $this->postJson(route('aset-barang.aset.mutasi', $aset->id_aset), ['tipe' => 'ruangan'])->assertForbidden();
        $this->postJson(route('aset-barang.aset.destroy', $aset->id_aset))->assertForbidden();
        $this->postJson(route('aset-barang.aset.penempatan-awal', $aset->id_aset))->assertForbidden();
        $this->postJson(route('aset-barang.aset.finalisasi-penempatan', $aset->id_aset))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Test Cases: Admin UPT (P2BTP & PSBP Isolation)
    // -------------------------------------------------------------------------

    public function test_admin_upt_dapat_mengelola_aset_dalam_upt_sendiri_dan_cetak_label(): void
    {
        $skpdP2btp = Skpd::where('jenis_skpd', 'UPT')->orderBy('id_skpd')->first();
        $ruanganP2btp = Ruangan::where('id_skpd', $skpdP2btp->id_skpd)->first();

        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $skpdP2btp, $ruanganP2btp);
        $asetP2btp = $this->buatAset($skpdP2btp, $ruanganP2btp, $adminP2btp->pegawai);

        $this->loginSebagai($adminP2btp);

        // Akses index & melihat aset UPT-nya
        $resIndex = $this->get(route('aset-barang.index', ['search' => $asetP2btp->nomor_kartu_barang]));
        $resIndex->assertOk();
        $resIndex->assertSee($asetP2btp->nomor_kartu_barang);

        // Akses detail, QR, dan cetak label satuan
        $this->get(route('aset-barang.aset.detail', $asetP2btp->id_aset))->assertOk();
        $this->get(route('aset-barang.aset.qr', $asetP2btp->id_aset))->assertOk();
        $this->get(route('aset-barang.cetak.label.single', $asetP2btp->id_aset))->assertOk();

        // Download label pegawai UPT-nya
        $this->get(route('aset-barang.label.download', $adminP2btp->pegawai->id_pegawai))->assertOk();

        // Tambah aset baru (otomatis terikat ke UPT P2BTP)
        $kibBaru = 'KIB-P2BTP-' . uniqid();
        $storeRes = $this->postJson(route('aset-barang.store.flat'), [
            'nama_barang' => 'Mesin Pencacah Rumput',
            'nomor_kartu_barang' => $kibBaru,
            'merk' => 'Honda',
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'tanggal_pengadaan' => now()->toDateString(),
            'tanggal_habis_pakai' => now()->addYears(5)->toDateString(),
            'nilai_perolehan' => 8500000,
        ]);
        $storeRes->assertOk();
        $storeRes->assertJson(['success' => true]);

        // Cek data aset yang baru dibuat memiliki penempatan awal di UPT P2BTP
        $asetBaru = Aset::where('nomor_kartu_barang', $kibBaru)->first();
        $this->assertNotNull($asetBaru);
        $this->assertEquals($skpdP2btp->id_skpd, $asetBaru->id_skpd);

        // Edit aset
        $updateRes = $this->postJson(route('aset-barang.aset.update', $asetBaru->id_aset), [
            'nama_barang' => 'Mesin Pencacah Rumput V2',
            'nomor_kartu_barang' => $asetBaru->nomor_kartu_barang,
            'merk' => 'Yamaha',
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'tanggal_pengadaan' => now()->toDateString(),
            'tanggal_habis_pakai' => now()->addYears(5)->toDateString(),
            'nilai_perolehan' => 9000000,
        ]);
        $updateRes->assertOk();
        $updateRes->assertJson(['success' => true]);
    }

    public function test_admin_upt_terisolasi_dan_dilarang_mengakses_aset_upt_lain_atau_dinas(): void
    {
        $skpdsUpt = Skpd::where('jenis_skpd', 'UPT')->orderBy('id_skpd')->get();
        $skpdP2btp = $skpdsUpt[0];
        $skpdPsbp = $skpdsUpt[1] ?? Skpd::create(['nama_skpd' => 'UPT PSBP', 'jenis_skpd' => 'UPT']);

        $ruanganP2btp = Ruangan::where('id_skpd', $skpdP2btp->id_skpd)->first();
        $ruanganPsbp = Ruangan::where('id_skpd', $skpdPsbp->id_skpd)->first() ?? Ruangan::create([
            'nama_ruangan' => 'Ruang PSBP Test',
            'id_skpd' => $skpdPsbp->id_skpd,
            'status' => 'Aktif',
        ]);

        $skpdDinas = Skpd::where('jenis_skpd', '!=', 'UPT')->first();
        $lokasiDinas = Lokasi::dinas() ?? Lokasi::where('jenis_lokasi', 'Dinas')->first();
        $ruanganDinas = Ruangan::where('id_lokasi', $lokasiDinas->id_lokasi)->first();

        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $skpdP2btp, $ruanganP2btp);
        $pegawaiPsbp = Pegawai::create([
            'nama_pegawai' => 'Pegawai PSBP ' . uniqid(),
            'nip' => '199' . rand(10000000, 99999999),
            'id_skpd' => $skpdPsbp->id_skpd,
        ]);

        $asetPsbp = $this->buatAset($skpdPsbp, $ruanganPsbp, $pegawaiPsbp);
        $asetDinas = $this->buatAset($skpdDinas, $ruanganDinas);

        $this->loginSebagai($adminP2btp);

        // Index tidak memuat aset PSBP maupun Dinas
        $resIndexPsbp = $this->get(route('aset-barang.index', ['search' => $asetPsbp->nomor_kartu_barang]));
        $resIndexPsbp->assertOk();
        $this->assertCount(0, $resIndexPsbp->viewData('asetList'));

        $resIndexDinas = $this->get(route('aset-barang.index', ['search' => $asetDinas->nomor_kartu_barang]));
        $resIndexDinas->assertOk();
        $this->assertCount(0, $resIndexDinas->viewData('asetList'));

        // 403 saat mengakses detail aset PSBP & Dinas
        $this->get(route('aset-barang.aset.detail', $asetPsbp->id_aset))->assertForbidden();
        $this->get(route('aset-barang.aset.detail', $asetDinas->id_aset))->assertForbidden();

        // 403 saat mengakses QR & label aset PSBP & Dinas
        $this->get(route('aset-barang.aset.qr', $asetPsbp->id_aset))->assertForbidden();
        $this->get(route('aset-barang.cetak.label.single', $asetPsbp->id_aset))->assertForbidden();

        // 403 saat download label pegawai PSBP
        $this->get(route('aset-barang.label.download', $pegawaiPsbp->id_pegawai))->assertForbidden();

        // 403 saat mencoba update/mutasi aset PSBP
        $this->postJson(route('aset-barang.aset.update', $asetPsbp->id_aset), ['nama_barang' => 'Hacked'])->assertForbidden();
        $this->postJson(route('aset-barang.aset.mutasi', $asetPsbp->id_aset), ['tipe' => 'ruangan'])->assertForbidden();
        $this->postJson(route('aset-barang.aset.destroy', $asetPsbp->id_aset))->assertForbidden();

        // 403 pada SPPBI Word, template, import
        $this->get(route('aset-barang.sppbi.download.word', $pegawaiPsbp->id_pegawai))->assertForbidden();
        $this->get(route('aset-barang.template'))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Test Cases: Pegawai
    // -------------------------------------------------------------------------

    public function test_pegawai_melihat_aset_milik_sendiri_teratas_di_halaman_pertama(): void
    {
        $skpd = Skpd::where('jenis_skpd', 'Bidang')->first();
        $lokasiDinas = Lokasi::dinas() ?? Lokasi::where('jenis_lokasi', 'Dinas')->first();
        $ruangan = Ruangan::where('id_lokasi', $lokasiDinas->id_lokasi)->first();

        $pegawaiUser = $this->buatUserDenganRole('Pegawai', $skpd, $ruangan);

        $pegawaiLain = Pegawai::create([
            'nama_pegawai' => 'Rekan Kerja ' . uniqid(),
            'nip' => '199' . rand(10000000, 99999999),
            'id_skpd' => $skpd->id_skpd,
            'id_ruangan' => $ruangan->id_ruangan,
        ]);

        // Buat aset lain lebih dulu
        $this->buatAset($skpd, $ruangan, $pegawaiLain);
        $this->buatAset($skpd, $ruangan, $pegawaiLain);

        // Buat aset milik user pegawai ini
        $asetSaya = $this->buatAset($skpd, $ruangan, $pegawaiUser->pegawai);

        $this->loginSebagai($pegawaiUser);

        // Buka index tanpa parameter search
        $response = $this->get(route('aset-barang.index'));
        $response->assertOk();

        $asetList = $response->viewData('asetList');
        $this->assertNotEmpty($asetList);

        // Aset milik pegawai user harus berada di urutan teratas (elemen pertama)
        $firstItem = $asetList->first();
        $this->assertEquals(
            $asetSaya->id_aset,
            $firstItem->id_aset,
            'Aset milik pegawai login harus berada di urutan pertama saat index dibuka tanpa pencarian.'
        );
    }

    public function test_pegawai_dilarang_mencetak_label_qr_dan_dilarang_mengelola_aset(): void
    {
        $skpd = Skpd::where('jenis_skpd', 'Bidang')->first();
        $lokasiDinas = Lokasi::dinas() ?? Lokasi::where('jenis_lokasi', 'Dinas')->first();
        $ruangan = Ruangan::where('id_lokasi', $lokasiDinas->id_lokasi)->first();

        $pegawaiUser = $this->buatUserDenganRole('Pegawai', $skpd, $ruangan);
        $aset = $this->buatAset($skpd, $ruangan, $pegawaiUser->pegawai);

        $this->loginSebagai($pegawaiUser);

        // 403 pada QR & label
        $this->get(route('aset-barang.aset.qr', $aset->id_aset))->assertForbidden();
        $this->get(route('aset-barang.cetak.label.single', $aset->id_aset))->assertForbidden();
        $this->get(route('aset-barang.label.download', $pegawaiUser->pegawai->id_pegawai))->assertForbidden();

        // 403 pada SPPBI Word download
        $this->get(route('aset-barang.sppbi.download.word', $pegawaiUser->pegawai->id_pegawai))->assertForbidden();

        // 403 pada template & import
        $this->get(route('aset-barang.template'))->assertForbidden();

        // 403 pada CUD & mutasi
        $this->postJson(route('aset-barang.store.flat'), ['nama_barang' => 'Test'])->assertForbidden();
        $this->postJson(route('aset-barang.aset.update', $aset->id_aset), ['nama_barang' => 'Test'])->assertForbidden();
        $this->postJson(route('aset-barang.aset.mutasi', $aset->id_aset), ['tipe' => 'ruangan'])->assertForbidden();
        $this->postJson(route('aset-barang.aset.destroy', $aset->id_aset))->assertForbidden();
        $this->postJson(route('aset-barang.aset.penempatan-awal', $aset->id_aset))->assertForbidden();
        $this->postJson(route('aset-barang.aset.finalisasi-penempatan', $aset->id_aset))->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Test Cases: Keamanan Mutasi & Validasi Transaksi
    // -------------------------------------------------------------------------

    public function test_mutasi_menolak_ruangan_yang_sama_sebelum_mengubah_penempatan(): void
    {
        $skpd = Skpd::where('jenis_skpd', '!=', 'UPT')->first();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $skpd);
        $ruangan = Ruangan::where('id_skpd', $skpd->id_skpd)->first();

        $aset = $this->buatAset($skpd, $ruangan, null, true);

        $this->loginSebagai($adminAset);

        // Coba mutasikan ke ruangan yang persis sama
        $res = $this->postJson(route('aset-barang.aset.mutasi', $aset->id_aset), [
            'tipe' => 'ruangan',
            'id_ruangan' => $ruangan->id_ruangan,
            'keterangan' => 'Pindah ke ruangan sama',
        ]);

        $res->assertOk();
        $res->assertJson([
            'success' => true,
            'message' => 'Aset sudah berada di ruangan tersebut.',
        ]);

        // Pastikan tidak ada record penempatan baru yang dibuat
        $jumlahPenempatan = PenempatanAset::where('id_aset', $aset->id_aset)->count();
        $this->assertEquals(1, $jumlahPenempatan, 'Tidak boleh ada duplikasi penempatan saat mutasi ruangan ke ruangan yang sama.');
    }

    public function test_mutasi_menolak_pemegang_yang_sama(): void
    {
        $skpd = Skpd::where('jenis_skpd', '!=', 'UPT')->first();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $skpd);
        $ruangan = Ruangan::where('id_skpd', $skpd->id_skpd)->first();

        $pegawai = Pegawai::create([
            'nama_pegawai' => 'Pemegang Tetap ' . uniqid(),
            'nip' => '199' . rand(10000000, 99999999),
            'id_skpd' => $skpd->id_skpd,
            'id_ruangan' => $ruangan->id_ruangan,
        ]);

        $aset = $this->buatAset($skpd, $ruangan, $pegawai, true);

        $this->loginSebagai($adminAset);

        // Coba mutasikan ke pemegang yang sama
        $res = $this->postJson(route('aset-barang.aset.mutasi', $aset->id_aset), [
            'tipe' => 'pegawai',
            'id_pegawai' => $pegawai->id_pegawai,
            'keterangan' => 'Ganti pemegang ke orang sama',
        ]);

        $res->assertStatus(422);
        $res->assertJsonValidationErrors(['id_pegawai']);
        $this->assertStringContainsString('sudah dipegang oleh pegawai yang dipilih', $res->json('errors.id_pegawai.0'));

        // Pastikan tidak ada duplikasi pemegang
        $jumlahPemegang = PemegangAset::where('id_aset', $aset->id_aset)->count();
        $this->assertEquals(1, $jumlahPemegang);
    }

    public function test_mutasi_sukses_berjalan_dalam_transaksi_database_dan_menghasilkan_finalisasi_otomatis(): void
    {
        $skpd = Skpd::where('jenis_skpd', '!=', 'UPT')->first();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $skpd);

        $ruanganLama = Ruangan::where('id_skpd', $skpd->id_skpd)->first();
        $ruanganBaru = Ruangan::create([
            'nama_ruangan' => 'Ruang Baru Mutasi ' . uniqid(),
            'id_skpd' => $skpd->id_skpd,
            'id_lokasi' => $ruanganLama->id_lokasi,
            'status' => 'Aktif',
        ]);

        $aset = $this->buatAset($skpd, $ruanganLama, null, true);

        $this->loginSebagai($adminAset);

        // Eksekusi mutasi ruangan
        $res = $this->postJson(route('aset-barang.aset.mutasi', $aset->id_aset), [
            'tipe' => 'ruangan',
            'id_ruangan' => $ruanganBaru->id_ruangan,
            'keterangan' => 'Mutasi ruangan sukses',
        ]);

        $res->assertOk();
        $res->assertJson(['success' => true]);

        // Verifikasi penempatan lama nonaktif ('tidak aktif')
        $penempatanLama = PenempatanAset::where('id_aset', $aset->id_aset)
            ->where('id_ruangan', $ruanganLama->id_ruangan)
            ->first();
        $this->assertEquals('tidak aktif', $penempatanLama->status);

        // Verifikasi penempatan baru aktif dan otomatis is_finalized = 1
        $penempatanBaru = PenempatanAset::where('id_aset', $aset->id_aset)
            ->where('id_ruangan', $ruanganBaru->id_ruangan)
            ->where('status', 'aktif')
            ->first();
        $this->assertNotNull($penempatanBaru);
        $this->assertEquals(1, $penempatanBaru->is_finalized);
        $this->assertNotNull($penempatanBaru->finalized_at);

        // Verifikasi Mutasi tercatat
        $detailMutasi = DetailMutasiAset::where('id_aset', $aset->id_aset)->latest('id_detail')->first();
        $this->assertNotNull($detailMutasi);
        $this->assertEquals('Pindah Ruangan', $detailMutasi->mutasi->jenis_mutasi);
    }
}
