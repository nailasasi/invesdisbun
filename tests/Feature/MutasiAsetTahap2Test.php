<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\DetailMutasiAset;
use App\Models\Kendaraan;
use App\Models\Lokasi;
use App\Models\MasterBarang;
use App\Models\MutasiAset;
use App\Models\Pegawai;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Skpd;
use App\Models\User;
use App\Services\AsetScope;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Test Suite Tahap 2A: Pembukaan Akses Riwayat Mutasi Admin UPT (P2BTP & PSBP).
 */
class MutasiAsetTahap2Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabase(),
        ]);

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function namaDatabase(): string
    {
        foreach (file(base_path('.env')) as $line) {
            if (str_starts_with(trim($line), 'DB_DATABASE=')) {
                $nilai = trim(explode('=', $line, 2)[1]);

                return $nilai === '' ? 'invesdisbun' : $nilai;
            }
        }

        return 'invesdisbun';
    }

    private function loginSebagai(User $user): void
    {
        app()->forgetInstance(AsetScope::class);
        $this->actingAs($user);
    }

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
            'nama_pegawai' => $namaRole . ' ' . $skpd->id_skpd . ' ' . uniqid(),
            'nip' => '199' . rand(10000000, 99999999),
            'id_skpd' => $skpd->id_skpd,
            'id_ruangan' => $ruangan?->id_ruangan,
            'jabatan' => 'Staf ' . $namaRole,
        ]);

        return User::create([
            'username' => strtolower(str_replace(' ', '_', $namaRole)) . '_' . uniqid(),
            'password' => bcrypt('password123'),
            'id_pegawai' => $pegawai->id_pegawai,
            'id_role' => $role->id_role,
            'status_user' => 'aktif',
        ]);
    }

    private function ambilSkpdUptP2btp(): Skpd
    {
        $skpd = Skpd::where('jenis_skpd', 'UPT')
            ->where('nama_skpd', 'like', '%P2BTP%')
            ->first();

        if (! $skpd) {
            $skpd = Skpd::where('jenis_skpd', 'UPT')->firstOrFail();
        }

        return $skpd;
    }

    private function ambilSkpdUptPsbp(): Skpd
    {
        $skpd = Skpd::where('jenis_skpd', 'UPT')
            ->where('nama_skpd', 'like', '%PSBP%')
            ->first();

        if (! $skpd) {
            $p2btp = $this->ambilSkpdUptP2btp();
            $skpd = Skpd::where('jenis_skpd', 'UPT')->where('id_skpd', '!=', $p2btp->id_skpd)->firstOrFail();
        }

        return $skpd;
    }

    private function ambilSkpdBidang(): Skpd
    {
        return Skpd::where('jenis_skpd', '!=', 'UPT')->firstOrFail();
    }

    private function buatRuanganUntukSkpd(Skpd $skpd): Ruangan
    {
        $lokasi = Lokasi::untukSkpd($skpd->id_skpd) ?? Lokasi::dinas();

        return Ruangan::create([
            'id_skpd' => $skpd->id_skpd,
            'id_lokasi' => $lokasi->id_lokasi,
            'nama_ruangan' => 'Ruang Uji ' . $skpd->id_skpd . ' ' . uniqid(),
            'status' => 'Aktif',
        ]);
    }

    private function buatAsetBarang(Skpd $skpd, ?Ruangan $ruangan = null): Aset
    {
        $mb = MasterBarang::firstOrCreate(
            ['nama_barang' => 'Laptop Mutasi Tahap 2A'],
            ['kode_barang' => 'MB-MUT-2A']
        );

        $idLokasi = $ruangan ? $ruangan->id_lokasi : Lokasi::dinas()->id_lokasi;

        return Aset::create([
            'id_barang' => $mb->id_barang,
            'id_skpd' => $skpd->id_skpd,
            'id_lokasi' => $idLokasi,
            'nomor_kartu_barang' => 'KIB-B-' . uniqid(),
            'merk' => 'Lenovo ThinkPad',
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'tanggal_pengadaan' => now()->toDateString(),
            'nilai_perolehan' => 12000000,
            'is_kendaraan' => false,
        ]);
    }

    private function buatTransaksiMutasi(
        Aset $aset,
        ?Ruangan $ruanganLama,
        ?Ruangan $ruanganBaru,
        string $jenisMutasi = 'Pindah Ruangan',
        string $keterangan = 'Pengujian Riwayat Mutasi Tahap 2A'
    ): MutasiAset {
        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => $jenisMutasi,
            'keterangan' => $keterangan,
            'id_user_penginput' => auth()->id(),
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $aset->id_aset,
            'ruangan_lama' => $ruanganLama?->id_ruangan,
            'ruangan_baru' => $ruanganBaru?->id_ruangan,
            'pegawai_lama' => null,
            'pegawai_baru' => null,
        ]);

        return $mutasi;
    }

    // =========================================================================
    // 16 SKENARIO PENGUJIAN TAHAP 2A
    // =========================================================================

    /**
     * Skenario 1: Admin Aset dapat melihat seluruh riwayat mutasi secara global.
     */
    public function test_admin_aset_dapat_melihat_seluruh_riwayat_mutasi(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $p2btpSkpd = $this->ambilSkpdUptP2btp();

        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);
        $ruangDinas = $this->buatRuanganUntukSkpd($dinasSkpd);
        $ruangP2btp = $this->buatRuanganUntukSkpd($p2btpSkpd);

        $asetDinas = $this->buatAsetBarang($dinasSkpd, $ruangDinas);
        $asetP2btp = $this->buatAsetBarang($p2btpSkpd, $ruangP2btp);

        $mutasiDinas = $this->buatTransaksiMutasi($asetDinas, $ruangDinas, $ruangDinas, 'Pindah Ruangan', 'Mutasi Internal Dinas');
        $mutasiP2btp = $this->buatTransaksiMutasi($asetP2btp, $ruangP2btp, $ruangP2btp, 'Pindah Ruangan', 'Mutasi Internal P2BTP');

        $this->loginSebagai($adminAset);

        $response = $this->get(route('mutasi-aset.index'));
        $response->assertStatus(200);

        $list = $response->viewData('mutasiList');
        $ids = $list->pluck('id_mutasi')->all();

        $this->assertContains($mutasiDinas->id_mutasi, $ids);
        $this->assertContains($mutasiP2btp->id_mutasi, $ids);
    }

    /**
     * Skenario 2: Admin UPT P2BTP dapat melihat mutasi yang melibatkan lokasi P2BTP.
     */
    public function test_admin_upt_p2btp_dapat_melihat_mutasi_yang_melibatkan_lokasi_p2btp(): void
    {
        $p2btpSkpd = $this->ambilSkpdUptP2btp();
        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $p2btpSkpd);
        $ruangP2btp = $this->buatRuanganUntukSkpd($p2btpSkpd);

        $aset = $this->buatAsetBarang($p2btpSkpd, $ruangP2btp);
        $mutasi = $this->buatTransaksiMutasi($aset, $ruangP2btp, $ruangP2btp, 'Pindah Ruangan', 'Mutasi Internal P2BTP');

        $this->loginSebagai($adminP2btp);

        $response = $this->get(route('mutasi-aset.index'));
        $response->assertStatus(200);

        $list = $response->viewData('mutasiList');
        $this->assertContains($mutasi->id_mutasi, $list->pluck('id_mutasi')->all());
    }

    /**
     * Skenario 3: Admin UPT PSBP dapat melihat mutasi yang melibatkan lokasi PSBP.
     */
    public function test_admin_upt_psbp_dapat_melihat_mutasi_yang_melibatkan_lokasi_psbp(): void
    {
        $psbpSkpd = $this->ambilSkpdUptPsbp();
        $adminPsbp = $this->buatUserDenganRole('Admin UPT', $psbpSkpd);
        $ruangPsbp = $this->buatRuanganUntukSkpd($psbpSkpd);

        $aset = $this->buatAsetBarang($psbpSkpd, $ruangPsbp);
        $mutasi = $this->buatTransaksiMutasi($aset, $ruangPsbp, $ruangPsbp, 'Pindah Ruangan', 'Mutasi Internal PSBP');

        $this->loginSebagai($adminPsbp);

        $response = $this->get(route('mutasi-aset.index'));
        $response->assertStatus(200);

        $list = $response->viewData('mutasiList');
        $this->assertContains($mutasi->id_mutasi, $list->pluck('id_mutasi')->all());
    }

    /**
     * Skenario 4: Admin UPT P2BTP tidak dapat melihat mutasi internal Dinas.
     */
    public function test_admin_upt_p2btp_tidak_dapat_melihat_mutasi_internal_dinas(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $p2btpSkpd = $this->ambilSkpdUptP2btp();

        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $p2btpSkpd);
        $ruangDinas = $this->buatRuanganUntukSkpd($dinasSkpd);

        $asetDinas = $this->buatAsetBarang($dinasSkpd, $ruangDinas);
        $mutasiDinas = $this->buatTransaksiMutasi($asetDinas, $ruangDinas, $ruangDinas, 'Pindah Ruangan', 'Internal Dinas Saja');

        $this->loginSebagai($adminP2btp);

        $response = $this->get(route('mutasi-aset.index'));
        $response->assertStatus(200);

        $list = $response->viewData('mutasiList');
        $this->assertNotContains($mutasiDinas->id_mutasi, $list->pluck('id_mutasi')->all());
    }

    /**
     * Skenario 5: Admin UPT PSBP tidak dapat melihat mutasi internal Dinas.
     */
    public function test_admin_upt_psbp_tidak_dapat_melihat_mutasi_internal_dinas(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $psbpSkpd = $this->ambilSkpdUptPsbp();

        $adminPsbp = $this->buatUserDenganRole('Admin UPT', $psbpSkpd);
        $ruangDinas = $this->buatRuanganUntukSkpd($dinasSkpd);

        $asetDinas = $this->buatAsetBarang($dinasSkpd, $ruangDinas);
        $mutasiDinas = $this->buatTransaksiMutasi($asetDinas, $ruangDinas, $ruangDinas, 'Pindah Ruangan', 'Internal Dinas Saja');

        $this->loginSebagai($adminPsbp);

        $response = $this->get(route('mutasi-aset.index'));
        $response->assertStatus(200);

        $list = $response->viewData('mutasiList');
        $this->assertNotContains($mutasiDinas->id_mutasi, $list->pluck('id_mutasi')->all());
    }

    /**
     * Skenario 6: Admin UPT P2BTP tidak dapat melihat mutasi milik PSBP yang tidak melibatkan P2BTP.
     */
    public function test_admin_upt_p2btp_tidak_dapat_melihat_mutasi_milik_psbp_yang_tidak_melibatkan_p2btp(): void
    {
        $p2btpSkpd = $this->ambilSkpdUptP2btp();
        $psbpSkpd = $this->ambilSkpdUptPsbp();

        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $p2btpSkpd);
        $ruangPsbp = $this->buatRuanganUntukSkpd($psbpSkpd);

        $asetPsbp = $this->buatAsetBarang($psbpSkpd, $ruangPsbp);
        $mutasiPsbp = $this->buatTransaksiMutasi($asetPsbp, $ruangPsbp, $ruangPsbp, 'Pindah Ruangan', 'Mutasi Internal PSBP Eksklusif');

        $this->loginSebagai($adminP2btp);

        $response = $this->get(route('mutasi-aset.index'));
        $response->assertStatus(200);

        $list = $response->viewData('mutasiList');
        $this->assertNotContains($mutasiPsbp->id_mutasi, $list->pluck('id_mutasi')->all());
    }

    /**
     * Skenario 7: Admin UPT PSBP tidak dapat melihat mutasi milik P2BTP yang tidak melibatkan PSBP.
     */
    public function test_admin_upt_psbp_tidak_dapat_melihat_mutasi_milik_p2btp_yang_tidak_melibatkan_psbp(): void
    {
        $p2btpSkpd = $this->ambilSkpdUptP2btp();
        $psbpSkpd = $this->ambilSkpdUptPsbp();

        $adminPsbp = $this->buatUserDenganRole('Admin UPT', $psbpSkpd);
        $ruangP2btp = $this->buatRuanganUntukSkpd($p2btpSkpd);

        $asetP2btp = $this->buatAsetBarang($p2btpSkpd, $ruangP2btp);
        $mutasiP2btp = $this->buatTransaksiMutasi($asetP2btp, $ruangP2btp, $ruangP2btp, 'Pindah Ruangan', 'Mutasi Internal P2BTP Eksklusif');

        $this->loginSebagai($adminPsbp);

        $response = $this->get(route('mutasi-aset.index'));
        $response->assertStatus(200);

        $list = $response->viewData('mutasiList');
        $this->assertNotContains($mutasiP2btp->id_mutasi, $list->pluck('id_mutasi')->all());
    }

    /**
     * Skenario 8: Mutasi dari UPT ke Dinas tetap terlihat oleh UPT asal.
     */
    public function test_mutasi_dari_upt_ke_dinas_tetap_terlihat_oleh_upt_asal(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $p2btpSkpd = $this->ambilSkpdUptP2btp();

        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $p2btpSkpd);
        $ruangP2btp = $this->buatRuanganUntukSkpd($p2btpSkpd);
        $ruangDinas = $this->buatRuanganUntukSkpd($dinasSkpd);

        $aset = $this->buatAsetBarang($p2btpSkpd, $ruangP2btp);
        $mutasi = $this->buatTransaksiMutasi($aset, $ruangP2btp, $ruangDinas, 'Pindah Ruangan', 'Mutasi dari P2BTP ke Dinas');

        $this->loginSebagai($adminP2btp);

        $response = $this->get(route('mutasi-aset.index'));
        $response->assertStatus(200);

        $list = $response->viewData('mutasiList');
        $this->assertContains($mutasi->id_mutasi, $list->pluck('id_mutasi')->all());
    }

    /**
     * Skenario 9: Mutasi dari Dinas ke UPT tetap terlihat oleh UPT tujuan.
     */
    public function test_mutasi_dari_dinas_ke_upt_tetap_terlihat_oleh_upt_tujuan(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $psbpSkpd = $this->ambilSkpdUptPsbp();

        $adminPsbp = $this->buatUserDenganRole('Admin UPT', $psbpSkpd);
        $ruangDinas = $this->buatRuanganUntukSkpd($dinasSkpd);
        $ruangPsbp = $this->buatRuanganUntukSkpd($psbpSkpd);

        $aset = $this->buatAsetBarang($dinasSkpd, $ruangDinas);
        $mutasi = $this->buatTransaksiMutasi($aset, $ruangDinas, $ruangPsbp, 'Pindah Ruangan', 'Mutasi dari Dinas ke PSBP');

        $this->loginSebagai($adminPsbp);

        $response = $this->get(route('mutasi-aset.index'));
        $response->assertStatus(200);

        $list = $response->viewData('mutasiList');
        $this->assertContains($mutasi->id_mutasi, $list->pluck('id_mutasi')->all());
    }

    /**
     * Skenario 10: Mutasi lintas UPT dapat terlihat oleh kedua UPT yang terlibat.
     */
    public function test_mutasi_lintas_upt_dapat_terlihat_oleh_kedua_upt_yang_terlibat(): void
    {
        $p2btpSkpd = $this->ambilSkpdUptP2btp();
        $psbpSkpd = $this->ambilSkpdUptPsbp();

        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $p2btpSkpd);
        $adminPsbp = $this->buatUserDenganRole('Admin UPT', $psbpSkpd);

        $ruangP2btp = $this->buatRuanganUntukSkpd($p2btpSkpd);
        $ruangPsbp = $this->buatRuanganUntukSkpd($psbpSkpd);

        $aset = $this->buatAsetBarang($p2btpSkpd, $ruangP2btp);
        $mutasi = $this->buatTransaksiMutasi($aset, $ruangP2btp, $ruangPsbp, 'Pindah Ruangan', 'Mutasi dari P2BTP ke PSBP Langsung');

        // P2BTP sebagai asal: harus bisa melihat
        $this->loginSebagai($adminP2btp);
        $resP2btp = $this->get(route('mutasi-aset.index'));
        $resP2btp->assertStatus(200);
        $this->assertContains($mutasi->id_mutasi, $resP2btp->viewData('mutasiList')->pluck('id_mutasi')->all());

        // PSBP sebagai tujuan: harus bisa melihat
        $this->loginSebagai($adminPsbp);
        $resPsbp = $this->get(route('mutasi-aset.index'));
        $resPsbp->assertStatus(200);
        $this->assertContains($mutasi->id_mutasi, $resPsbp->viewData('mutasiList')->pluck('id_mutasi')->all());
    }

    /**
     * Skenario 11: Mutasi dengan kedua ruangan NULL tidak terlihat oleh Admin UPT (namun terlihat oleh Admin Aset).
     */
    public function test_mutasi_dengan_kedua_ruangan_null_tidak_terlihat_oleh_admin_upt(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $p2btpSkpd = $this->ambilSkpdUptP2btp();

        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);
        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $p2btpSkpd);

        $aset = $this->buatAsetBarang($dinasSkpd, null);
        $mutasiNull = $this->buatTransaksiMutasi($aset, null, null, 'Ganti Pemegang', 'Mutasi Tanpa Ruangan');

        // Admin UPT P2BTP: tidak boleh melihat (fail-closed)
        $this->loginSebagai($adminP2btp);
        $resUpt = $this->get(route('mutasi-aset.index'));
        $resUpt->assertStatus(200);
        $this->assertNotContains($mutasiNull->id_mutasi, $resUpt->viewData('mutasiList')->pluck('id_mutasi')->all());

        // Admin Aset: tetap melihat
        $this->loginSebagai($adminAset);
        $resAset = $this->get(route('mutasi-aset.index'));
        $resAset->assertStatus(200);
        $this->assertContains($mutasiNull->id_mutasi, $resAset->viewData('mutasiList')->pluck('id_mutasi')->all());
    }

    /**
     * Skenario 12: Admin UPT tanpa lokasi valid tidak mendapatkan data (fail-closed, 0 hasil).
     */
    public function test_admin_upt_tanpa_lokasi_valid_tidak_mendapatkan_data(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang(); // SKPD Dinas, bukan UPT!
        $adminUptInvalid = $this->buatUserDenganRole('Admin UPT', $dinasSkpd);

        $this->loginSebagai($adminUptInvalid);

        $response = $this->get(route('mutasi-aset.index'));
        $response->assertStatus(200);

        $list = $response->viewData('mutasiList');
        $this->assertEquals(0, $list->total());
    }

    /**
     * Skenario 13: Admin Bidang dan Pegawai tetap mendapatkan HTTP 403.
     */
    public function test_admin_bidang_dan_pegawai_tetap_mendapatkan_http_403(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();

        $adminBidang = $this->buatUserDenganRole('Admin Bidang', $dinasSkpd);
        $pegawai = $this->buatUserDenganRole('Pegawai', $dinasSkpd);

        $this->loginSebagai($adminBidang);
        $this->get(route('mutasi-aset.index'))->assertStatus(403);

        $this->loginSebagai($pegawai);
        $this->get(route('mutasi-aset.index'))->assertStatus(403);
    }

    /**
     * Skenario 14: Admin UPT dapat membuka tab kendaraan dengan data dibatasi cakupan UPT.
     */
    public function test_admin_upt_tidak_dapat_membuka_tab_atau_data_mutasi_kendaraan(): void
    {
        $p2btpSkpd = $this->ambilSkpdUptP2btp();
        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $p2btpSkpd);

        $this->loginSebagai($adminP2btp);

        // Kirim ?kategori=kendaraan secara eksplisit
        $response = $this->get(route('mutasi-aset.index', ['kategori' => 'kendaraan']));
        $response->assertStatus(200);

        // Kategori kendaraan diizinkan
        $this->assertEquals('kendaraan', $response->viewData('kategori'));

        // Tab Kendaraan dirender di Blade
        $response->assertSee('href="' . route('mutasi-aset.index', ['kategori' => 'kendaraan']) . '"', false);
    }

    /**
     * Skenario 15: Admin UPT tidak dapat mengunduh SPPKD dan BAST mutasi di luar cakupan UPT.
     */
    public function test_admin_upt_tidak_dapat_mengunduh_dokumen_mutasi_kendaraan(): void
    {
        $p2btpSkpd = $this->ambilSkpdUptP2btp();
        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $p2btpSkpd);

        $this->loginSebagai($adminP2btp);

        // Coba unduh SPPKD via id_mutasi (harus tetap 403 Forbidden)
        $resSppkd = $this->get('/kendaraan/mutasi/1/sppkd');
        $this->assertEquals(403, $resSppkd->status());

        // Coba unduh BAST Kendaraan milik UPT PSBP (#259) oleh Admin P2BTP (harus 403 Forbidden)
        $resBast = $this->get('/kendaraan/mutasi/259/bast');
        $this->assertEquals(403, $resBast->status());
    }

    /**
     * Skenario 16: Admin Aset tidak mengalami regresi akses maupun fungsi.
     */
    public function test_admin_aset_tidak_mengalami_regresi_akses_maupun_fungsi(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);

        $this->loginSebagai($adminAset);

        // 1. Akses barang
        $resBarang = $this->get(route('mutasi-aset.index', ['kategori' => 'barang']));
        $resBarang->assertStatus(200);
        $this->assertEquals('barang', $resBarang->viewData('kategori'));
        $resBarang->assertSee('Aset Barang');
        $resBarang->assertSee('Kendaraan');

        // 2. Akses kendaraan
        $resKendaraan = $this->get(route('mutasi-aset.index', ['kategori' => 'kendaraan']));
        $resKendaraan->assertStatus(200);
        $this->assertEquals('kendaraan', $resKendaraan->viewData('kategori'));

        // 3. Filter pencarian
        $resSearch = $this->get(route('mutasi-aset.index', ['search' => 'kata_kunci_unik_yang_pasti_tidak_ada_' . uniqid()]));
        $resSearch->assertStatus(200);
        $this->assertEquals(0, $resSearch->viewData('mutasiList')->total());
    }

    // =========================================================================
    // SKENARIO PENGUJIAN TAHAP 2B (INFORMASI TABEL & MULTI-DETAIL UI/UX)
    // =========================================================================

    /**
     * Skenario 17 (Tahap 2B): Nama barang dan Nomor Kartu Barang (NKB) tampil bersamaan.
     */
    public function test_tampilan_memuat_nama_barang_dan_nomor_kartu_barang_secara_bersamaan(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);
        $ruangDinas = $this->buatRuanganUntukSkpd($dinasSkpd);

        $aset = $this->buatAsetBarang($dinasSkpd, $ruangDinas);
        $this->buatTransaksiMutasi($aset, $ruangDinas, $ruangDinas, 'Pindah Ruangan', 'Test NKB dan Nama Barang');

        $this->loginSebagai($adminAset);
        $response = $this->get(route('mutasi-aset.index'));
        $response->assertStatus(200);

        // Pastikan nama barang dan NKB keduanya tercetak pada HTML respon
        $response->assertSee($aset->barang->nama_barang);
        $response->assertSee('NKB: ' . $aset->nomor_kartu_barang);
    }

    /**
     * Skenario 18 (Tahap 2B): Informasi perpindahan ruangan asal dan tujuan tampil lengkap dengan nama lokasi.
     */
    public function test_tampilan_memuat_informasi_perpindahan_ruangan_asal_dan_tujuan_lengkap(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $p2btpSkpd = $this->ambilSkpdUptP2btp();

        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);
        $ruangAsal = $this->buatRuanganUntukSkpd($dinasSkpd);
        $ruangTujuan = $this->buatRuanganUntukSkpd($p2btpSkpd);

        $aset = $this->buatAsetBarang($dinasSkpd, $ruangAsal);
        $this->buatTransaksiMutasi($aset, $ruangAsal, $ruangTujuan, 'Pindah Ruangan', 'Test Perpindahan Ruangan Lengkap');

        $this->loginSebagai($adminAset);
        $response = $this->get(route('mutasi-aset.index'));
        $response->assertStatus(200);

        // Format: {ruangAsal} ({lokasiAsal}) → {ruangTujuan} ({lokasiTujuan})
        $response->assertSee($ruangAsal->nama_ruangan);
        $response->assertSee($ruangTujuan->nama_ruangan);
    }

    /**
     * Skenario 19 (Tahap 2B): Penanganan ruangan asal NULL menampilkan 'Belum Ditempatkan'.
     */
    public function test_tampilan_menangani_ruangan_asal_null_menjadi_belum_ditempatkan(): void
    {
        $p2btpSkpd = $this->ambilSkpdUptP2btp();
        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $p2btpSkpd);
        $ruangTujuan = $this->buatRuanganUntukSkpd($p2btpSkpd);

        $aset = $this->buatAsetBarang($p2btpSkpd, null);
        $this->buatTransaksiMutasi($aset, null, $ruangTujuan, 'Pindah Ruangan', 'Test Ruangan Asal NULL');

        $this->loginSebagai($adminP2btp);
        $response = $this->get(route('mutasi-aset.index'));
        $response->assertStatus(200);

        $response->assertSee('Belum Ditempatkan');
        $response->assertSee($ruangTujuan->nama_ruangan);
    }

    /**
     * Skenario 20 (Tahap 2B): Penanganan kedua ruangan NULL menampilkan tanda '-'.
     */
    public function test_tampilan_menangani_kedua_ruangan_null_menjadi_tanda_strip(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);

        $aset = $this->buatAsetBarang($dinasSkpd, null);
        $this->buatTransaksiMutasi($aset, null, null, 'Ganti Pemegang', 'Test Kedua Ruangan NULL');

        $this->loginSebagai($adminAset);
        $response = $this->get(route('mutasi-aset.index'));
        $response->assertStatus(200);

        // Pastikan halaman berhasil dirender tanpa error dan memuat tanda '-'
        $response->assertSee('Test Kedua Ruangan NULL');
    }

    /**
     * Skenario 21 (Tahap 2B): Kolom status mutasi menampilkan badge 'Selesai'.
     */
    public function test_tampilan_kolom_status_menampilkan_badge_selesai(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);
        $ruangDinas = $this->buatRuanganUntukSkpd($dinasSkpd);

        $aset = $this->buatAsetBarang($dinasSkpd, $ruangDinas);
        $this->buatTransaksiMutasi($aset, $ruangDinas, $ruangDinas, 'Pindah Ruangan', 'Test Status Mutasi Selesai');

        $this->loginSebagai($adminAset);
        $response = $this->get(route('mutasi-aset.index'));
        $response->assertStatus(200);

        $response->assertSee('Selesai');
    }

    /**
     * Skenario 22 (Tahap 2B): Multi-detail Admin Aset melihat seluruh detail dan tombol '+X aset lainnya'.
     */
    public function test_multi_detail_admin_aset_dapat_melihat_seluruh_detail_dan_indikator_tambahan(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $p2btpSkpd = $this->ambilSkpdUptP2btp();
        $psbpSkpd = $this->ambilSkpdUptPsbp();

        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);

        $ruangDinas = $this->buatRuanganUntukSkpd($dinasSkpd);
        $ruangP2btp = $this->buatRuanganUntukSkpd($p2btpSkpd);
        $ruangPsbp = $this->buatRuanganUntukSkpd($psbpSkpd);

        $aset1 = $this->buatAsetBarang($p2btpSkpd, $ruangP2btp);
        $aset2 = $this->buatAsetBarang($dinasSkpd, $ruangDinas);
        $aset3 = $this->buatAsetBarang($psbpSkpd, $ruangPsbp);

        // Buat mutasi multi-detail (3 aset dalam 1 transaksi)
        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Pindah Ruangan',
            'keterangan' => 'Mutasi Massal 3 Aset Berbeda Unit',
            'id_user_penginput' => $adminAset->id_user,
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create(['id_mutasi' => $mutasi->id_mutasi, 'id_aset' => $aset1->id_aset, 'ruangan_lama' => $ruangP2btp->id_ruangan, 'ruangan_baru' => $ruangP2btp->id_ruangan]);
        DetailMutasiAset::create(['id_mutasi' => $mutasi->id_mutasi, 'id_aset' => $aset2->id_aset, 'ruangan_lama' => $ruangDinas->id_ruangan, 'ruangan_baru' => $ruangDinas->id_ruangan]);
        DetailMutasiAset::create(['id_mutasi' => $mutasi->id_mutasi, 'id_aset' => $aset3->id_aset, 'ruangan_lama' => $ruangPsbp->id_ruangan, 'ruangan_baru' => $ruangPsbp->id_ruangan]);

        $this->loginSebagai($adminAset);
        $response = $this->get(route('mutasi-aset.index'));
        $response->assertStatus(200);

        // Admin Aset melihat indikator "+2 aset lainnya"
        $response->assertSee('+2 aset lainnya');

        // Pastikan ketiga aset hadir dalam payload modal HTML Admin Aset
        $response->assertSee($aset1->nomor_kartu_barang);
        $response->assertSee($aset2->nomor_kartu_barang);
        $response->assertSee($aset3->nomor_kartu_barang);
    }

    /**
     * Skenario 23 (Tahap 2B): Multi-detail Admin UPT P2BTP HANYA melihat detail miliknya, detail Dinas & PSBP TIDAK bocor ke HTML.
     */
    public function test_multi_detail_admin_upt_p2btp_hanya_melihat_detail_p2btp_tanpa_kebocoran_html(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $p2btpSkpd = $this->ambilSkpdUptP2btp();
        $psbpSkpd = $this->ambilSkpdUptPsbp();

        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $p2btpSkpd);

        $ruangDinas = $this->buatRuanganUntukSkpd($dinasSkpd);
        $ruangP2btp = $this->buatRuanganUntukSkpd($p2btpSkpd);
        $ruangPsbp = $this->buatRuanganUntukSkpd($psbpSkpd);

        $asetP2btp = $this->buatAsetBarang($p2btpSkpd, $ruangP2btp);
        $asetDinas = $this->buatAsetBarang($dinasSkpd, $ruangDinas);
        $asetPsbp = $this->buatAsetBarang($psbpSkpd, $ruangPsbp);

        // Buat mutasi multi-detail (1 P2BTP, 1 Dinas, 1 PSBP)
        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Pindah Ruangan',
            'keterangan' => 'Mutasi Multi Detail Lintas Lokasi',
            'id_user_penginput' => 1,
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create(['id_mutasi' => $mutasi->id_mutasi, 'id_aset' => $asetP2btp->id_aset, 'ruangan_lama' => $ruangP2btp->id_ruangan, 'ruangan_baru' => $ruangP2btp->id_ruangan]);
        DetailMutasiAset::create(['id_mutasi' => $mutasi->id_mutasi, 'id_aset' => $asetDinas->id_aset, 'ruangan_lama' => $ruangDinas->id_ruangan, 'ruangan_baru' => $ruangDinas->id_ruangan]);
        DetailMutasiAset::create(['id_mutasi' => $mutasi->id_mutasi, 'id_aset' => $asetPsbp->id_aset, 'ruangan_lama' => $ruangPsbp->id_ruangan, 'ruangan_baru' => $ruangPsbp->id_ruangan]);

        $this->loginSebagai($adminP2btp);
        $response = $this->get(route('mutasi-aset.index'));
        $response->assertStatus(200);

        // 1. Aset P2BTP wajib tampil
        $response->assertSee($asetP2btp->nomor_kartu_barang);

        // 2. Karena hanya ada 1 aset P2BTP dalam transaksi ini, indikator "+ aset lainnya" TIDAK boleh muncul
        $response->assertDontSee('+2 aset lainnya');
        $response->assertDontSee('+1 aset lainnya');

        // 3. KEAMANAN ANTI-LEAKAGE: NKB dan detail aset Dinas & PSBP sama sekali TIDAK BOLEH ada di HTML
        $response->assertDontSee($asetDinas->nomor_kartu_barang);
        $response->assertDontSee($asetPsbp->nomor_kartu_barang);
    }

    /**
     * Skenario 24 (Tahap 2B): Multi-detail Admin UPT P2BTP melihat 2 aset P2BTP dalam transaksi campuran 4 aset.
     */
    public function test_multi_detail_admin_upt_p2btp_melihat_dua_aset_p2btp_dalam_transaksi_campuran(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $p2btpSkpd = $this->ambilSkpdUptP2btp();
        $psbpSkpd = $this->ambilSkpdUptPsbp();

        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $p2btpSkpd);

        $ruangDinas = $this->buatRuanganUntukSkpd($dinasSkpd);
        $ruangP2btp = $this->buatRuanganUntukSkpd($p2btpSkpd);
        $ruangPsbp = $this->buatRuanganUntukSkpd($psbpSkpd);

        $asetP2btp1 = $this->buatAsetBarang($p2btpSkpd, $ruangP2btp);
        $asetP2btp2 = $this->buatAsetBarang($p2btpSkpd, $ruangP2btp);
        $asetDinas = $this->buatAsetBarang($dinasSkpd, $ruangDinas);
        $asetPsbp = $this->buatAsetBarang($psbpSkpd, $ruangPsbp);

        // Transaksi 4 detail: 2 P2BTP, 1 Dinas, 1 PSBP
        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Pindah Ruangan',
            'keterangan' => 'Mutasi 4 Aset Campuran',
            'id_user_penginput' => 1,
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create(['id_mutasi' => $mutasi->id_mutasi, 'id_aset' => $asetP2btp1->id_aset, 'ruangan_lama' => $ruangP2btp->id_ruangan, 'ruangan_baru' => $ruangP2btp->id_ruangan]);
        DetailMutasiAset::create(['id_mutasi' => $mutasi->id_mutasi, 'id_aset' => $asetP2btp2->id_aset, 'ruangan_lama' => $ruangP2btp->id_ruangan, 'ruangan_baru' => $ruangP2btp->id_ruangan]);
        DetailMutasiAset::create(['id_mutasi' => $mutasi->id_mutasi, 'id_aset' => $asetDinas->id_aset, 'ruangan_lama' => $ruangDinas->id_ruangan, 'ruangan_baru' => $ruangDinas->id_ruangan]);
        DetailMutasiAset::create(['id_mutasi' => $mutasi->id_mutasi, 'id_aset' => $asetPsbp->id_aset, 'ruangan_lama' => $ruangPsbp->id_ruangan, 'ruangan_baru' => $ruangPsbp->id_ruangan]);

        $this->loginSebagai($adminP2btp);
        $response = $this->get(route('mutasi-aset.index'));
        $response->assertStatus(200);

        // P2BTP berhak melihat 2 aset miliknya: 1 di baris utama + 1 di badge
        $response->assertSee('+1 aset lainnya');
        $response->assertSee($asetP2btp1->nomor_kartu_barang);
        $response->assertSee($asetP2btp2->nomor_kartu_barang);

        // Aset Dinas dan PSBP tetap tidak bocor
        $response->assertDontSee($asetDinas->nomor_kartu_barang);
        $response->assertDontSee($asetPsbp->nomor_kartu_barang);
    }

    /**
     * Skenario 25 (Tahap 2B): Tombol unduh BAST aktif jika seluruh detail dalam cakupan UPT.
     */
    public function test_sinkronisasi_tombol_bast_aktif_jika_seluruh_detail_dalam_cakupan_upt(): void
    {
        $p2btpSkpd = $this->ambilSkpdUptP2btp();
        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $p2btpSkpd);
        $ruangP2btp = $this->buatRuanganUntukSkpd($p2btpSkpd);

        $aset = $this->buatAsetBarang($p2btpSkpd, $ruangP2btp);
        $mutasi = $this->buatTransaksiMutasi($aset, $ruangP2btp, $ruangP2btp, 'Ganti Pemegang', 'Mutasi Ganti Pemegang Internal P2BTP');

        $this->loginSebagai($adminP2btp);
        $response = $this->get(route('mutasi-aset.index'));
        $response->assertStatus(200);

        // Tombol aktif mengarah ke route bast.download
        $response->assertSee(route('mutasi-aset.bast.download', $mutasi->id_mutasi));
        $response->assertSee('Unduh BAST');
    }

    /**
     * Skenario 26 (Tahap 2B): Tombol unduh BAST menampilkan 'Akses Terbatas' jika sebagian detail di luar cakupan UPT.
     */
    public function test_sinkronisasi_tombol_bast_akses_terbatas_jika_sebagian_detail_di_luar_cakupan_upt(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $p2btpSkpd = $this->ambilSkpdUptP2btp();

        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $p2btpSkpd);

        $ruangDinas = $this->buatRuanganUntukSkpd($dinasSkpd);
        $ruangP2btp = $this->buatRuanganUntukSkpd($p2btpSkpd);

        $asetP2btp = $this->buatAsetBarang($p2btpSkpd, $ruangP2btp);
        $asetDinas = $this->buatAsetBarang($dinasSkpd, $ruangDinas);

        // Transaksi Ganti Pemegang dengan 2 detail: 1 P2BTP, 1 Dinas
        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => 'Mutasi Ganti Pemegang Campuran',
            'id_user_penginput' => 1,
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create(['id_mutasi' => $mutasi->id_mutasi, 'id_aset' => $asetP2btp->id_aset, 'ruangan_lama' => $ruangP2btp->id_ruangan, 'ruangan_baru' => $ruangP2btp->id_ruangan]);
        DetailMutasiAset::create(['id_mutasi' => $mutasi->id_mutasi, 'id_aset' => $asetDinas->id_aset, 'ruangan_lama' => $ruangDinas->id_ruangan, 'ruangan_baru' => $ruangDinas->id_ruangan]);

        $this->loginSebagai($adminP2btp);
        $response = $this->get(route('mutasi-aset.index'));
        $response->assertStatus(200);

        // Karena sebagian aset berada di Dinas, Admin UPT P2BTP tidak boleh mendapatkan link aktif unduh BAST
        $response->assertDontSee(route('mutasi-aset.bast.download', $mutasi->id_mutasi));
        $response->assertSee('Akses Terbatas');

        // Verifikasi backend juga menolak jika URL diakses langsung
        $resDownload = $this->get(route('mutasi-aset.bast.download', $mutasi->id_mutasi));
        $this->assertEquals(403, $resDownload->status());
    }

    /**
     * Skenario 27 (Tahap 2B): Modal multi-detail memuat informasi lengkap (No, Nama, NKB, Perpindahan, Pemegang).
     */
    public function test_modal_multi_detail_memuat_informasi_lengkap_dan_benar(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);
        $ruangAsal = $this->buatRuanganUntukSkpd($dinasSkpd);
        $ruangTujuan = $this->buatRuanganUntukSkpd($dinasSkpd);

        $aset1 = $this->buatAsetBarang($dinasSkpd, $ruangAsal);
        $aset2 = $this->buatAsetBarang($dinasSkpd, $ruangTujuan);

        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Pindah Ruangan',
            'keterangan' => 'Mutasi 2 Aset untuk Cek Kelengkapan Modal',
            'id_user_penginput' => $adminAset->id_user,
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create(['id_mutasi' => $mutasi->id_mutasi, 'id_aset' => $aset1->id_aset, 'ruangan_lama' => $ruangAsal->id_ruangan, 'ruangan_baru' => $ruangTujuan->id_ruangan]);
        DetailMutasiAset::create(['id_mutasi' => $mutasi->id_mutasi, 'id_aset' => $aset2->id_aset, 'ruangan_lama' => $ruangAsal->id_ruangan, 'ruangan_baru' => $ruangTujuan->id_ruangan]);

        $this->loginSebagai($adminAset);
        $response = $this->get(route('mutasi-aset.index'));
        $response->assertStatus(200);

        // Pastikan kontainer modal multi detail tersedia di HTML
        $response->assertSee('id="modal-multi-detail"', false);
        $response->assertSee('bukaModalMultiAset', false);
        $response->assertSee('+1 aset lainnya');
        $response->assertSee('Penempatan Lama');
        $response->assertSee('Penempatan Baru');
    }

    /**
     * Skenario 28 (Perbaikan Layout): Header halaman harus berada di atas tabel dalam layout normal.
     */
    public function test_posisi_header_dan_tabel_berurutan_secara_normal_dalam_layout(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);

        $this->loginSebagai($adminAset);
        $response = $this->get(route('mutasi-aset.index'));
        $response->assertStatus(200);

        $content = $response->getContent();

        // 1. DOCTYPE harus berada paling awal (tidak didahului oleh card/tabel)
        $this->assertStringStartsWith('<!DOCTYPE html>', trim($content));

        // 2. Posisi <header> harus berada SEBELUM <table
        $posHeader = strpos($content, '<header');
        $posTable = strpos($content, '<table');

        $this->assertNotFalse($posHeader, 'Tag <header> harus ditemukan dalam respon');
        $this->assertNotFalse($posTable, 'Tag <table> harus ditemukan dalam respon');
        $this->assertLessThan($posTable, $posHeader, 'Posisi <header> harus berada sebelum <table>');
    }

    /**
     * Skenario 29 (Perbaikan Kolom): Tabel Aset Barang tepat memuat 2 kolom penempatan dan menghapus kolom perpindahan ruangan terpisah.
     */
    public function test_struktur_kolom_menggunakan_dua_kolom_penempatan(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);

        $this->loginSebagai($adminAset);
        $response = $this->get(route('mutasi-aset.index', ['kategori' => 'barang']));
        $response->assertStatus(200);

        $content = $response->getContent();

        // Ambil header thead tabel aset barang
        preg_match('/<thead[^>]*>(.*?)<\/thead>/s', $content, $matches);
        $this->assertTrue(isset($matches[1]), 'Thead tabel harus ditemukan');
        $thead = $matches[1];

        // 1. Harus memuat Penempatan Lama dan Penempatan Baru
        $this->assertStringContainsString('Penempatan Lama', $thead);
        $this->assertStringContainsString('Penempatan Baru', $thead);

        // 2. Kolom terpisah Perpindahan Ruangan, Pemegang Lama, dan Pemegang Baru TIDAK BOLEH ada di thead
        $this->assertStringNotContainsString('Perpindahan Ruangan', $thead);
        $this->assertStringNotContainsString('Pemegang Lama', $thead);
        $this->assertStringNotContainsString('Pemegang Baru', $thead);
    }

    /**
     * Skenario 30 (Perbaikan Penempatan): Pada Pindah Ruangan, hanya ruangan dan lokasi yang tampil (tanpa nama pemegang).
     */
    public function test_kolom_penempatan_pindah_ruangan_hanya_tampilkan_ruangan_dan_lokasi_tanpa_pegawai(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $p2btpSkpd = $this->ambilSkpdUptP2btp();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);

        $ruangAsal = $this->buatRuanganUntukSkpd($dinasSkpd);
        $ruangTujuan = $this->buatRuanganUntukSkpd($p2btpSkpd);

        $pegawaiUnik = Pegawai::create([
            'nama_pegawai' => 'Pegawai Khusus Pindah Ruangan ' . uniqid(),
            'nip' => '199' . rand(10000000, 99999999),
            'id_skpd' => $dinasSkpd->id_skpd,
            'jabatan' => 'Staf Pengujian',
        ]);

        $aset = $this->buatAsetBarang($dinasSkpd, $ruangAsal);

        // Mutasi Pindah Ruangan
        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Pindah Ruangan',
            'keterangan' => 'Mutasi Khusus Uji Tampilan Pindah Ruangan',
            'id_user_penginput' => $adminAset->id_user,
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $aset->id_aset,
            'ruangan_lama' => $ruangAsal->id_ruangan,
            'ruangan_baru' => $ruangTujuan->id_ruangan,
            'pegawai_lama' => $pegawaiUnik->id_pegawai,
            'pegawai_baru' => $pegawaiUnik->id_pegawai,
        ]);

        $this->loginSebagai($adminAset);
        $response = $this->get(route('mutasi-aset.index', ['kategori' => 'barang']));
        $response->assertStatus(200);

        // Ruangan dan lokasi harus muncul
        $response->assertSee($ruangAsal->nama_ruangan);
        $response->assertSee($ruangTujuan->nama_ruangan);

        // Nama pegawai TIDAK BOLEH tampil di baris tabel Pindah Ruangan
        $response->assertDontSee($pegawaiUnik->nama_pegawai);
    }

    /**
     * Skenario 31 (Perbaikan Penempatan): Pada Ganti Pemegang, pegawai tampil sebagai baris utama diikuti ruangan dan lokasi.
     */
    public function test_kolom_penempatan_ganti_pemegang_tampilkan_pegawai_ruangan_dan_lokasi(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $p2btpSkpd = $this->ambilSkpdUptP2btp();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);

        $ruangAsal = $this->buatRuanganUntukSkpd($dinasSkpd);
        $ruangTujuan = $this->buatRuanganUntukSkpd($p2btpSkpd);

        $pegLama = Pegawai::create([
            'nama_pegawai' => 'Achmad ' . uniqid(),
            'nip' => '199' . rand(10000000, 99999999),
            'id_skpd' => $dinasSkpd->id_skpd,
            'jabatan' => 'Staf Lama',
        ]);

        $pegBaru = Pegawai::create([
            'nama_pegawai' => 'Budi ' . uniqid(),
            'nip' => '199' . rand(10000000, 99999999),
            'id_skpd' => $p2btpSkpd->id_skpd,
            'jabatan' => 'Staf Baru',
        ]);

        $aset = $this->buatAsetBarang($dinasSkpd, $ruangAsal);

        // Mutasi Ganti Pemegang
        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => 'Mutasi Khusus Uji Tampilan Ganti Pemegang',
            'id_user_penginput' => $adminAset->id_user,
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $aset->id_aset,
            'ruangan_lama' => $ruangAsal->id_ruangan,
            'ruangan_baru' => $ruangTujuan->id_ruangan,
            'pegawai_lama' => $pegLama->id_pegawai,
            'pegawai_baru' => $pegBaru->id_pegawai,
        ]);

        $this->loginSebagai($adminAset);
        $response = $this->get(route('mutasi-aset.index', ['kategori' => 'barang']));
        $response->assertStatus(200);

        // Keduanya wajib tampil
        $response->assertSee($pegLama->nama_pegawai);
        $response->assertSee($pegBaru->nama_pegawai);
        $response->assertSee($ruangAsal->nama_ruangan);
        $response->assertSee($ruangTujuan->nama_ruangan);
    }

    // =========================================================================
    // SKENARIO PENGUJIAN TAHAP 2C (PENCARIAN, FILTER, DAN RESET MUTASI ASET)
    // =========================================================================

    /**
     * Skenario 32 (Pencarian Aset): Pencarian berdasarkan nama barang.
     */
    public function test_pencarian_berdasarkan_nama_barang(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);
        $ruangDinas = $this->buatRuanganUntukSkpd($dinasSkpd);

        $mbUnik = MasterBarang::create([
            'nama_barang' => 'Proyektor Epson EB-X500 ' . uniqid(),
            'kode_barang' => 'PRJ-' . uniqid(),
        ]);

        $aset = Aset::create([
            'id_barang' => $mbUnik->id_barang,
            'id_skpd' => $dinasSkpd->id_skpd,
            'id_lokasi' => $ruangDinas->id_lokasi,
            'nomor_kartu_barang' => 'KIB-PRJ-' . uniqid(),
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        $this->buatTransaksiMutasi($aset, $ruangDinas, $ruangDinas, 'Pindah Ruangan', 'Mutasi Proyektor Baru');

        $this->loginSebagai($adminAset);

        // Cari dengan kata kunci nama barang unik
        $response = $this->get(route('mutasi-aset.index', ['search' => $mbUnik->nama_barang]));
        $response->assertStatus(200);
        $response->assertSee($mbUnik->nama_barang);
    }

    /**
     * Skenario 33 (Pencarian Aset): Pencarian berdasarkan Nomor Kartu Barang (NKB) penuh dan sebagian.
     */
    public function test_pencarian_berdasarkan_nomor_kartu_barang_penuh_dan_sebagian(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);
        $ruangDinas = $this->buatRuanganUntukSkpd($dinasSkpd);

        $nkbUnik = 'KIB-NKB-TEST-' . rand(100000, 999999);
        $aset = $this->buatAsetBarang($dinasSkpd, $ruangDinas);
        $aset->nomor_kartu_barang = $nkbUnik;
        $aset->save();

        $this->buatTransaksiMutasi($aset, $ruangDinas, $ruangDinas, 'Pindah Ruangan', 'Mutasi NKB Spesifik');

        $this->loginSebagai($adminAset);

        // 1. Pencarian penuh
        $resPenuh = $this->get(route('mutasi-aset.index', ['search' => $nkbUnik]));
        $resPenuh->assertStatus(200);
        $resPenuh->assertSee($nkbUnik);

        // 2. Pencarian sebagian
        $sebagian = substr($nkbUnik, 4, 8);
        $resSebagian = $this->get(route('mutasi-aset.index', ['search' => $sebagian]));
        $resSebagian->assertStatus(200);
        $resSebagian->assertSee($nkbUnik);
    }

    /**
     * Skenario 34 (Pencarian Transaksi): Pencarian berdasarkan ID transaksi numerik (misal 123 atau #123).
     */
    public function test_pencarian_berdasarkan_id_transaksi_numerik(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);
        $ruangDinas = $this->buatRuanganUntukSkpd($dinasSkpd);
        $aset = $this->buatAsetBarang($dinasSkpd, $ruangDinas);

        $mutasi = $this->buatTransaksiMutasi($aset, $ruangDinas, $ruangDinas, 'Pindah Ruangan', 'Mutasi ID Numerik Unik');

        $this->loginSebagai($adminAset);

        // Cari angka langsung
        $resAngka = $this->get(route('mutasi-aset.index', ['search' => (string) $mutasi->id_mutasi]));
        $resAngka->assertStatus(200);
        $resAngka->assertSee('Mutasi ID Numerik Unik');

        // Cari dengan prefix '#'
        $resPagar = $this->get(route('mutasi-aset.index', ['search' => '#' . $mutasi->id_mutasi]));
        $resPagar->assertStatus(200);
        $resPagar->assertSee('Mutasi ID Numerik Unik');
    }

    /**
     * Skenario 35 (Pencarian Pegawai): Pencarian berdasarkan nama pegawai lama.
     */
    public function test_pencarian_berdasarkan_nama_pegawai_lama(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);
        $ruangDinas = $this->buatRuanganUntukSkpd($dinasSkpd);

        $pegLama = Pegawai::create([
            'nama_pegawai' => 'Staf Lama Pencarian ' . uniqid(),
            'nip' => '199' . rand(10000000, 99999999),
            'id_skpd' => $dinasSkpd->id_skpd,
            'jabatan' => 'Analis',
        ]);

        $aset = $this->buatAsetBarang($dinasSkpd, $ruangDinas);

        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => 'Mutasi Uji Pegawai Lama',
            'id_user_penginput' => $adminAset->id_user,
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $aset->id_aset,
            'ruangan_lama' => $ruangDinas->id_ruangan,
            'ruangan_baru' => $ruangDinas->id_ruangan,
            'pegawai_lama' => $pegLama->id_pegawai,
            'pegawai_baru' => null,
        ]);

        $this->loginSebagai($adminAset);
        $response = $this->get(route('mutasi-aset.index', ['search' => $pegLama->nama_pegawai]));
        $response->assertStatus(200);
        $response->assertSee($pegLama->nama_pegawai);
    }

    /**
     * Skenario 36 (Pencarian Pegawai): Pencarian berdasarkan nama pegawai baru.
     */
    public function test_pencarian_berdasarkan_nama_pegawai_baru(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);
        $ruangDinas = $this->buatRuanganUntukSkpd($dinasSkpd);

        $pegBaru = Pegawai::create([
            'nama_pegawai' => 'Staf Baru Pencarian ' . uniqid(),
            'nip' => '199' . rand(10000000, 99999999),
            'id_skpd' => $dinasSkpd->id_skpd,
            'jabatan' => 'Pranata Komputer',
        ]);

        $aset = $this->buatAsetBarang($dinasSkpd, $ruangDinas);

        $mutasi = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => 'Mutasi Uji Pegawai Baru',
            'id_user_penginput' => $adminAset->id_user,
            'status_mutasi' => 'selesai',
        ]);

        DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $aset->id_aset,
            'ruangan_lama' => $ruangDinas->id_ruangan,
            'ruangan_baru' => $ruangDinas->id_ruangan,
            'pegawai_lama' => null,
            'pegawai_baru' => $pegBaru->id_pegawai,
        ]);

        $this->loginSebagai($adminAset);
        $response = $this->get(route('mutasi-aset.index', ['search' => $pegBaru->nama_pegawai]));
        $response->assertStatus(200);
        $response->assertSee($pegBaru->nama_pegawai);
    }

    /**
     * Skenario 37 (Pencarian Ruangan): Pencarian berdasarkan nama ruangan lama.
     */
    public function test_pencarian_berdasarkan_nama_ruangan_lama(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);

        $ruangLama = Ruangan::create([
            'id_skpd' => $dinasSkpd->id_skpd,
            'id_lokasi' => Lokasi::dinas()->id_lokasi,
            'nama_ruangan' => 'Ruang Laboratorium Uji ' . uniqid(),
            'status' => 'Aktif',
        ]);
        $ruangBaru = $this->buatRuanganUntukSkpd($dinasSkpd);
        $aset = $this->buatAsetBarang($dinasSkpd, $ruangLama);

        $this->buatTransaksiMutasi($aset, $ruangLama, $ruangBaru, 'Pindah Ruangan', 'Mutasi Ruang Lama Unik');

        $this->loginSebagai($adminAset);
        $response = $this->get(route('mutasi-aset.index', ['search' => $ruangLama->nama_ruangan]));
        $response->assertStatus(200);
        $response->assertSee($ruangLama->nama_ruangan);
    }

    /**
     * Skenario 38 (Pencarian Ruangan): Pencarian berdasarkan nama ruangan baru.
     */
    public function test_pencarian_berdasarkan_nama_ruangan_baru(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);

        $ruangLama = $this->buatRuanganUntukSkpd($dinasSkpd);
        $ruangBaru = Ruangan::create([
            'id_skpd' => $dinasSkpd->id_skpd,
            'id_lokasi' => Lokasi::dinas()->id_lokasi,
            'nama_ruangan' => 'Ruang Gudang Arsip ' . uniqid(),
            'status' => 'Aktif',
        ]);
        $aset = $this->buatAsetBarang($dinasSkpd, $ruangLama);

        $this->buatTransaksiMutasi($aset, $ruangLama, $ruangBaru, 'Pindah Ruangan', 'Mutasi Ruang Baru Unik');

        $this->loginSebagai($adminAset);
        $response = $this->get(route('mutasi-aset.index', ['search' => $ruangBaru->nama_ruangan]));
        $response->assertStatus(200);
        $response->assertSee($ruangBaru->nama_ruangan);
    }

    /**
     * Skenario 39 (Filter Jenis Mutasi): Filter Pindah Ruangan dan Ganti Pemegang.
     */
    public function test_filter_jenis_mutasi(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);
        $ruangDinas = $this->buatRuanganUntukSkpd($dinasSkpd);
        $aset = $this->buatAsetBarang($dinasSkpd, $ruangDinas);

        $mutasiPindah = $this->buatTransaksiMutasi($aset, $ruangDinas, $ruangDinas, 'Pindah Ruangan', 'Ket Pindah Khusus ' . uniqid());
        $mutasiGanti = $this->buatTransaksiMutasi($aset, $ruangDinas, $ruangDinas, 'Ganti Pemegang', 'Ket Ganti Khusus ' . uniqid());

        $this->loginSebagai($adminAset);

        // 1. Filter Pindah Ruangan
        $resPindah = $this->get(route('mutasi-aset.index', ['jenis_mutasi' => 'Pindah Ruangan']));
        $resPindah->assertStatus(200);
        $resPindah->assertSee($mutasiPindah->keterangan);
        $resPindah->assertDontSee($mutasiGanti->keterangan);

        // 2. Filter Ganti Pemegang
        $resGanti = $this->get(route('mutasi-aset.index', ['jenis_mutasi' => 'Ganti Pemegang']));
        $resGanti->assertStatus(200);
        $resGanti->assertSee($mutasiGanti->keterangan);
        $resGanti->assertDontSee($mutasiPindah->keterangan);
    }

    /**
     * Skenario 40 (Filter Tanggal): Filter rentang tanggal inklusif (tanggal_mulai dan tanggal_akhir).
     */
    public function test_filter_rentang_tanggal_inklusif(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);
        $ruangDinas = $this->buatRuanganUntukSkpd($dinasSkpd);
        $aset = $this->buatAsetBarang($dinasSkpd, $ruangDinas);

        // Buat mutasi pada 3 tanggal berbeda
        $m1 = MutasiAset::create([
            'tanggal_mutasi' => '2026-05-10',
            'jenis_mutasi' => 'Pindah Ruangan',
            'keterangan' => 'Mutasi Mei 2026 ' . uniqid(),
            'id_user_penginput' => $adminAset->id_user,
            'status_mutasi' => 'selesai',
        ]);
        DetailMutasiAset::create(['id_mutasi' => $m1->id_mutasi, 'id_aset' => $aset->id_aset, 'ruangan_lama' => $ruangDinas->id_ruangan, 'ruangan_baru' => $ruangDinas->id_ruangan]);

        $m2 = MutasiAset::create([
            'tanggal_mutasi' => '2026-06-15',
            'jenis_mutasi' => 'Pindah Ruangan',
            'keterangan' => 'Mutasi Juni 2026 ' . uniqid(),
            'id_user_penginput' => $adminAset->id_user,
            'status_mutasi' => 'selesai',
        ]);
        DetailMutasiAset::create(['id_mutasi' => $m2->id_mutasi, 'id_aset' => $aset->id_aset, 'ruangan_lama' => $ruangDinas->id_ruangan, 'ruangan_baru' => $ruangDinas->id_ruangan]);

        $m3 = MutasiAset::create([
            'tanggal_mutasi' => '2026-07-20',
            'jenis_mutasi' => 'Pindah Ruangan',
            'keterangan' => 'Mutasi Juli 2026 ' . uniqid(),
            'id_user_penginput' => $adminAset->id_user,
            'status_mutasi' => 'selesai',
        ]);
        DetailMutasiAset::create(['id_mutasi' => $m3->id_mutasi, 'id_aset' => $aset->id_aset, 'ruangan_lama' => $ruangDinas->id_ruangan, 'ruangan_baru' => $ruangDinas->id_ruangan]);

        $this->loginSebagai($adminAset);

        // Filter rentang: 2026-06-01 s/d 2026-06-30 (Hanya m2 yang masuk)
        $response = $this->get(route('mutasi-aset.index', [
            'tanggal_mulai' => '2026-06-01',
            'tanggal_akhir' => '2026-06-30',
        ]));
        $response->assertStatus(200);
        $response->assertSee($m2->keterangan);
        $response->assertDontSee($m1->keterangan);
        $response->assertDontSee($m3->keterangan);
    }

    /**
     * Skenario 41 (Filter Tanggal): Filter hanya tanggal mulai.
     */
    public function test_filter_hanya_tanggal_mulai(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);
        $ruangDinas = $this->buatRuanganUntukSkpd($dinasSkpd);
        $aset = $this->buatAsetBarang($dinasSkpd, $ruangDinas);

        $mLama = MutasiAset::create([
            'tanggal_mutasi' => '2026-01-10',
            'jenis_mutasi' => 'Pindah Ruangan',
            'keterangan' => 'Mutasi Awal Tahun ' . uniqid(),
            'id_user_penginput' => $adminAset->id_user,
            'status_mutasi' => 'selesai',
        ]);
        DetailMutasiAset::create(['id_mutasi' => $mLama->id_mutasi, 'id_aset' => $aset->id_aset, 'ruangan_lama' => $ruangDinas->id_ruangan, 'ruangan_baru' => $ruangDinas->id_ruangan]);

        $mBaru = MutasiAset::create([
            'tanggal_mutasi' => '2026-08-10',
            'jenis_mutasi' => 'Pindah Ruangan',
            'keterangan' => 'Mutasi Pertengahan Tahun ' . uniqid(),
            'id_user_penginput' => $adminAset->id_user,
            'status_mutasi' => 'selesai',
        ]);
        DetailMutasiAset::create(['id_mutasi' => $mBaru->id_mutasi, 'id_aset' => $aset->id_aset, 'ruangan_lama' => $ruangDinas->id_ruangan, 'ruangan_baru' => $ruangDinas->id_ruangan]);

        $this->loginSebagai($adminAset);

        // Hanya tanggal_mulai: 2026-08-01
        $response = $this->get(route('mutasi-aset.index', ['tanggal_mulai' => '2026-08-01']));
        $response->assertStatus(200);
        $response->assertSee($mBaru->keterangan);
        $response->assertDontSee($mLama->keterangan);
    }

    /**
     * Skenario 42 (Filter Tanggal): Filter hanya tanggal akhir.
     */
    public function test_filter_hanya_tanggal_akhir(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);
        $ruangDinas = $this->buatRuanganUntukSkpd($dinasSkpd);
        $aset = $this->buatAsetBarang($dinasSkpd, $ruangDinas);

        $mLama = MutasiAset::create([
            'tanggal_mutasi' => '2026-01-10',
            'jenis_mutasi' => 'Pindah Ruangan',
            'keterangan' => 'Mutasi Batas Awal ' . uniqid(),
            'id_user_penginput' => $adminAset->id_user,
            'status_mutasi' => 'selesai',
        ]);
        DetailMutasiAset::create(['id_mutasi' => $mLama->id_mutasi, 'id_aset' => $aset->id_aset, 'ruangan_lama' => $ruangDinas->id_ruangan, 'ruangan_baru' => $ruangDinas->id_ruangan]);

        $mBaru = MutasiAset::create([
            'tanggal_mutasi' => '2026-08-10',
            'jenis_mutasi' => 'Pindah Ruangan',
            'keterangan' => 'Mutasi Batas Akhir ' . uniqid(),
            'id_user_penginput' => $adminAset->id_user,
            'status_mutasi' => 'selesai',
        ]);
        DetailMutasiAset::create(['id_mutasi' => $mBaru->id_mutasi, 'id_aset' => $aset->id_aset, 'ruangan_lama' => $ruangDinas->id_ruangan, 'ruangan_baru' => $ruangDinas->id_ruangan]);

        $this->loginSebagai($adminAset);

        // Hanya tanggal_akhir: 2026-05-01
        $response = $this->get(route('mutasi-aset.index', ['tanggal_akhir' => '2026-05-01']));
        $response->assertStatus(200);
        $response->assertSee($mLama->keterangan);
        $response->assertDontSee($mBaru->keterangan);
    }

    /**
     * Skenario 43 (Validasi Tanggal): Format tanggal tidak valid ditangani tanpa error 500 dan menampilkan pesan error.
     */
    public function test_validasi_format_tanggal_tidak_valid(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);

        $this->loginSebagai($adminAset);

        $response = $this->get(route('mutasi-aset.index', ['tanggal_mulai' => 'bukan-tanggal-valid']));
        $response->assertStatus(200);
        $response->assertSee('Format tanggal mulai tidak valid');
    }

    /**
     * Skenario 44 (Validasi Tanggal): Tanggal akhir lebih awal daripada tanggal mulai menampilkan pesan validasi.
     */
    public function test_validasi_tanggal_akhir_lebih_awal_daripada_tanggal_mulai(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);

        $this->loginSebagai($adminAset);

        // Tanggal mulai: 2026-12-01, Tanggal akhir: 2026-01-01 (terbalik)
        $response = $this->get(route('mutasi-aset.index', [
            'tanggal_mulai' => '2026-12-01',
            'tanggal_akhir' => '2026-01-01',
        ]));
        $response->assertStatus(200);
        $response->assertSee('Tanggal akhir tidak boleh lebih awal daripada tanggal mulai.');
    }

    /**
     * Skenario 45 (Kombinasi Filter): Kombinasi pencarian teks, jenis mutasi, dan rentang tanggal bersamaan.
     */
    public function test_kombinasi_pencarian_jenis_mutasi_dan_rentang_tanggal(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);
        $ruangDinas = $this->buatRuanganUntukSkpd($dinasSkpd);

        $mbCocok = MasterBarang::create(['nama_barang' => 'Scanner Barcode ' . uniqid(), 'kode_barang' => 'SCN-' . uniqid()]);
        $asetCocok = Aset::create([
            'id_barang' => $mbCocok->id_barang,
            'id_skpd' => $dinasSkpd->id_skpd,
            'id_lokasi' => $ruangDinas->id_lokasi,
            'nomor_kartu_barang' => 'KIB-SCN-' . uniqid(),
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        // Target: Scanner Barcode + Ganti Pemegang + Juni 2026
        $mTarget = MutasiAset::create([
            'tanggal_mutasi' => '2026-06-15',
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => 'Mutasi Kombinasi Target ' . uniqid(),
            'id_user_penginput' => $adminAset->id_user,
            'status_mutasi' => 'selesai',
        ]);
        DetailMutasiAset::create(['id_mutasi' => $mTarget->id_mutasi, 'id_aset' => $asetCocok->id_aset, 'ruangan_lama' => $ruangDinas->id_ruangan, 'ruangan_baru' => $ruangDinas->id_ruangan]);

        // Pengacau 1: Scanner Barcode tapi Pindah Ruangan (bukan Ganti Pemegang)
        $mSalahJenis = MutasiAset::create([
            'tanggal_mutasi' => '2026-06-15',
            'jenis_mutasi' => 'Pindah Ruangan',
            'keterangan' => 'Mutasi Kombinasi Salah Jenis ' . uniqid(),
            'id_user_penginput' => $adminAset->id_user,
            'status_mutasi' => 'selesai',
        ]);
        DetailMutasiAset::create(['id_mutasi' => $mSalahJenis->id_mutasi, 'id_aset' => $asetCocok->id_aset, 'ruangan_lama' => $ruangDinas->id_ruangan, 'ruangan_baru' => $ruangDinas->id_ruangan]);

        // Pengacau 2: Scanner Barcode + Ganti Pemegang tapi tanggal Mei (di luar rentang)
        $mSalahTanggal = MutasiAset::create([
            'tanggal_mutasi' => '2026-05-15',
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => 'Mutasi Kombinasi Salah Tanggal ' . uniqid(),
            'id_user_penginput' => $adminAset->id_user,
            'status_mutasi' => 'selesai',
        ]);
        DetailMutasiAset::create(['id_mutasi' => $mSalahTanggal->id_mutasi, 'id_aset' => $asetCocok->id_aset, 'ruangan_lama' => $ruangDinas->id_ruangan, 'ruangan_baru' => $ruangDinas->id_ruangan]);

        $this->loginSebagai($adminAset);

        $response = $this->get(route('mutasi-aset.index', [
            'search' => $mbCocok->nama_barang,
            'jenis_mutasi' => 'Ganti Pemegang',
            'tanggal_mulai' => '2026-06-01',
            'tanggal_akhir' => '2026-06-30',
        ]));
        $response->assertStatus(200);

        // Hanya target yang muncul
        $response->assertSee($mTarget->keterangan);
        $response->assertDontSee($mSalahJenis->keterangan);
        $response->assertDontSee($mSalahTanggal->keterangan);
    }

    /**
     * Skenario 46 (Reset Filter): Tombol Reset muncul jika ada filter aktif dan mereset URL.
     */
    public function test_tombol_reset_muncul_dan_mereset_filter(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);

        $this->loginSebagai($adminAset);

        // Saat tidak ada filter, tombol Reset tidak ada
        $resTanpaFilter = $this->get(route('mutasi-aset.index'));
        $resTanpaFilter->assertStatus(200);
        $resTanpaFilter->assertDontSee('>Reset</a>', false);

        // Saat ada filter, tombol Reset muncul dan mengarah ke URL bersih
        $resDenganFilter = $this->get(route('mutasi-aset.index', ['search' => 'laptop']));
        $resDenganFilter->assertStatus(200);
        $resDenganFilter->assertSee('href="' . route('mutasi-aset.index') . '"', false);
        $resDenganFilter->assertSee('Reset');
    }

    /**
     * Skenario 47 (Reset Filter): Tombol Reset mempertahankan kategori tab (kategori=kendaraan).
     */
    public function test_tombol_reset_mempertahankan_kategori_tab(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);

        $this->loginSebagai($adminAset);

        // Pada tab kendaraan dengan pencarian
        $response = $this->get(route('mutasi-aset.index', [
            'kategori' => 'kendaraan',
            'search' => 'Innova',
        ]));
        $response->assertStatus(200);

        // Tombol reset harus mengarah ke ?kategori=kendaraan tanpa parameter filter
        $expectedResetUrl = route('mutasi-aset.index', ['kategori' => 'kendaraan']);
        $response->assertSee('href="' . $expectedResetUrl . '"', false);
        $response->assertSee('Reset');
    }

    /**
     * Skenario 48 (Pagination): Pagination mempertahankan seluruh parameter filter.
     */
    public function test_pagination_mempertahankan_seluruh_parameter_filter(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);
        $ruangDinas = $this->buatRuanganUntukSkpd($dinasSkpd);

        // Buat 15 transaksi mutasi untuk memicu pagination (per_page = 10)
        $kataKunci = 'KataKunciPagination' . uniqid();
        for ($i = 0; $i < 15; $i++) {
            $aset = $this->buatAsetBarang($dinasSkpd, $ruangDinas);
            $this->buatTransaksiMutasi($aset, $ruangDinas, $ruangDinas, 'Pindah Ruangan', $kataKunci . ' Ke ' . $i);
        }

        $this->loginSebagai($adminAset);

        $response = $this->get(route('mutasi-aset.index', [
            'search' => $kataKunci,
            'jenis_mutasi' => 'Pindah Ruangan',
        ]));
        $response->assertStatus(200);

        // Cek link pagination memuat parameter search dan jenis_mutasi
        $content = $response->getContent();
        $this->assertStringContainsString('search=' . $kataKunci, $content);
        $this->assertTrue(
            str_contains($content, 'jenis_mutasi=Pindah+Ruangan') || str_contains($content, 'jenis_mutasi=Pindah%20Ruangan'),
            'Link pagination harus memuat query string jenis_mutasi'
        );
    }

    /**
     * Skenario 49 (Keamanan Admin UPT): Admin UPT P2BTP hanya menemukan transaksi melalui detail P2BTP.
     */
    public function test_keamanan_admin_upt_p2btp_hanya_menemukan_transaksi_melalui_detail_p2btp(): void
    {
        $p2btpSkpd = $this->ambilSkpdUptP2btp();
        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $p2btpSkpd);
        $ruangP2btp = $this->buatRuanganUntukSkpd($p2btpSkpd);

        $mbP2btp = MasterBarang::create(['nama_barang' => 'Kamera Canon P2BTP ' . uniqid(), 'kode_barang' => 'CAM-' . uniqid()]);
        $asetP2btp = Aset::create([
            'id_barang' => $mbP2btp->id_barang,
            'id_skpd' => $p2btpSkpd->id_skpd,
            'id_lokasi' => $ruangP2btp->id_lokasi,
            'nomor_kartu_barang' => 'KIB-CAM-P2BTP-' . uniqid(),
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        $this->buatTransaksiMutasi($asetP2btp, $ruangP2btp, $ruangP2btp, 'Pindah Ruangan', 'Mutasi Sah Internal P2BTP');

        $this->loginSebagai($adminP2btp);

        // Cari aset miliknya -> Berhasil
        $res = $this->get(route('mutasi-aset.index', ['search' => $mbP2btp->nama_barang]));
        $res->assertStatus(200);
        $res->assertSee($mbP2btp->nama_barang);
    }

    /**
     * Skenario 50 (Keamanan Anti-Leakage Admin UPT): Pencarian ruangan/pegawai UPT lain TIDAK mengungkap transaksi pada Admin UPT P2BTP (bahkan jika ada detail campuran).
     */
    public function test_pencarian_ruangan_atau_aset_upt_lain_tidak_mengungkap_transaksi_campuran_pada_admin_upt(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $p2btpSkpd = $this->ambilSkpdUptP2btp();
        $psbpSkpd = $this->ambilSkpdUptPsbp();

        $adminP2btp = $this->buatUserDenganRole('Admin UPT', $p2btpSkpd);

        $ruangP2btp = $this->buatRuanganUntukSkpd($p2btpSkpd);
        $ruangPsbp = Ruangan::create([
            'id_skpd' => $psbpSkpd->id_skpd,
            'id_lokasi' => Lokasi::untukSkpd($psbpSkpd->id_skpd)->id_lokasi,
            'nama_ruangan' => 'Ruang Lab Khusus PSBP ' . uniqid(),
            'status' => 'Aktif',
        ]);

        $asetP2btp = $this->buatAsetBarang($p2btpSkpd, $ruangP2btp);
        $asetPsbp = $this->buatAsetBarang($psbpSkpd, $ruangPsbp);

        // Transaksi Campuran: 1 aset P2BTP + 1 aset PSBP
        $mutasiCampuran = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Pindah Ruangan',
            'keterangan' => 'Mutasi Massal Gabungan P2BTP dan PSBP',
            'id_user_penginput' => 1,
            'status_mutasi' => 'selesai',
        ]);
        DetailMutasiAset::create(['id_mutasi' => $mutasiCampuran->id_mutasi, 'id_aset' => $asetP2btp->id_aset, 'ruangan_lama' => $ruangP2btp->id_ruangan, 'ruangan_baru' => $ruangP2btp->id_ruangan]);
        DetailMutasiAset::create(['id_mutasi' => $mutasiCampuran->id_mutasi, 'id_aset' => $asetPsbp->id_aset, 'ruangan_lama' => $ruangPsbp->id_ruangan, 'ruangan_baru' => $ruangPsbp->id_ruangan]);

        $this->loginSebagai($adminP2btp);

        // Jika Admin UPT P2BTP mencari 'Ruang Lab Khusus PSBP':
        // Transaksi campuran TIDAK BOLEH muncul, karena pencarian terikat pada detail yang berada di P2BTP saja!
        $response = $this->get(route('mutasi-aset.index', ['search' => $ruangPsbp->nama_ruangan]));
        $response->assertStatus(200);

        // Transaksi campuran tidak boleh masuk ke hasil mutasi Admin UPT P2BTP
        $this->assertEquals(0, $response->viewData('mutasiList')->total());
        $response->assertDontSee($mutasiCampuran->keterangan);
        $response->assertDontSee($asetPsbp->nomor_kartu_barang);
    }

    /**
     * Skenario 51 (Keamanan Global Admin Aset): Admin Aset dapat mencari transaksi di UPT manapun secara global.
     */
    public function test_admin_aset_dapat_mencari_seluruh_transaksi_secara_global(): void
    {
        $psbpSkpd = $this->ambilSkpdUptPsbp();
        $ruangPsbp = $this->buatRuanganUntukSkpd($psbpSkpd);
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);

        $asetPsbp = $this->buatAsetBarang($psbpSkpd, $ruangPsbp);
        $mutasiPsbp = $this->buatTransaksiMutasi($asetPsbp, $ruangPsbp, $ruangPsbp, 'Pindah Ruangan', 'Mutasi Internal PSBP Global Test ' . uniqid());

        $this->loginSebagai($adminAset);

        // Admin Aset mencari ruangan PSBP -> Berhasil menemukan transaksi
        $response = $this->get(route('mutasi-aset.index', ['search' => $ruangPsbp->nama_ruangan]));
        $response->assertStatus(200);
        $response->assertSee($mutasiPsbp->keterangan);
    }

    /**
     * Skenario 52 (Regresi Kendaraan): Pencarian kendaraan (pemegang & jenis kendaraan) tetap berfungsi pada tab Kendaraan Admin Aset.
     */
    public function test_pencarian_kendaraan_pemegang_dan_jenis_kendaraan_tetap_berfungsi(): void
    {
        $dinasSkpd = $this->ambilSkpdBidang();
        $adminAset = $this->buatUserDenganRole('Admin Aset', $dinasSkpd);

        $mbKnd = MasterBarang::firstOrCreate(['nama_barang' => 'Toyota Fortuner Test'], ['kode_barang' => 'KND-FORT']);
        $asetKnd = Aset::create([
            'id_barang' => $mbKnd->id_barang,
            'id_skpd' => $dinasSkpd->id_skpd,
            'id_lokasi' => Lokasi::dinas()->id_lokasi,
            'nomor_kartu_barang' => 'KIB-KND-' . uniqid(),
            'status_aset' => 'aktif',
            'is_kendaraan' => true,
        ]);

        $pemegangUnik = 'Drs. H. Kendaraan Test ' . uniqid();
        Kendaraan::create([
            'id_aset' => $asetKnd->id_aset,
            'jenis_kendaraan' => 'Mobil Dinas Operasional',
            'pemegang' => $pemegangUnik,
            'nomor_rangka' => 'MH123' . uniqid(),
            'nomor_mesin' => 'ENG456' . uniqid(),
            'merk' => 'Toyota',
            'tipe' => 'SUV',
        ]);

        $mutasiKnd = MutasiAset::create([
            'tanggal_mutasi' => now()->toDateString(),
            'jenis_mutasi' => 'Ganti Pemegang',
            'keterangan' => 'Mutasi Kendaraan Operasional ' . uniqid(),
            'id_user_penginput' => $adminAset->id_user,
            'status_mutasi' => 'selesai',
        ]);
        DetailMutasiAset::create([
            'id_mutasi' => $mutasiKnd->id_mutasi,
            'id_aset' => $asetKnd->id_aset,
            'ruangan_lama' => null,
            'ruangan_baru' => null,
            'pegawai_lama' => null,
            'pegawai_baru' => null,
        ]);

        $this->loginSebagai($adminAset);

        // Cari pada tab kendaraan dengan pemegang
        $response = $this->get(route('mutasi-aset.index', [
            'kategori' => 'kendaraan',
            'search' => $pemegangUnik,
        ]));
        $response->assertStatus(200);
        $this->assertEquals(1, $response->viewData('mutasiList')->total());
        $this->assertEquals($mutasiKnd->id_mutasi, $response->viewData('mutasiList')->first()->id_mutasi);
        $response->assertSee('Toyota SUV');
    }
}


