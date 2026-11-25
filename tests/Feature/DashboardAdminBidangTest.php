<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Kendaraan;
use App\Models\MasterBarang;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\Role;
use App\Models\Skpd;
use App\Models\User;
use App\Models\UsulanRkbmd;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardAdminBidangTest extends TestCase
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
        return $this->namaDatabasePengujian();
    }

    /**
     * Helper untuk membuat atau mengambil Admin Bidang pada SKPD Bidang tertentu.
     */
    private function buatAdminBidang(int $idSkpd = 4): User
    {
        $roleAdminBidang = Role::firstOrCreate(
            ['nama_role' => 'Admin Bidang'],
            ['deskripsi' => 'Administrator Bidang']
        );

        $skpd = Skpd::find($idSkpd);
        if (! $skpd) {
            $skpd = Skpd::create([
                'id_skpd' => $idSkpd,
                'nama_skpd' => 'Bidang Pengujian ' . $idSkpd,
                'jenis_skpd' => 'Bidang',
            ]);
        }

        $pegawai = Pegawai::create([
            'nama_pegawai' => 'Admin Bidang Tester ' . uniqid(),
            'nip' => '19900101' . rand(100000, 999999),
            'id_skpd' => $skpd->id_skpd,
            'jabatan' => 'Penata Kelola Aset Bidang',
        ]);

        return User::create([
            'username' => 'admin_bidang_' . uniqid(),
            'password' => bcrypt('password123'),
            'id_pegawai' => $pegawai->id_pegawai,
            'id_role' => $roleAdminBidang->id_role,
            'status_user' => 'aktif',
        ]);
    }

    /**
     * 1. Admin Bidang dapat mengakses dashboard dan melihat identitas bidangnya.
     */
    public function test_admin_bidang_dapat_mengakses_dashboard(): void
    {
        $admin = $this->buatAdminBidang(4);
        $this->actingAs($admin);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Dashboard Admin Bidang');
        $response->assertSee($admin->pegawai->skpd->nama_skpd);
        $response->assertSee($admin->pegawai->nama_pegawai);
        $response->assertSee('Lihat Aset Saya');
        $response->assertSee('Informasi Aset Bidang');
        $response->assertSee('Monitoring RKBMD');

        // Section informasi pribadi telah dihapus dari dashboard
        $response->assertDontSee('Informasi Pribadi Admin Bidang');
        $response->assertDontSee('Profil Aparatur');
        $response->assertDontSee('Aset yang Sedang Dipegang Pribadi');
    }

    /**
     * 2. Isolasi Data Aset: Admin Bidang HANYA melihat aset milik Bidangnya (berdasarkan id_skpd).
     * Aset milik Sekretariat dan Bidang lain TIDAK bocor.
     */
    public function test_admin_bidang_hanya_melihat_aset_bidangnya_sendiri(): void
    {
        $adminBidang4 = $this->buatAdminBidang(4); // Bidang Produksi Tanaman Semusim
        $this->actingAs($adminBidang4);

        // Buat aset khusus di Bidang 4
        $asetBidang4 = Aset::create([
            'id_skpd' => 4,
            'id_lokasi' => 1,
            'nomor_kartu_barang' => 'KIB-TEST-BIDANG-4',
            'merk' => 'Laptop Bidang 4 Khusus',
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'nilai_perolehan' => 15000000,
        ]);

        PemegangAset::create([
            'id_aset' => $asetBidang4->id_aset,
            'id_pegawai' => $adminBidang4->id_pegawai,
            'status' => 'aktif',
            'tanggal_mulai' => now()->toDateString(),
        ]);

        // Buat aset di Sekretariat (id_skpd = 1) dan Bidang 2 (id_skpd = 2)
        $asetSekretariat = Aset::create([
            'id_skpd' => 1,
            'id_lokasi' => 1,
            'nomor_kartu_barang' => 'KIB-TEST-SEKRETARIAT',
            'merk' => 'Server Sekretariat Rahasia',
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'nilai_perolehan' => 99000000,
        ]);

        $asetBidang2 = Aset::create([
            'id_skpd' => 2,
            'id_lokasi' => 1,
            'nomor_kartu_barang' => 'KIB-TEST-BIDANG-2',
            'merk' => 'Kendaraan Bidang 2 Rahasia',
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'nilai_perolehan' => 50000000,
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);

        // Aset bidang 4 harus terlihat
        $response->assertSee('KIB-TEST-BIDANG-4');

        // Aset milik unit lain TIDAK boleh terlihat di daftar maupun bocor ke view
        $response->assertDontSee('KIB-TEST-SEKRETARIAT');
        $response->assertDontSee('Server Sekretariat Rahasia');
        $response->assertDontSee('KIB-TEST-BIDANG-2');
        $response->assertDontSee('Kendaraan Bidang 2 Rahasia');

        // Pastikan view data total aset bidang hanya menghitung aset id_skpd 4
        $expectedTotalAset = Aset::where('id_skpd', 4)->whereIn('status_aset', ['aktif', 'Aktif'])->count();
        $this->assertEquals($expectedTotalAset, $response->viewData('totalAsetBidang'));
    }

    /**
     * 3. Tombol "Lihat Aset Saya" membuka aset pribadi Admin Bidang dan parameter URL dikunci
     * sehingga Admin Bidang tidak dapat melihat aset pegawai lain (HTTP 403).
     */
    public function test_admin_bidang_tombol_lihat_aset_saya_dan_pencegahan_akses_pegawai_lain(): void
    {
        $admin = $this->buatAdminBidang(4);
        $this->actingAs($admin);

        // Buat aset dinas yang dipegang pribadi oleh admin ini
        $asetPersonal = Aset::create([
            'id_skpd' => 4,
            'id_lokasi' => 1,
            'nomor_kartu_barang' => 'KIB-PERSONAL-ADMIN',
            'merk' => 'Laptop Kedinasan Admin Personal',
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'nilai_perolehan' => 12000000,
        ]);

        PemegangAset::create([
            'id_aset' => $asetPersonal->id_aset,
            'id_pegawai' => $admin->id_pegawai,
            'status' => 'aktif',
            'tanggal_mulai' => now()->toDateString(),
        ]);

        // Buat pegawai lain beserta aset yang dipegangnya
        $pegawaiLain = Pegawai::create([
            'nama_pegawai' => 'Staf Bidang Lain ' . uniqid(),
            'id_skpd' => 4,
            'nip' => '19950101' . rand(100000, 999999),
        ]);

        $asetPegawaiLain = Aset::create([
            'id_skpd' => 4,
            'id_lokasi' => 1,
            'nomor_kartu_barang' => 'KIB-MILIK-STAF-LAIN',
            'merk' => 'Komputer Staf Lain',
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'nilai_perolehan' => 8000000,
        ]);

        PemegangAset::create([
            'id_aset' => $asetPegawaiLain->id_aset,
            'id_pegawai' => $pegawaiLain->id_pegawai,
            'status' => 'aktif',
            'tanggal_mulai' => now()->toDateString(),
        ]);

        // A. Cek pada Dashboard: tombol "Lihat Aset Saya" mengarah ke profil sendiri
        $dashboardResponse = $this->get(route('dashboard'));
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('Lihat Aset Saya');
        $dashboardResponse->assertSee(route('aset-barang.show', $admin->id_pegawai));
        $dashboardResponse->assertSee($admin->pegawai->nama_pegawai);

        // Section informasi pribadi tidak ada di dashboard
        $dashboardResponse->assertDontSee('Informasi Pribadi Admin Bidang');
        $dashboardResponse->assertDontSee('Aset yang Sedang Dipegang Pribadi');

        // B. Akses halaman "Lihat Aset Saya" (aset-barang.show untuk profil sendiri)
        $showOwnResponse = $this->get(route('aset-barang.show', $admin->id_pegawai));
        $showOwnResponse->assertStatus(200);
        $showOwnResponse->assertSee('KIB-PERSONAL-ADMIN');
        $showOwnResponse->assertSee('Laptop Kedinasan Admin Personal');

        // C. Percobaan manipulasi parameter URL: Admin Bidang mencoba membuka aset pegawai lain -> 403 Forbidden!
        $showOtherResponse = $this->get(route('aset-barang.show', $pegawaiLain->id_pegawai));
        $showOtherResponse->assertStatus(403);
    }

    /**
     * 4. SPPBI telah DIHILANGKAN dari Dashboard Admin Bidang.
     */
    public function test_dashboard_admin_bidang_tidak_memuat_informasi_sppbi(): void
    {
        $admin = $this->buatAdminBidang(4);
        $this->actingAs($admin);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('SPPBI');
        $response->assertDontSee('Surat Pernyataan Penggunaan Barang');
        $this->assertArrayNotHasKey('sppbiSaya', $response->viewData());
    }

    /**
     * 5. Monitoring RKBMD Bidang difilter berdasarkan usulan_rkbmd.id_skpd dan menghitung 4 status aktual:
     * Draft, Diajukan, Disetujui Pengurus Barang, dan Ditolak.
     */
    public function test_monitoring_rkbmd_bidang_menghitung_4_status_aktual_sesuai_id_skpd(): void
    {
        $admin = $this->buatAdminBidang(4);
        $this->actingAs($admin);

        // Buat usulan pada Bidang 4 untuk masing-masing status
        UsulanRkbmd::create([
            'id_skpd' => 4,
            'id_pegawai' => $admin->id_pegawai,
            'tahun_anggaran' => 2026,
            'jenis_usulan' => 'Pengadaan',
            'nama_barang' => 'Drone Pertanian Bidang 4',
            'jumlah' => 1,
            'satuan' => 'Unit',
            'status_usulan' => 'Draft',
        ]);

        UsulanRkbmd::create([
            'id_skpd' => 4,
            'id_pegawai' => $admin->id_pegawai,
            'tahun_anggaran' => 2026,
            'jenis_usulan' => 'Pengadaan',
            'nama_barang' => 'Mesin Pengering Bidang 4',
            'jumlah' => 2,
            'satuan' => 'Unit',
            'status_usulan' => 'Diajukan',
        ]);

        UsulanRkbmd::create([
            'id_skpd' => 4,
            'id_pegawai' => $admin->id_pegawai,
            'tahun_anggaran' => 2026,
            'jenis_usulan' => 'Pemeliharaan',
            'nama_barang' => 'Traktor Bidang 4',
            'jumlah' => 1,
            'satuan' => 'Unit',
            'status_usulan' => 'Disetujui Pengurus Barang',
        ]);

        UsulanRkbmd::create([
            'id_skpd' => 4,
            'id_pegawai' => $admin->id_pegawai,
            'tahun_anggaran' => 2026,
            'jenis_usulan' => 'Pengadaan',
            'nama_barang' => 'Kendaraan Operasional Ditolak',
            'jumlah' => 1,
            'satuan' => 'Unit',
            'status_usulan' => 'Ditolak',
            'catatan_pengurus' => 'Belum prioritas',
        ]);

        // Buat usulan di SKPD lain (misal Sekretariat / UPT) yang TIDAK boleh ikut terhitung
        UsulanRkbmd::create([
            'id_skpd' => 1,
            'id_pegawai' => $admin->id_pegawai,
            'tahun_anggaran' => 2026,
            'jenis_usulan' => 'Pengadaan',
            'nama_barang' => 'Genset Sekretariat Lain',
            'jumlah' => 1,
            'satuan' => 'Unit',
            'status_usulan' => 'Draft',
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);

        $rkbmdStats = $response->viewData('rkbmdStats');
        $this->assertEquals(4, $rkbmdStats->total);
        $this->assertEquals(1, $rkbmdStats->draft);
        $this->assertEquals(1, $rkbmdStats->diajukan);
        $this->assertEquals(1, $rkbmdStats->disetujui);
        $this->assertEquals(1, $rkbmdStats->ditolak);

        // Section tabel usulan berulang di bawah telah dihapus
        $response->assertDontSee('Usulan RKBMD Bidang Terbaru');
    }

    /**
     * 6. Pengingat usulan Draft muncul jika draft > 0, dan menampilkan banner bersih jika draft = 0.
     */
    public function test_pengingat_usulan_draft_muncul_sesuai_kondisi(): void
    {
        $admin = $this->buatAdminBidang(4);
        $this->actingAs($admin);

        // Kasus A: Ada usulan draft
        $usulanDraft = UsulanRkbmd::create([
            'id_skpd' => 4,
            'id_pegawai' => $admin->id_pegawai,
            'tahun_anggaran' => 2026,
            'jenis_usulan' => 'Pengadaan',
            'nama_barang' => 'Komputer Kerja Draf',
            'jumlah' => 1,
            'satuan' => 'Unit',
            'status_usulan' => 'Draft',
        ]);

        $responseWithDraft = $this->get(route('dashboard'));
        $responseWithDraft->assertStatus(200);
        $responseWithDraft->assertSee('Pengingat Usulan Draft Belum Diajukan');
        $responseWithDraft->assertSee('Ajukan Usulan Draft');

        // Kasus B: Tidak ada draf (status diubah jadi Diajukan)
        $usulanDraft->update(['status_usulan' => 'Diajukan']);

        $responseNoDraft = $this->get(route('dashboard'));
        $responseNoDraft->assertStatus(200);
        $responseNoDraft->assertSee('Status RKBMD Bersih');
        $responseNoDraft->assertDontSee('Pengingat Usulan Draft Belum Diajukan');
    }

    /**
     * 7. Dashboard aman dan bebas error saat data aset dan RKBMD masih kosong (Null-safety & zero-division).
     */
    public function test_dashboard_admin_bidang_aman_saat_data_kosong(): void
    {
        // Gunakan Bidang 5 (Bidang Produksi Tanaman Tahunan) yang kosong
        $adminBidang5 = $this->buatAdminBidang(5);
        $this->actingAs($adminBidang5);

        // Bersihkan data dummy aset/rkbmd jika ada di SKPD 5
        Aset::where('id_skpd', 5)->delete();
        UsulanRkbmd::where('id_skpd', 5)->delete();

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $this->assertEquals(0, $response->viewData('totalAsetBidang'));
        $this->assertEquals(0, $response->viewData('nilaiAsetBidang'));
        $this->assertEquals(0, $response->viewData('asetBaikBidang'));
        $this->assertEquals(0, $response->viewData('rkbmdDraftCount'));
        $this->assertEquals(0, $response->viewData('rkbmdStats')->total);
        $response->assertSee('Belum ada pemegang aset yang tercatat di bidang ini');
        $response->assertSee('Status RKBMD Bersih');
        $response->assertSee('Lihat Aset Saya');
        $response->assertDontSee('Informasi Pribadi Admin Bidang');
    }

    /**
     * 8. Akun Admin Bidang tanpa relasi pegawai atau SKPD yang valid ditolak dengan aman (HTTP 403).
     */
    public function test_admin_bidang_tanpa_pegawai_atau_skpd_valid_ditolak_403(): void
    {
        $roleAdminBidang = Role::firstOrCreate(['nama_role' => 'Admin Bidang']);

        // Akun dengan pegawai tetapi id_skpd NULL
        $pegawaiTanpaSkpd = Pegawai::create([
            'nama_pegawai' => 'Pegawai Tanpa Unit',
            'id_skpd' => null,
            'nip' => '19999998' . rand(100000, 999999),
        ]);

        $userTanpaSkpd = User::create([
            'username' => 'tanpa_skpd_' . uniqid(),
            'password' => bcrypt('password123'),
            'id_pegawai' => $pegawaiTanpaSkpd->id_pegawai,
            'id_role' => $roleAdminBidang->id_role,
            'status_user' => 'aktif',
        ]);

        $this->actingAs($userTanpaSkpd);
        $this->get(route('dashboard'))->assertStatus(403);

        // Akun dengan pegawai tetapi SKPD-nya adalah UPT (bukan Bidang)
        $skpdUpt = Skpd::where('jenis_skpd', 'UPT')->first() ?? Skpd::find(6);
        $pegawaiUpt = Pegawai::create([
            'nama_pegawai' => 'Pegawai Salah Penempatan',
            'id_skpd' => $skpdUpt->id_skpd,
            'nip' => '19999999' . rand(100000, 999999),
        ]);

        $userSalahSkpd = User::create([
            'username' => 'salah_skpd_' . uniqid(),
            'password' => bcrypt('password123'),
            'id_pegawai' => $pegawaiUpt->id_pegawai,
            'id_role' => $roleAdminBidang->id_role,
            'status_user' => 'aktif',
        ]);

        $this->actingAs($userSalahSkpd);
        $this->get(route('dashboard'))->assertStatus(403);
    }

    /**
     * 9. Role tak dikenal atau tanpa role ditolak (HTTP 403) dan TIDAK PERNAH masuk ke handler Admin Bidang atau Pegawai.
     */
    public function test_role_tak_dikenal_ditolak_403(): void
    {
        $roleAnomali = Role::create([
            'nama_role' => 'Role Non Eksis ' . uniqid(),
            'deskripsi' => 'Role Tidak Sah',
        ]);

        $pegawai = Pegawai::create([
            'nama_pegawai' => 'Pegawai Anomali',
            'id_skpd' => 1,
            'nip' => '19888888' . rand(100000, 999999),
        ]);

        $userAnomali = User::create([
            'username' => 'user_anomali_' . uniqid(),
            'password' => bcrypt('password123'),
            'id_pegawai' => $pegawai->id_pegawai,
            'id_role' => $roleAnomali->id_role,
            'status_user' => 'aktif',
        ]);

        $this->actingAs($userAnomali);
        $response = $this->get(route('dashboard'));

        $response->assertStatus(403);
    }
}
