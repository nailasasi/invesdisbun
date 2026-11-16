<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\DetailMutasiAset;
use App\Models\Kendaraan;
use App\Models\Lokasi;
use App\Models\MutasiAset;
use App\Models\Pegawai;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
        parent::tearDown();
    }

    private function namaDatabase(): string
    {
        if (file_exists(base_path('.env'))) {
            foreach (file(base_path('.env')) as $line) {
                if (str_starts_with(trim($line), 'DB_DATABASE=')) {
                    $nilai = trim(explode('=', $line, 2)[1]);

                    return $nilai === '' ? 'invesdisbun' : $nilai;
                }
            }
        }

        return 'invesdisbun';
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

        $this->adminBidang = User::whereHas('role', fn ($q) => $q->where('nama_role', 'Admin Bidang'))->firstOrFail();
        $this->pegawai = User::whereHas('role', fn ($q) => $q->where('nama_role', 'Pegawai'))->firstOrFail();

        $this->skpdP2btp = Skpd::where('nama_skpd', 'like', '%P2BTP%')->firstOrFail();
        $this->skpdPsbp = Skpd::where('nama_skpd', 'like', '%PSBP%')->firstOrFail();
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
        $expectedIds = [259, 335, 346, 347, 503, 1178, 1179, 1186];
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

        $this->assertContains(259, $ids, 'Admin UPT PSBP harus dapat melihat mutasi #259');
        $this->assertContains(335, $ids, 'Admin UPT PSBP harus dapat melihat mutasi #335');
        $this->assertContains(503, $ids, 'Admin UPT PSBP harus dapat melihat mutasi #503');
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

        $this->assertContains(347, $ids, 'Admin UPT P2BTP harus dapat melihat mutasi #347');
        $this->assertContains(1178, $ids, 'Admin UPT P2BTP harus dapat melihat mutasi #1178');
    }

    /**
     * Skenario 4: Admin UPT tidak dapat melihat mutasi kendaraan yang tidak berkaitan dengan UPT miliknya.
     */
    public function test_skenario_04_admin_upt_tidak_dapat_melihat_mutasi_kendaraan_di_luar_upt_miliknya(): void
    {
        // Admin UPT PSBP tidak boleh melihat mutasi P2BTP (#347, #1178) maupun mutasi Dinas (#346, #1179, #1186)
        $resPsbp = $this->actingAs($this->adminUptPsbp)
            ->get(route('mutasi-aset.index', ['kategori' => 'kendaraan']));
        $idsPsbp = $resPsbp->viewData('mutasiList')->pluck('id_mutasi')->toArray();

        $this->assertNotContains(347, $idsPsbp);
        $this->assertNotContains(1178, $idsPsbp);
        $this->assertNotContains(346, $idsPsbp);
        $this->assertNotContains(1179, $idsPsbp);
        $this->assertNotContains(1186, $idsPsbp);

        // Admin UPT P2BTP tidak boleh melihat mutasi PSBP (#259, #335, #503) maupun mutasi Dinas (#346, #1179, #1186)
        $resP2btp = $this->actingAs($this->adminUptP2btp)
            ->get(route('mutasi-aset.index', ['kategori' => 'kendaraan']));
        $idsP2btp = $resP2btp->viewData('mutasiList')->pluck('id_mutasi')->toArray();

        $this->assertNotContains(259, $idsP2btp);
        $this->assertNotContains(335, $idsP2btp);
        $this->assertNotContains(503, $idsP2btp);
        $this->assertNotContains(346, $idsP2btp);
        $this->assertNotContains(1179, $idsP2btp);
        $this->assertNotContains(1186, $idsP2btp);
    }

    /**
     * Skenario 5: Admin UPT dapat mengunduh BAST mutasi yang memang berada dalam scope-nya.
     */
    public function test_skenario_05_admin_upt_dapat_mengunduh_bast_mutasi_dalam_scopenya(): void
    {
        // Admin UPT PSBP mengunduh BAST untuk mutasi #259 (Ganti Pemegang dalam scope PSBP)
        $resPsbp = $this->actingAs($this->adminUptPsbp)
            ->get(route('kendaraan.bast.download', 259));
        $resPsbp->assertOk();
        $this->assertTrue(
            str_contains($resPsbp->headers->get('content-type') ?? '', 'wordprocessingml')
            || str_contains($resPsbp->headers->get('content-disposition') ?? '', '.docx'),
            'Response unduh BAST oleh Admin UPT PSBP harus mengembalikan berkas Word .docx'
        );

        // Admin UPT P2BTP mengunduh BAST untuk mutasi #347 & #1178 (Ganti Pemegang dalam scope P2BTP)
        $resP2btp1 = $this->actingAs($this->adminUptP2btp)
            ->get(route('kendaraan.bast.download', 347));
        $resP2btp1->assertOk();
        $this->assertTrue(
            str_contains($resP2btp1->headers->get('content-type') ?? '', 'wordprocessingml')
            || str_contains($resP2btp1->headers->get('content-disposition') ?? '', '.docx'),
            'Response unduh BAST oleh Admin UPT P2BTP harus mengembalikan berkas Word .docx'
        );

        $resP2btp2 = $this->actingAs($this->adminUptP2btp)
            ->get(route('kendaraan.bast.download', 1178));
        $resP2btp2->assertOk();
    }

    /**
     * Skenario 6: Admin UPT tidak dapat mengunduh BAST milik UPT lain dengan memanipulasi URL atau id_mutasi.
     */
    public function test_skenario_06_admin_upt_tidak_dapat_mengunduh_bast_milik_upt_lain_atau_dinas(): void
    {
        // Admin UPT PSBP mencoba mengunduh BAST mutasi milik P2BTP (#347)
        $resPsbpToP2btp = $this->actingAs($this->adminUptPsbp)
            ->get(route('kendaraan.bast.download', 347));
        $resPsbpToP2btp->assertStatus(403);

        // Admin UPT P2BTP mencoba mengunduh BAST mutasi milik PSBP (#259)
        $resP2btpToPsbp = $this->actingAs($this->adminUptP2btp)
            ->get(route('kendaraan.bast.download', 259));
        $resP2btpToPsbp->assertStatus(403);

        // Admin UPT PSBP & P2BTP mencoba mengunduh BAST mutasi milik Dinas (#346 dan #1186)
        $resPsbpToDinas = $this->actingAs($this->adminUptPsbp)
            ->get(route('kendaraan.bast.download', 346));
        $resPsbpToDinas->assertStatus(403);

        $resP2btpToDinas = $this->actingAs($this->adminUptP2btp)
            ->get(route('kendaraan.bast.download', 1186));
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
            ->get(route('kendaraan.bast.download', 259));
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
            ->get(route('kendaraan.bast.download', 259));
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
            ->get(route('mutasi-aset.index', ['kategori' => 'kendaraan', 'search' => '259']));
        $resSearch->assertOk();
        $listSearch = $resSearch->viewData('mutasiList');
        $this->assertTrue($listSearch->contains('id_mutasi', 259));

        // Pencarian transaksi UPT lain oleh Admin UPT PSBP menghasilkan 0 data
        $resSearchLain = $this->actingAs($this->adminUptPsbp)
            ->get(route('mutasi-aset.index', ['kategori' => 'kendaraan', 'search' => '347']));
        $resSearchLain->assertOk();
        $this->assertCount(0, $resSearchLain->viewData('mutasiList'));
    }
}
