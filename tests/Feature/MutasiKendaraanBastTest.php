<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\DetailMutasiAset;
use App\Models\Kendaraan;
use App\Models\Lokasi;
use App\Models\MasterBarang;
use App\Models\MutasiAset;
use App\Models\Pegawai;
use App\Models\Skpd;
use App\Models\TemplateDokumen;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MutasiKendaraanBastTest extends TestCase
{
    private User $adminAset;

    private User $adminUptP2btp;

    private User $adminUptPsbp;

    private User $adminBidang;

    private User $pegawai;

    private Skpd $skpdP2btp;

    private Skpd $skpdPsbp;

    private array $filesDibuatSementara = [];

    private MutasiAset $mutasiPsbp1;

    private MutasiAset $mutasiPsbp2;

    private MutasiAset $mutasiPsbp3;

    private MutasiAset $mutasiP2btp1;

    private MutasiAset $mutasiP2btp2;

    private MutasiAset $mutasiDinas1;

    private MutasiAset $mutasiDinas2;

    private MutasiAset $mutasiDinas3;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabase(),
        ]);

        DB::beginTransaction();

        $this->siapkanData();
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

    private function namaDatabase(): string
    {
        return $this->namaDatabasePengujian();
    }

    private function siapkanData(): void
    {
        $this->adminAset = User::whereHas('role', fn ($q) => $q->where('nama_role', 'Admin Aset'))->firstOrFail();

        $this->adminUptP2btp = User::whereHas('role', fn ($q) => $q->where('nama_role', 'Admin UPT'))
            ->whereHas('pegawai.skpd', fn ($q) => $q->where('nama_skpd', 'like', '%P2BTP%'))
            ->firstOrFail();

        $this->adminUptPsbp = User::whereHas('role', fn ($q) => $q->where('nama_role', 'Admin UPT'))
            ->whereHas('pegawai.skpd', fn ($q) => $q->where('nama_skpd', 'like', '%PSBP%'))
            ->firstOrFail();

        $this->adminBidang = $this->ambilAtauBuatUserRole('Admin Bidang');
        $this->pegawai = $this->ambilAtauBuatUserRole('Pegawai');

        $this->skpdP2btp = Skpd::where('nama_skpd', 'like', '%P2BTP%')->firstOrFail();
        $this->skpdPsbp = Skpd::where('nama_skpd', 'like', '%PSBP%')->firstOrFail();
        $skpdDinas = Skpd::where('nama_skpd', 'like', '%Sekretariat%')->firstOrFail();

        $lokasiPsbp = Lokasi::find(3) ?? Lokasi::where('nama_lokasi', 'like', '%PSBP%')->firstOrFail();
        $lokasiP2btp = Lokasi::find(2) ?? Lokasi::where('nama_lokasi', 'like', '%P2BTP%')->firstOrFail();
        $lokasiDinas = Lokasi::find(1) ?? Lokasi::where('nama_lokasi', 'like', '%Dinas%')->firstOrFail();

        $this->pastikanBerkasBastTersedia();

        $this->mutasiPsbp1 = $this->buatMutasiKendaraan($this->skpdPsbp, $lokasiPsbp, 'PSBP 1');
        $this->mutasiPsbp2 = $this->buatMutasiKendaraan($this->skpdPsbp, $lokasiPsbp, 'PSBP 2');
        $this->mutasiPsbp3 = $this->buatMutasiKendaraan($this->skpdPsbp, $lokasiPsbp, 'PSBP 3');

        $this->mutasiP2btp1 = $this->buatMutasiKendaraan($this->skpdP2btp, $lokasiP2btp, 'P2BTP 1');
        $this->mutasiP2btp2 = $this->buatMutasiKendaraan($this->skpdP2btp, $lokasiP2btp, 'P2BTP 2');

        $this->mutasiDinas1 = $this->buatMutasiKendaraan($skpdDinas, $lokasiDinas, 'Dinas 1');
        $this->mutasiDinas2 = $this->buatMutasiKendaraan($skpdDinas, $lokasiDinas, 'Dinas 2');
        $this->mutasiDinas3 = $this->buatMutasiKendaraan($skpdDinas, $lokasiDinas, 'Dinas 3');
    }

    private function pastikanBerkasBastTersedia(): void
    {
        $sampleDocx = storage_path('app/private/templates/kir_20261119_154822_d125383b.docx');

        foreach (['bast_kendaraan', 'bast_barang'] as $kode) {
            $tpl = TemplateDokumen::where('kode_template', $kode)->first();
            if ($tpl && $tpl->file_path && ! $tpl->fileExists()) {
                $targetPath = Storage::disk(TemplateDokumen::DISK)->path($tpl->file_path);
                $dir = dirname($targetPath);
                if (! is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                }
                if (file_exists($sampleDocx)) {
                    copy($sampleDocx, $targetPath);
                    $this->filesDibuatSementara[] = $targetPath;
                }
            }
        }
    }

    private function buatMutasiKendaraan(Skpd $skpd, Lokasi $lokasi, string $label): MutasiAset
    {
        $masterBarang = MasterBarang::first() ?? MasterBarang::create([
            'kode_barang' => 'TEST-' . uniqid(),
            'nama_barang' => 'Kendaraan Roda 4 ' . $label,
        ]);

        $pegawaiLama = Pegawai::create([
            'nip' => '9' . str_pad((string) random_int(10000000, 99999999), 8, '0') . substr(uniqid(), -8),
            'nama_pegawai' => 'Pegawai Lama ' . $label,
            'id_skpd' => $skpd->id_skpd,
            'jabatan' => 'Staff Lama ' . $skpd->nama_skpd,
        ]);

        $pegawaiBaru = Pegawai::create([
            'nip' => '9' . str_pad((string) random_int(10000000, 99999999), 8, '0') . substr(uniqid(), -8),
            'nama_pegawai' => 'Pegawai Baru ' . $label,
            'id_skpd' => $skpd->id_skpd,
            'jabatan' => 'Staff Baru ' . $skpd->nama_skpd,
        ]);

        $aset = Aset::create([
            'id_barang' => $masterBarang->id_barang,
            'id_skpd' => $skpd->id_skpd,
            'id_lokasi' => $lokasi->id_lokasi,
            'nomor_kartu_barang' => 'NKB-' . uniqid(),
            'merk' => 'Toyota ' . $label,
            'tanggal_pengadaan' => '2022-01-01',
            'nilai_perolehan' => 150000000,
            'kondisi' => 'Baik',
            'status_aset' => 'Aktif',
            'is_kendaraan' => true,
        ]);

        Kendaraan::create([
            'id_aset' => $aset->id_aset,
            'nama_kendaraan' => 'Kendaraan Dinas ' . $label,
            'jenis_kendaraan' => 'Roda 4',
            'nomor_rangka' => 'MHK' . strtoupper(substr(uniqid(), -8)),
            'nomor_mesin' => '2NR' . strtoupper(substr(uniqid(), -8)),
            'merk' => 'Toyota',
            'tipe' => 'Avanza',
            'pemegang' => $pegawaiBaru->nama_pegawai,
            'status_penggunaan' => 'Operasional',
        ]);

        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->format('Y-m-d'),
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => 'Ganti Pemegang dari ' . $pegawaiLama->nama_pegawai . ' ke ' . $pegawaiBaru->nama_pegawai,
            'id_user_penginput' => $this->adminAset->id_user,
            'status_mutasi' => 'Selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $aset->id_aset,
            'pegawai_lama' => $pegawaiLama->id_pegawai,
            'pegawai_baru' => $pegawaiBaru->id_pegawai,
        ]);

        return $mutasi;
    }

    /**
     * Skenario 1: Admin Aset dapat melihat seluruh mutasi kendaraan lintas SKPD.
     */
    public function test_skenario_01_admin_aset_dapat_melihat_seluruh_mutasi_kendaraan_lintas_skpd(): void
    {
        $response = $this->actingAs($this->adminAset)
            ->get(route('mutasi-aset.index', ['kategori' => 'kendaraan']));

        $response->assertOk();
        $mutasiList = $response->viewData('mutasiList');
        $this->assertNotNull($mutasiList);

        $ids = $mutasiList->pluck('id_mutasi')->toArray();

        // Admin Aset melihat seluruh transaksi kendaraan (lintas UPT dan Dinas)
        $expectedIds = [
            $this->mutasiPsbp1->id_mutasi,
            $this->mutasiPsbp2->id_mutasi,
            $this->mutasiPsbp3->id_mutasi,
            $this->mutasiP2btp1->id_mutasi,
            $this->mutasiP2btp2->id_mutasi,
            $this->mutasiDinas1->id_mutasi,
            $this->mutasiDinas2->id_mutasi,
            $this->mutasiDinas3->id_mutasi,
        ];
        foreach ($expectedIds as $id) {
            $this->assertContains($id, $ids, "Admin Aset harus dapat melihat mutasi kendaraan #{$id}");
        }
    }

    /**
     * Skenario 2: Admin UPT PSBP dapat melihat mutasi kendaraan #259, #335, dan #503 jika relasi transaksinya sesuai data aktual.
     */
    public function test_skenario_02_admin_upt_psbp_dapat_melihat_mutasi_kendaraan_259_335_503(): void
    {
        $response = $this->actingAs($this->adminUptPsbp)
            ->get(route('mutasi-aset.index', ['kategori' => 'kendaraan']));

        $response->assertOk();
        $this->assertSame('kendaraan', $response->viewData('kategori'));

        $mutasiList = $response->viewData('mutasiList');
        $this->assertNotNull($mutasiList);
        $ids = $mutasiList->pluck('id_mutasi')->toArray();

        $this->assertContains($this->mutasiPsbp1->id_mutasi, $ids, 'Admin UPT PSBP harus dapat melihat mutasi PSBP 1');
        $this->assertContains($this->mutasiPsbp2->id_mutasi, $ids, 'Admin UPT PSBP harus dapat melihat mutasi PSBP 2');
        $this->assertContains($this->mutasiPsbp3->id_mutasi, $ids, 'Admin UPT PSBP harus dapat melihat mutasi PSBP 3');
    }

    /**
     * Skenario 3: Admin UPT P2BTP dapat melihat mutasi kendaraan #347 dan #1178 jika relasi transaksinya sesuai data aktual.
     */
    public function test_skenario_03_admin_upt_p2btp_dapat_melihat_mutasi_kendaraan_347_1178(): void
    {
        $response = $this->actingAs($this->adminUptP2btp)
            ->get(route('mutasi-aset.index', ['kategori' => 'kendaraan']));

        $response->assertOk();
        $this->assertSame('kendaraan', $response->viewData('kategori'));

        $mutasiList = $response->viewData('mutasiList');
        $this->assertNotNull($mutasiList);
        $ids = $mutasiList->pluck('id_mutasi')->toArray();

        $this->assertContains($this->mutasiP2btp1->id_mutasi, $ids, 'Admin UPT P2BTP harus dapat melihat mutasi P2BTP 1');
        $this->assertContains($this->mutasiP2btp2->id_mutasi, $ids, 'Admin UPT P2BTP harus dapat melihat mutasi P2BTP 2');
    }

    /**
     * Skenario 4: Admin UPT tidak dapat melihat mutasi kendaraan yang tidak berkaitan dengan UPT miliknya.
     */
    public function test_skenario_04_admin_upt_tidak_dapat_melihat_mutasi_kendaraan_di_luar_upt_miliknya(): void
    {
        // Admin UPT PSBP tidak boleh melihat mutasi P2BTP maupun mutasi Dinas
        $resPsbp = $this->actingAs($this->adminUptPsbp)
            ->get(route('mutasi-aset.index', ['kategori' => 'kendaraan']));
        $idsPsbp = $resPsbp->viewData('mutasiList')->pluck('id_mutasi')->toArray();

        $this->assertNotContains($this->mutasiP2btp1->id_mutasi, $idsPsbp);
        $this->assertNotContains($this->mutasiP2btp2->id_mutasi, $idsPsbp);
        $this->assertNotContains($this->mutasiDinas1->id_mutasi, $idsPsbp);
        $this->assertNotContains($this->mutasiDinas2->id_mutasi, $idsPsbp);
        $this->assertNotContains($this->mutasiDinas3->id_mutasi, $idsPsbp);

        // Admin UPT P2BTP tidak boleh melihat mutasi PSBP maupun mutasi Dinas
        $resP2btp = $this->actingAs($this->adminUptP2btp)
            ->get(route('mutasi-aset.index', ['kategori' => 'kendaraan']));
        $idsP2btp = $resP2btp->viewData('mutasiList')->pluck('id_mutasi')->toArray();

        $this->assertNotContains($this->mutasiPsbp1->id_mutasi, $idsP2btp);
        $this->assertNotContains($this->mutasiPsbp2->id_mutasi, $idsP2btp);
        $this->assertNotContains($this->mutasiPsbp3->id_mutasi, $idsP2btp);
        $this->assertNotContains($this->mutasiDinas1->id_mutasi, $idsP2btp);
        $this->assertNotContains($this->mutasiDinas2->id_mutasi, $idsP2btp);
        $this->assertNotContains($this->mutasiDinas3->id_mutasi, $idsP2btp);
    }

    /**
     * Skenario 5: Admin UPT dapat mengunduh BAST mutasi yang memang berada dalam scope-nya.
     */
    public function test_skenario_05_admin_upt_dapat_mengunduh_bast_mutasi_dalam_scopenya(): void
    {
        // Admin UPT PSBP mengunduh BAST untuk mutasi PSBP 1
        $resPsbp = $this->actingAs($this->adminUptPsbp)
            ->get(route('kendaraan.bast.download', $this->mutasiPsbp1->id_mutasi));
        $resPsbp->assertOk();
        $this->assertTrue(
            str_contains($resPsbp->headers->get('content-type') ?? '', 'wordprocessingml')
            || str_contains($resPsbp->headers->get('content-disposition') ?? '', '.docx'),
            'Response unduh BAST oleh Admin UPT PSBP harus mengembalikan berkas Word .docx'
        );

        // Admin UPT P2BTP mengunduh BAST untuk mutasi P2BTP 1 & 2
        $resP2btp1 = $this->actingAs($this->adminUptP2btp)
            ->get(route('kendaraan.bast.download', $this->mutasiP2btp1->id_mutasi));
        $resP2btp1->assertOk();
        $this->assertTrue(
            str_contains($resP2btp1->headers->get('content-type') ?? '', 'wordprocessingml')
            || str_contains($resP2btp1->headers->get('content-disposition') ?? '', '.docx'),
            'Response unduh BAST oleh Admin UPT P2BTP harus mengembalikan berkas Word .docx'
        );

        $resP2btp2 = $this->actingAs($this->adminUptP2btp)
            ->get(route('kendaraan.bast.download', $this->mutasiP2btp2->id_mutasi));
        $resP2btp2->assertOk();
    }

    /**
     * Skenario 6: Admin UPT tidak dapat mengunduh BAST milik UPT lain dengan memanipulasi URL atau id_mutasi.
     */
    public function test_skenario_06_admin_upt_tidak_dapat_mengunduh_bast_milik_upt_lain_atau_dinas(): void
    {
        // Admin UPT PSBP mencoba mengunduh BAST mutasi milik P2BTP
        $resPsbpToP2btp = $this->actingAs($this->adminUptPsbp)
            ->get(route('kendaraan.bast.download', $this->mutasiP2btp1->id_mutasi));
        $resPsbpToP2btp->assertStatus(403);

        // Admin UPT P2BTP mencoba mengunduh BAST mutasi milik PSBP
        $resP2btpToPsbp = $this->actingAs($this->adminUptP2btp)
            ->get(route('kendaraan.bast.download', $this->mutasiPsbp1->id_mutasi));
        $resP2btpToPsbp->assertStatus(403);

        // Admin UPT PSBP & P2BTP mencoba mengunduh BAST mutasi milik Dinas
        $resPsbpToDinas = $this->actingAs($this->adminUptPsbp)
            ->get(route('kendaraan.bast.download', $this->mutasiDinas1->id_mutasi));
        $resPsbpToDinas->assertStatus(403);

        $resP2btpToDinas = $this->actingAs($this->adminUptP2btp)
            ->get(route('kendaraan.bast.download', $this->mutasiDinas2->id_mutasi));
        $resP2btpToDinas->assertStatus(403);
    }

    /**
     * Skenario 7: Admin Bidang mendapatkan 403 Forbidden.
     */
    public function test_skenario_07_admin_bidang_mendapatkan_403_forbidden(): void
    {
        // Akses menu Mutasi Aset
        $resIndex = $this->actingAs($this->adminBidang)
            ->get(route('mutasi-aset.index', ['kategori' => 'kendaraan']));
        $resIndex->assertStatus(403);

        // Akses endpoint unduh BAST kendaraan
        $resBast = $this->actingAs($this->adminBidang)
            ->get(route('kendaraan.bast.download', $this->mutasiPsbp1->id_mutasi));
        $resBast->assertStatus(403);
    }

    /**
     * Skenario 8: Pegawai mendapatkan 403 Forbidden.
     */
    public function test_skenario_08_pegawai_mendapatkan_403_forbidden(): void
    {
        // Akses menu Mutasi Aset
        $resIndex = $this->actingAs($this->pegawai)
            ->get(route('mutasi-aset.index', ['kategori' => 'kendaraan']));
        $resIndex->assertStatus(403);

        // Akses endpoint unduh BAST kendaraan
        $resBast = $this->actingAs($this->pegawai)
            ->get(route('kendaraan.bast.download', $this->mutasiPsbp1->id_mutasi));
        $resBast->assertStatus(403);
    }

    /**
     * Skenario 9: Tab Barang tetap berfungsi seperti sebelumnya tanpa regresi.
     */
    public function test_skenario_09_tab_barang_tetap_berfungsi_seperti_sebelumnya(): void
    {
        // Admin Aset membuka tab barang
        $resAset = $this->actingAs($this->adminAset)
            ->get(route('mutasi-aset.index', ['kategori' => 'barang']));
        $resAset->assertOk();
        $this->assertSame('barang', $resAset->viewData('kategori'));
        $resAset->assertSee('Aset Barang');

        // Admin UPT membuka tab barang
        $resUpt = $this->actingAs($this->adminUptP2btp)
            ->get(route('mutasi-aset.index', ['kategori' => 'barang']));
        $resUpt->assertOk();
        $this->assertSame('barang', $resUpt->viewData('kategori'));

        // Cek unduh BAST barang
        $mutasiBarang = MutasiAset::where('jenis_mutasi', 'Ganti Pemegang')
            ->whereHas('details.aset', fn ($a) => $a->where('is_kendaraan', false))
            ->first();

        if ($mutasiBarang) {
            $resBastBarang = $this->actingAs($this->adminAset)
                ->get(route('mutasi-aset.bast.download', $mutasiBarang->id_mutasi));
            $resBastBarang->assertOk();
        }
    }

    /**
     * Skenario 10: Tombol BAST kendaraan tetap memiliki tampilan yang sama dengan Tab Barang.
     */
    public function test_skenario_10_tombol_bast_kendaraan_tetap_memiliki_tampilan_yang_sama_dengan_tab_barang(): void
    {
        $response = $this->actingAs($this->adminAset)
            ->get(route('mutasi-aset.index', ['kategori' => 'kendaraan']));

        $response->assertOk();
        $html = $response->getContent();

        // Label 'Unduh BAST' dan styling yang seragam dengan Tab Barang
        $this->assertStringContainsString('Unduh BAST', $html);
        $this->assertStringContainsString('border-sky-200 bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700', $html);
        $this->assertStringContainsString('title="Unduh Berkas BAST"', $html);
    }

    /**
     * Skenario 11: Tombol SPPKD tetap tidak muncul pada Tab Kendaraan.
     */
    public function test_skenario_11_tombol_sppkd_tetap_tidak_muncul_pada_tab_kendaraan(): void
    {
        $response = $this->actingAs($this->adminAset)
            ->get(route('mutasi-aset.index', ['kategori' => 'kendaraan']));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringNotContainsString('route(\'kendaraan.sppkd.download\'', $html);
        $this->assertStringNotContainsString('Unduh Dokumen SPPKD', $html);
        $this->assertStringNotContainsString('>SPPKD<', $html);
    }

    /**
     * Skenario 12: Pagination, filter, pencarian, dan pergantian tab tetap berfungsi.
     */
    public function test_skenario_12_pagination_filter_pencarian_dan_pergantian_tab_tetap_berfungsi(): void
    {
        // 1. Pergantian tab: Tab Kendaraan dan Tab Barang dapat diakses
        $resTabKendaraan = $this->actingAs($this->adminUptPsbp)
            ->get(route('mutasi-aset.index', ['kategori' => 'kendaraan']));
        $resTabKendaraan->assertOk();
        $resTabKendaraan->assertSee('href="' . route('mutasi-aset.index', ['kategori' => 'kendaraan']) . '"', false);
        $resTabKendaraan->assertSee('href="' . route('mutasi-aset.index', ['kategori' => 'barang']) . '"', false);

        // 2. Filter jenis mutasi
        $resFilter = $this->actingAs($this->adminAset)
            ->get(route('mutasi-aset.index', ['kategori' => 'kendaraan', 'jenis_mutasi' => 'Ganti Pemegang']));
        $resFilter->assertOk();
        $listFilter = $resFilter->viewData('mutasiList');
        foreach ($listFilter as $m) {
            $this->assertSame('Ganti Pemegang', $m->jenis_mutasi);
        }

        // 3. Pencarian numerik ID mutasi untuk Admin UPT PSBP
        $resSearch = $this->actingAs($this->adminUptPsbp)
            ->get(route('mutasi-aset.index', ['kategori' => 'kendaraan', 'search' => (string) $this->mutasiPsbp1->id_mutasi]));
        $resSearch->assertOk();
        $listSearch = $resSearch->viewData('mutasiList');
        $this->assertTrue($listSearch->contains('id_mutasi', $this->mutasiPsbp1->id_mutasi));

        // Pencarian transaksi UPT lain oleh Admin UPT PSBP menghasilkan 0 data
        $resSearchLain = $this->actingAs($this->adminUptPsbp)
            ->get(route('mutasi-aset.index', ['kategori' => 'kendaraan', 'search' => (string) $this->mutasiP2btp1->id_mutasi]));
        $resSearchLain->assertOk();
        $this->assertCount(0, $resSearchLain->viewData('mutasiList'));
    }
}
