<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Kendaraan;
use App\Models\MasterBarang;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Skpd;
use App\Models\User;
use App\Models\UsulanRkbmd;
use App\Services\AsetScope;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardAdminUptTest extends TestCase
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

    /**
     * Helper login dengan me-reset singleton AsetScope.
     */
    private function loginSebagai(User $user): void
    {
        app()->forgetInstance(AsetScope::class);
        $this->actingAs($user);
    }

    /**
     * Helper untuk membuat Admin UPT pada SKPD UPT tertentu.
     */
    private function buatAdminUpt(int $idSkpd = 6, ?string $namaPegawai = null): User
    {
        $roleAdminUpt = Role::firstOrCreate(
            ['nama_role' => 'Admin UPT'],
            ['deskripsi' => 'Administrator UPT']
        );

        $skpd = Skpd::find($idSkpd);
        if (! $skpd) {
            $skpd = Skpd::create([
                'id_skpd' => $idSkpd,
                'nama_skpd' => $idSkpd === 6 ? 'UPT P2BTP' : ($idSkpd === 7 ? 'UPT PSBP' : 'UPT Tester ' . $idSkpd),
                'jenis_skpd' => 'UPT',
            ]);
        }

        $ruangan = Ruangan::firstOrCreate(
            ['nama_ruangan' => 'Ruang Administrasi UPT ' . $idSkpd],
            ['id_skpd' => $idSkpd, 'status' => 'Aktif']
        );

        $pegawai = Pegawai::create([
            'nama_pegawai' => $namaPegawai ?? ('Admin UPT Tester ' . uniqid()),
            'nip' => '19950101' . rand(100000, 999999),
            'id_skpd' => $skpd->id_skpd,
            'id_ruangan' => $ruangan->id_ruangan,
            'jabatan' => 'Pengadministrasi Aset UPT',
        ]);

        return User::create([
            'username' => 'admin_upt_' . uniqid(),
            'password' => bcrypt('password123'),
            'id_pegawai' => $pegawai->id_pegawai,
            'id_role' => $roleAdminUpt->id_role,
            'status_user' => 'aktif',
        ]);
    }

    /**
     * 1. Nama pegawai muncul tepat di bawah nama UPT pada header dashboard.
     */
    public function test_nama_pegawai_muncul_tepat_di_bawah_nama_upt_pada_header(): void
    {
        $adminP2btp = $this->buatAdminUpt(6, 'Abdullah Musa, S.P., M.IP');
        $this->loginSebagai($adminP2btp);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Dashboard Unit Kerja');
        $response->assertSee('UPT P2BTP');
        $response->assertSee('Abdullah Musa, S.P., M.IP');
        $response->assertSee('Ringkasan aset yang dipegang pegawai di lingkungan unit kerja ini.');

        // Pastikan urutan teks: UPT P2BTP -> Abdullah Musa -> Ringkasan aset
        $content = $response->getContent();
        $posHero = strpos($content, 'Dashboard Unit Kerja');
        $this->assertNotFalse($posHero, 'Harus memuat header Dashboard Unit Kerja');

        $posUpt = strpos($content, 'UPT P2BTP', $posHero);
        $posNama = strpos($content, 'Abdullah Musa, S.P., M.IP', $posHero);
        $posDesc = strpos($content, 'Ringkasan aset yang dipegang pegawai di lingkungan unit kerja ini.', $posHero);

        $this->assertTrue($posUpt !== false && $posNama !== false && $posDesc !== false);
        $this->assertTrue($posUpt < $posNama, 'Nama pegawai harus berada di bawah nama UPT');
        $this->assertTrue($posNama < $posDesc, 'Deskripsi harus berada di bawah nama pegawai');
    }

    /**
     * 2. Nama pegawai bersifat dinamis sesuai akun yang sedang login.
     */
    public function test_nama_pegawai_dinamis_sesuai_akun_login(): void
    {
        $admin1 = $this->buatAdminUpt(6, 'Budi Santoso, S.ST');
        $admin2 = $this->buatAdminUpt(7, 'Siti Rahmawati, S.P');

        // Login sebagai Admin 1
        $this->loginSebagai($admin1);
        $res1 = $this->get(route('dashboard'));
        $res1->assertStatus(200);
        $res1->assertSee('Budi Santoso, S.ST');
        $res1->assertDontSee('Siti Rahmawati, S.P');

        // Login sebagai Admin 2
        $this->loginSebagai($admin2);
        $res2 = $this->get(route('dashboard'));
        $res2->assertStatus(200);
        $res2->assertSee('Siti Rahmawati, S.P');
        $res2->assertDontSee('Budi Santoso, S.ST');
    }

    /**
     * 3. Label tombol pada header berubah menjadi "Lihat Aset Saya" (bukan "Lihat Data Aset").
     */
    public function test_label_tombol_berubah_menjadi_lihat_aset_saya(): void
    {
        $admin = $this->buatAdminUpt(6);
        $this->loginSebagai($admin);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Lihat Aset Saya');
        $response->assertDontSee('Lihat Data Aset');

        // Pastikan mengarah ke route aset-barang.show milik pegawai login
        $targetUrl = route('aset-barang.show', $admin->id_pegawai);
        $response->assertSee($targetUrl);
    }

    /**
     * 4. Tombol "Lihat Aset Saya" membuka halaman detail aset milik pegawai login.
     */
    public function test_tombol_lihat_aset_saya_membuka_halaman_detail_aset_pribadi(): void
    {
        $admin = $this->buatAdminUpt(6, 'Ahmad Fadhil, S.P');
        $this->loginSebagai($admin);

        // Buat aset yang dipegang oleh admin
        $barang = MasterBarang::firstOrCreate(
            ['kode_barang' => 'BRG-PRIBADI-TEST'],
            ['nama_barang' => 'Laptop Kedinasan Admin UPT']
        );

        $aset = Aset::create([
            'id_barang' => $barang->id_barang,
            'id_skpd' => 6,
            'nomor_kartu_barang' => 'KIB-LAPTOP-PRIBADI-' . uniqid(),
            'merk' => 'Lenovo ThinkPad',
            'nilai_perolehan' => 15000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        PemegangAset::create([
            'id_aset' => $aset->id_aset,
            'id_pegawai' => $admin->id_pegawai,
            'status' => 'aktif',
        ]);

        // Kunjungi aset-barang.show untuk pegawai admin
        $response = $this->get(route('aset-barang.show', $admin->id_pegawai));

        $response->assertStatus(200);
        $response->assertSee('Aset Ahmad Fadhil, S.P');
        $response->assertSee($aset->nomor_kartu_barang);
        $response->assertSee('Laptop Kedinasan Admin UPT');
        $response->assertSee('Lenovo ThinkPad');
    }

    /**
     * 5. Halaman aset pribadi menampilkan empty state yang informatif bila belum ada aset.
     */
    public function test_halaman_aset_pribadi_menampilkan_empty_state_bila_belum_ada_aset(): void
    {
        $admin = $this->buatAdminUpt(6, 'Admin Tanpa Aset');
        $this->loginSebagai($admin);

        $response = $this->get(route('aset-barang.show', $admin->id_pegawai));

        $response->assertStatus(200);
        $response->assertSee('Aset Admin Tanpa Aset');
        $response->assertSee('Belum ada aset yang dipegang pegawai ini.');
        $response->assertSee('Belum ada kendaraan yang dipegang pegawai ini.');
    }

    /**
     * 6. Backend menolak manipulasi URL: Admin UPT tidak dapat melihat aset pegawai lain.
     */
    public function test_backend_menolak_manipulasi_url_melihat_aset_pegawai_lain(): void
    {
        $admin = $this->buatAdminUpt(6, 'Admin Utama');
        $pegawaiLain = Pegawai::create([
            'nama_pegawai' => 'Pegawai Kolega ' . uniqid(),
            'nip' => '19980101' . rand(100000, 999999),
            'id_skpd' => 6,
        ]);

        $this->loginSebagai($admin);

        // Akses aset pribadi sendiri -> 200 OK
        $resSendiri = $this->get(route('aset-barang.show', $admin->id_pegawai));
        $resSendiri->assertStatus(200);

        // Manipulasi URL ke id_pegawai lain -> 403 Forbidden
        $resLain = $this->get(route('aset-barang.show', $pegawaiLain->id_pegawai));
        $resLain->assertStatus(403);
    }

    /**
     * 7. Backend menolak manipulasi URL antar-UPT: P2BTP tidak dapat membuka aset PSBP.
     */
    public function test_backend_menolak_manipulasi_url_antar_upt(): void
    {
        $adminP2btp = $this->buatAdminUpt(6, 'Admin P2BTP');
        $adminPsbp = $this->buatAdminUpt(7, 'Admin PSBP');

        $this->loginSebagai($adminP2btp);

        // Mencoba membuka aset Admin PSBP
        $response = $this->get(route('aset-barang.show', $adminPsbp->id_pegawai));
        $response->assertStatus(403);
    }

    /**
     * 8. Scope pemisahan data aset UPT P2BTP pada dashboard.
     */
    public function test_scope_pemisahan_data_p2btp_pada_dashboard(): void
    {
        $adminP2btp = $this->buatAdminUpt(6);
        $adminPsbp = $this->buatAdminUpt(7);

        $barang = MasterBarang::firstOrCreate(
            ['kode_barang' => 'BRG-P2BTP-EKS'],
            ['nama_barang' => 'Perangkat Khusus P2BTP']
        );

        $lokasiP2btp = \App\Models\Lokasi::untukSkpd(6);
        $lokasiPsbp = \App\Models\Lokasi::untukSkpd(7);

        $asetP2btp = Aset::create([
            'id_barang' => $barang->id_barang,
            'id_skpd' => 6,
            'id_lokasi' => $lokasiP2btp?->id_lokasi,
            'nomor_kartu_barang' => 'KIB-P2BTP-ISO-' . uniqid(),
            'merk' => 'Merk P2BTP Eksklusif',
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
        ]);

        $asetPsbp = Aset::create([
            'id_barang' => $barang->id_barang,
            'id_skpd' => 7,
            'id_lokasi' => $lokasiPsbp?->id_lokasi,
            'nomor_kartu_barang' => 'KIB-PSBP-ISO-' . uniqid(),
            'merk' => 'Merk PSBP Eksklusif',
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
        ]);

        PemegangAset::create([
            'id_aset' => $asetP2btp->id_aset,
            'id_pegawai' => $adminP2btp->id_pegawai,
            'status' => 'aktif',
        ]);

        PemegangAset::create([
            'id_aset' => $asetPsbp->id_aset,
            'id_pegawai' => $adminPsbp->id_pegawai,
            'status' => 'aktif',
        ]);

        $this->loginSebagai($adminP2btp);
        $resP2btp = $this->get(route('dashboard'));
        $resP2btp->assertStatus(200);
        $resP2btp->assertSee($asetP2btp->nomor_kartu_barang);
        $resP2btp->assertDontSee($asetPsbp->nomor_kartu_barang);
    }

    /**
     * 9. Scope pemisahan data aset UPT PSBP pada dashboard.
     */
    public function test_scope_pemisahan_data_psbp_pada_dashboard(): void
    {
        $adminP2btp = $this->buatAdminUpt(6);
        $adminPsbp = $this->buatAdminUpt(7);

        $barang = MasterBarang::firstOrCreate(
            ['kode_barang' => 'BRG-PSBP-EKS'],
            ['nama_barang' => 'Perangkat Khusus PSBP']
        );

        $lokasiP2btp = \App\Models\Lokasi::untukSkpd(6);
        $lokasiPsbp = \App\Models\Lokasi::untukSkpd(7);

        $asetP2btp = Aset::create([
            'id_barang' => $barang->id_barang,
            'id_skpd' => 6,
            'id_lokasi' => $lokasiP2btp?->id_lokasi,
            'nomor_kartu_barang' => 'KIB-P2BTP-ISO2-' . uniqid(),
            'merk' => 'Merk P2BTP Eksklusif 2',
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
        ]);

        $asetPsbp = Aset::create([
            'id_barang' => $barang->id_barang,
            'id_skpd' => 7,
            'id_lokasi' => $lokasiPsbp?->id_lokasi,
            'nomor_kartu_barang' => 'KIB-PSBP-ISO2-' . uniqid(),
            'merk' => 'Merk PSBP Eksklusif 2',
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
        ]);

        PemegangAset::create([
            'id_aset' => $asetP2btp->id_aset,
            'id_pegawai' => $adminP2btp->id_pegawai,
            'status' => 'aktif',
        ]);

        PemegangAset::create([
            'id_aset' => $asetPsbp->id_aset,
            'id_pegawai' => $adminPsbp->id_pegawai,
            'status' => 'aktif',
        ]);

        $this->loginSebagai($adminPsbp);
        $resPsbp = $this->get(route('dashboard'));
        $resPsbp->assertStatus(200);
        $resPsbp->assertSee($asetPsbp->nomor_kartu_barang);
        $resPsbp->assertDontSee($asetP2btp->nomor_kartu_barang);
    }

    /**
     * 9. Desain dashboard tetap mempertahankan seluruh metrik bento dan status RKBMD.
     */
    public function test_desain_dashboard_mempertahankan_seluruh_metrik_bento(): void
    {
        $admin = $this->buatAdminUpt(6);
        $this->loginSebagai($admin);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Aset Unit');
        $response->assertSee('Nilai Perolehan');
        $response->assertSee('Kendaraan');
        $response->assertSee('Pegawai Pemegang');
        $response->assertSee('Kondisi Aset Unit');
        $response->assertSee('Kondisi Baik');
        $response->assertSee('Rusak Ringan');
        $response->assertSee('Rusak Berat');
        $response->assertSee('Aset Dipegang Pegawai Unit');
        $response->assertSee('Usulan RKBMD Unit');
        $response->assertSee('Draft');
        $response->assertSee('Diajukan');
        $response->assertSee('Disetujui');
        $response->assertSee('Ditolak');
    }

    /**
     * Helper untuk membuat akun Admin Aset di Sekretariat.
     */
    private function buatAdminAset(): User
    {
        $roleAdminAset = Role::firstOrCreate(
            ['nama_role' => 'Admin Aset'],
            ['deskripsi' => 'Administrator Aset']
        );

        $skpdSekretariat = Skpd::firstOrCreate(
            ['id_skpd' => 1],
            ['nama_skpd' => 'Sekretariat', 'jenis_skpd' => 'Sekretariat']
        );

        $pegawai = Pegawai::create([
            'nama_pegawai' => 'Admin Aset Tester ' . uniqid(),
            'nip' => '19880101' . rand(100000, 999999),
            'id_skpd' => $skpdSekretariat->id_skpd,
            'jabatan' => 'Pengurus Barang',
        ]);

        return User::create([
            'username' => 'admin_aset_' . uniqid(),
            'password' => bcrypt('password123'),
            'id_pegawai' => $pegawai->id_pegawai,
            'id_role' => $roleAdminAset->id_role,
            'status_user' => 'aktif',
        ]);
    }

    /**
     * Helper membuat data usulan RKBMD.
     */
    private function buatUsulanRkbmd(int $idSkpd, ?int $idPegawai, string $status = 'Diajukan', string $namaBarang = 'Barang Uji'): UsulanRkbmd
    {
        return UsulanRkbmd::create([
            'id_skpd' => $idSkpd,
            'id_pegawai' => $idPegawai,
            'tahun_anggaran' => (int) now()->format('Y') + 1,
            'jenis_usulan' => 'Pengadaan',
            'nama_barang' => $namaBarang,
            'kode_barang' => '1.3.2.10.02.001',
            'spesifikasi' => 'Spesifikasi Pengujian Dashboard',
            'jumlah' => 2,
            'satuan' => 'Unit',
            'status_usulan' => $status,
            'tanggal_usulan' => now()->toDateString(),
        ]);
    }

    /**
     * 10. Usulan dibuat Admin Aset dengan id_skpd UPT P2BTP terhitung pada dashboard P2BTP.
     */
    public function test_usulan_dibuat_admin_aset_untuk_p2btp_terhitung_pada_dashboard_p2btp(): void
    {
        $adminAset = $this->buatAdminAset();
        $adminP2btp = $this->buatAdminUpt(6, 'Admin P2BTP Verifikasi');

        $namaBarang = 'Laptop Kedinasan P2BTP ' . uniqid();
        $usulan = $this->buatUsulanRkbmd(6, $adminAset->id_pegawai, 'Diajukan', $namaBarang);

        $this->loginSebagai($adminP2btp);
        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee($namaBarang);

        $rkbmdUnitStatuses = $response->viewData('rkbmdUnitStatuses');
        $this->assertGreaterThanOrEqual(1, $rkbmdUnitStatuses['Diajukan'] ?? 0);

        $rkbmdUnitPending = $response->viewData('rkbmdUnitPending');
        $this->assertTrue($rkbmdUnitPending->pluck('id_usulan')->contains($usulan->id_usulan));
    }

    /**
     * 11. Usulan dibuat Admin Aset dengan id_skpd UPT PSBP terhitung pada dashboard PSBP.
     */
    public function test_usulan_dibuat_admin_aset_untuk_psbp_terhitung_pada_dashboard_psbp(): void
    {
        $adminAset = $this->buatAdminAset();
        $adminPsbp = $this->buatAdminUpt(7, 'Admin PSBP Verifikasi');

        $namaBarang = 'Mesin Sortasi Benih PSBP ' . uniqid();
        $usulan = $this->buatUsulanRkbmd(7, $adminAset->id_pegawai, 'Diajukan', $namaBarang);

        $this->loginSebagai($adminPsbp);
        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee($namaBarang);

        $rkbmdUnitStatuses = $response->viewData('rkbmdUnitStatuses');
        $this->assertGreaterThanOrEqual(1, $rkbmdUnitStatuses['Diajukan'] ?? 0);

        $rkbmdUnitPending = $response->viewData('rkbmdUnitPending');
        $this->assertTrue($rkbmdUnitPending->pluck('id_usulan')->contains($usulan->id_usulan));
    }

    /**
     * 12. Isolasi usulan antar-UPT: Usulan P2BTP tidak muncul di PSBP, dan sebaliknya.
     */
    public function test_isolasi_usulan_rkbmd_antar_upt_p2btp_dan_psbp(): void
    {
        $adminAset = $this->buatAdminAset();
        $adminP2btp = $this->buatAdminUpt(6, 'Admin P2BTP Isolasi');
        $adminPsbp = $this->buatAdminUpt(7, 'Admin PSBP Isolasi');

        $barangP2btp = 'Alat Lab Khusus P2BTP ' . uniqid();
        $barangPsbp = 'Timbangan Presisi Khusus PSBP ' . uniqid();

        $usulanP2btp = $this->buatUsulanRkbmd(6, $adminAset->id_pegawai, 'Diajukan', $barangP2btp);
        $usulanPsbp = $this->buatUsulanRkbmd(7, $adminAset->id_pegawai, 'Diajukan', $barangPsbp);

        // Dashboard P2BTP: harus melihat barang P2BTP, tidak melihat barang PSBP
        $this->loginSebagai($adminP2btp);
        $resP2btp = $this->get(route('dashboard'));
        $resP2btp->assertStatus(200);
        $resP2btp->assertSee($barangP2btp);
        $resP2btp->assertDontSee($barangPsbp);
        $this->assertTrue($resP2btp->viewData('rkbmdUnitPending')->pluck('id_usulan')->contains($usulanP2btp->id_usulan));
        $this->assertFalse($resP2btp->viewData('rkbmdUnitPending')->pluck('id_usulan')->contains($usulanPsbp->id_usulan));

        // Dashboard PSBP: harus melihat barang PSBP, tidak melihat barang P2BTP
        $this->loginSebagai($adminPsbp);
        $resPsbp = $this->get(route('dashboard'));
        $resPsbp->assertStatus(200);
        $resPsbp->assertSee($barangPsbp);
        $resPsbp->assertDontSee($barangP2btp);
        $this->assertTrue($resPsbp->viewData('rkbmdUnitPending')->pluck('id_usulan')->contains($usulanPsbp->id_usulan));
        $this->assertFalse($resPsbp->viewData('rkbmdUnitPending')->pluck('id_usulan')->contains($usulanP2btp->id_usulan));
    }

    /**
     * 13. Usulan yang dibuat Admin UPT untuk SKPD-nya sendiri tetap terhitung.
     */
    public function test_usulan_dibuat_admin_upt_untuk_skpd_sendiri_tetap_terhitung(): void
    {
        $adminP2btp = $this->buatAdminUpt(6, 'Admin P2BTP Pembuat');
        $namaBarang = 'Drone Pengawas Lahan ' . uniqid();

        $usulan = $this->buatUsulanRkbmd(6, $adminP2btp->id_pegawai, 'Diajukan', $namaBarang);

        $this->loginSebagai($adminP2btp);
        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee($namaBarang);
        $this->assertTrue($response->viewData('rkbmdUnitPending')->pluck('id_usulan')->contains($usulan->id_usulan));
    }

    /**
     * 14. Usulan dari SKPD lain (Bidang / Sekretariat) tidak ikut terhitung di dashboard UPT.
     */
    public function test_usulan_dari_skpd_lain_tidak_ikut_terhitung_di_dashboard_upt(): void
    {
        $adminAset = $this->buatAdminAset();
        $adminP2btp = $this->buatAdminUpt(6, 'Admin P2BTP Cek Luar');

        $barangBidang = 'Bibit Sawit Bidang Produksi ' . uniqid();
        $barangSekretariat = 'Komputer Server Sekretariat ' . uniqid();

        // Usulan untuk SKPD 2 (Bidang) dan SKPD 1 (Sekretariat)
        $usulanBidang = $this->buatUsulanRkbmd(2, $adminAset->id_pegawai, 'Diajukan', $barangBidang);
        $usulanSekretariat = $this->buatUsulanRkbmd(1, $adminAset->id_pegawai, 'Diajukan', $barangSekretariat);

        $this->loginSebagai($adminP2btp);
        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee($barangBidang);
        $response->assertDontSee($barangSekretariat);

        $pendingIds = $response->viewData('rkbmdUnitPending')->pluck('id_usulan');
        $this->assertFalse($pendingIds->contains($usulanBidang->id_usulan));
        $this->assertFalse($pendingIds->contains($usulanSekretariat->id_usulan));
    }

    /**
     * 15. Statistik berdasarkan status usulan RKBMD unit tetap akurat.
     */
    public function test_statistik_berdasarkan_status_usulan_rkbmd_unit_akurat(): void
    {
        $skpdId = 6;
        $admin = $this->buatAdminUpt($skpdId, 'Admin P2BTP Statistik');

        // Buat kombinasi status usulan di SKPD 6
        $uDraft = $this->buatUsulanRkbmd($skpdId, $admin->id_pegawai, 'Draft', 'Barang Draft ' . uniqid());
        $uDiajukan1 = $this->buatUsulanRkbmd($skpdId, $admin->id_pegawai, 'Diajukan', 'Barang Diajukan 1 ' . uniqid());
        $uDiajukan2 = $this->buatUsulanRkbmd($skpdId, $admin->id_pegawai, 'Diajukan', 'Barang Diajukan 2 ' . uniqid());
        $uDisetujui = $this->buatUsulanRkbmd($skpdId, $admin->id_pegawai, 'Disetujui Pengurus Barang', 'Barang Disetujui ' . uniqid());
        $uDitolak = $this->buatUsulanRkbmd($skpdId, $admin->id_pegawai, 'Ditolak', 'Barang Ditolak ' . uniqid());

        // Usulan di SKPD lain untuk memastikan tidak tercampur dalam agregasi
        $this->buatUsulanRkbmd(7, $admin->id_pegawai, 'Diajukan', 'Barang Luar ' . uniqid());

        $this->loginSebagai($admin);
        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);

        /** @var \Illuminate\Support\Collection $statuses */
        $statuses = $response->viewData('rkbmdUnitStatuses');

        $this->assertGreaterThanOrEqual(1, $statuses['Draft'] ?? 0);
        $this->assertGreaterThanOrEqual(2, $statuses['Diajukan'] ?? 0);
        $this->assertGreaterThanOrEqual(1, $statuses['Disetujui Pengurus Barang'] ?? 0);
        $this->assertGreaterThanOrEqual(1, $statuses['Ditolak'] ?? 0);

        /** @var \Illuminate\Support\Collection $pending */
        $pending = $response->viewData('rkbmdUnitPending');
        $this->assertTrue($pending->pluck('id_usulan')->contains($uDiajukan1->id_usulan));
        $this->assertTrue($pending->pluck('id_usulan')->contains($uDiajukan2->id_usulan));
        $this->assertFalse($pending->pluck('id_usulan')->contains($uDraft->id_usulan));
        $this->assertFalse($pending->pluck('id_usulan')->contains($uDisetujui->id_usulan));
        $this->assertFalse($pending->pluck('id_usulan')->contains($uDitolak->id_usulan));
    }
}
