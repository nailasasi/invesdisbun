<?php

namespace Tests\Feature;

use App\Models\MasterKegiatanRkbmd;
use App\Models\Pegawai;
use App\Models\Role;
use App\Models\Skpd;
use App\Models\User;
use App\Models\UsulanRkbmd;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pengujian Otorisasi Hak Akses Fitur Detail Usulan RKBMD:
 *
 * 1. Super Admin dapat membuka detail usulan.
 * 2. Admin Bidang dapat membuka detail usulan sesuai kewenangannya.
 * 3. Admin UPT dapat membuka detail usulan milik UPT-nya.
 * 4. Admin UPT tidak dapat membuka detail usulan UPT lain.
 * 5. Admin Bidang tidak dapat membuka detail di luar cakupan bidangnya.
 * 6. Admin Bidang tidak dapat menyetujui atau menolak usulan.
 * 7. Admin UPT tidak dapat menyetujui atau menolak usulan.
 * 8. Super Admin tetap dapat menyetujui dan menolak sesuai alur yang berlaku.
 */
class RkbmdDetailOtorisasiTest extends TestCase
{
    private const PASSWORD = 'rahasia-testing-123';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'invesdisbun',
        ]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function buatRole(string $nama): Role
    {
        return Role::firstOrCreate(['nama_role' => $nama]);
    }

    private function buatSkpd(string $nama, string $jenis): Skpd
    {
        return Skpd::firstOrCreate(['nama_skpd' => $nama], ['jenis_skpd' => $jenis]);
    }

    private function buatUser(string $roleNama, ?Skpd $skpd, string $username): User
    {
        $role = $this->buatRole($roleNama);

        $pegawai = Pegawai::create([
            'nip' => '8' . random_int(10000000, 99999999),
            'nama_pegawai' => 'Pegawai ' . $username,
            'jabatan' => 'Staff ' . $roleNama,
            'id_skpd' => $skpd?->id_skpd,
        ]);

        return User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'id_role' => $role->id_role,
            'username' => $username,
            'password' => self::PASSWORD,
            'status_user' => 'aktif',
        ]);
    }

    private function buatUsulan(Skpd $skpd, ?User $creator = null, array $override = []): UsulanRkbmd
    {
        $kegiatan = MasterKegiatanRkbmd::first();

        return UsulanRkbmd::create(array_merge([
            'id_skpd' => $skpd->id_skpd,
            'id_pegawai' => $creator?->pegawai?->id_pegawai,
            'tahun_anggaran' => 2027,
            'jenis_usulan' => 'Pengadaan',
            'id_kegiatan' => $kegiatan?->id_kegiatan,
            'bidang_urusan' => $kegiatan?->bidang_urusan,
            'nama_program' => $kegiatan?->nama_program,
            'nama_sub_kegiatan' => $kegiatan?->nama_sub_kegiatan,
            'program_kegiatan' => $kegiatan ? "{$kegiatan->bidang_urusan} - {$kegiatan->nama_program} - {$kegiatan->nama_sub_kegiatan}" : 'Program Uji',
            'nama_barang' => 'Laptop Testing Detail ' . uniqid(),
            'kode_barang' => '1.3.2.10.02',
            'spesifikasi' => 'RAM 16 GB, SSD 512 GB',
            'jumlah' => 2,
            'satuan' => 'Unit',
            'kebutuhan_maksimum' => 5,
            'kebutuhan_riil' => 2,
            'alasan_kebutuhan' => 'Kebutuhan uji coba otorisasi detail usulan RKBMD.',
            'status_usulan' => 'Diajukan',
            'tanggal_usulan' => now()->toDateString(),
        ], $override));
    }

    /**
     * 1. Super Admin dapat membuka detail usulan (baik usulan milik Bidang maupun UPT).
     */
    public function test_super_admin_dapat_membuka_detail_seluruh_usulan(): void
    {
        $superAdmin = $this->buatUser('Super Admin', null, 'superadmin_test');
        $skpdBidang = $this->buatSkpd('Bidang Perlindungan Perkebunan', 'Bidang');
        $skpdUpt = $this->buatSkpd('UPT P2BTP', 'UPT');

        $usulanBidang = $this->buatUsulan($skpdBidang);
        $usulanUpt = $this->buatUsulan($skpdUpt);

        // Akses detail usulan Bidang
        $response1 = $this->actingAs($superAdmin)
            ->get(route('rkbmd.show', $usulanBidang->id_usulan));
        $response1->assertOk();
        $response1->assertSee($usulanBidang->nama_barang);
        $response1->assertSee($usulanBidang->kode_barang);
        // Super Admin memiliki hak verifikasi
        $response1->assertSee('Setujui Usulan');
        $response1->assertSee('Tolak Usulan');

        // Akses detail usulan UPT
        $response2 = $this->actingAs($superAdmin)
            ->get(route('rkbmd.show', $usulanUpt->id_usulan));
        $response2->assertOk();
        $response2->assertSee($usulanUpt->nama_barang);
        $response2->assertSee('Setujui Usulan');
    }

    /**
     * 2. Admin Bidang dapat membuka detail usulan sesuai kewenangannya.
     */
    public function test_admin_bidang_dapat_membuka_detail_usulan_sesuai_kewenangannya(): void
    {
        $skpdBidang = $this->buatSkpd('Bidang Perlindungan Perkebunan', 'Bidang');
        $adminBidang = $this->buatUser('Admin Bidang', $skpdBidang, 'admin_bidang_test');
        $usulan = $this->buatUsulan($skpdBidang, $adminBidang, [
            'nama_barang' => 'Traktor Roda 2 Bidang',
            'kebutuhan_maksimum' => 4,
            'kebutuhan_riil' => 2,
            'alasan_kebutuhan' => 'Mendukung operasional kelompok tani binaan bidang.',
        ]);

        $response = $this->actingAs($adminBidang)
            ->get(route('rkbmd.show', $usulan->id_usulan));

        $response->assertOk();
        // Memastikan rincian barang, kebutuhan maksimum, riil, dan alasan tampil lengkap
        $response->assertSee('Traktor Roda 2 Bidang');
        $response->assertSee('4');
        $response->assertSee('2');
        $response->assertSee('Mendukung operasional kelompok tani binaan bidang.');

        // Memastikan tombol Setujui dan Tolak TIDAK muncul untuk Admin Bidang
        $response->assertDontSee('Setujui Usulan');
        $response->assertDontSee('Tolak Usulan');
    }

    /**
     * 3. Admin UPT dapat membuka detail usulan milik UPT-nya.
     */
    public function test_admin_upt_dapat_membuka_detail_usulan_milik_upt_nya(): void
    {
        $skpdUpt = $this->buatSkpd('UPT P2BTP', 'UPT');
        $adminUpt = $this->buatUser('Admin UPT', $skpdUpt, 'admin_upt_test');
        $usulan = $this->buatUsulan($skpdUpt, $adminUpt, [
            'nama_barang' => 'Mesin Sortasi Benih UPT',
            'jumlah' => 1,
            'satuan' => 'Unit',
        ]);

        $response = $this->actingAs($adminUpt)
            ->get(route('rkbmd.show', $usulan->id_usulan));

        $response->assertOk();
        $response->assertSee('Mesin Sortasi Benih UPT');
        $response->assertSee('UPT P2BTP');

        // Memastikan tombol Setujui dan Tolak TIDAK muncul untuk Admin UPT
        $response->assertDontSee('Setujui Usulan');
        $response->assertDontSee('Tolak Usulan');
    }

    /**
     * 4. Admin UPT tidak dapat membuka detail usulan UPT lain (pembatasan di backend).
     */
    public function test_admin_upt_tidak_dapat_membuka_detail_usulan_upt_lain(): void
    {
        $skpdUpt1 = $this->buatSkpd('UPT P2BTP', 'UPT');
        $skpdUpt2 = $this->buatSkpd('UPT PSBP', 'UPT');

        $adminUpt1 = $this->buatUser('Admin UPT', $skpdUpt1, 'admin_upt1');
        $usulanUpt2 = $this->buatUsulan($skpdUpt2, null, [
            'nama_barang' => 'Kendaraan Roda 3 Milik UPT Lain',
        ]);

        // Coba buka usulan milik UPT lain secara langsung via ID URL
        $response = $this->actingAs($adminUpt1)
            ->get(route('rkbmd.show', $usulanUpt2->id_usulan));

        $response->assertForbidden();
    }

    /**
     * 5. Admin Bidang tidak dapat membuka detail di luar cakupan bidangnya.
     */
    public function test_admin_bidang_tidak_dapat_membuka_detail_di_luar_cakupan_bidangnya(): void
    {
        $skpdBidang1 = $this->buatSkpd('Bidang Perlindungan Perkebunan', 'Bidang');
        $skpdBidang2 = $this->buatSkpd('Bidang Pengelolahan dan Pemasaran Hasil', 'Bidang');
        $skpdUpt = $this->buatSkpd('UPT P2BTP', 'UPT');

        $adminBidang1 = $this->buatUser('Admin Bidang', $skpdBidang1, 'admin_bidang1');

        $usulanBidang2 = $this->buatUsulan($skpdBidang2);
        $usulanUpt = $this->buatUsulan($skpdUpt);

        // 1. Coba buka usulan milik Bidang lain
        $resBidangLain = $this->actingAs($adminBidang1)
            ->get(route('rkbmd.show', $usulanBidang2->id_usulan));
        $resBidangLain->assertForbidden();

        // 2. Coba buka usulan milik UPT
        $resUpt = $this->actingAs($adminBidang1)
            ->get(route('rkbmd.show', $usulanUpt->id_usulan));
        $resUpt->assertForbidden();
    }

    /**
     * 6. Admin Bidang tidak dapat menyetujui atau menolak usulan.
     */
    public function test_admin_bidang_tidak_dapat_menyetujui_atau_menolak_usulan(): void
    {
        $skpdBidang = $this->buatSkpd('Bidang Perlindungan Perkebunan', 'Bidang');
        $adminBidang = $this->buatUser('Admin Bidang', $skpdBidang, 'admin_bidang_action');

        $usulan = $this->buatUsulan($skpdBidang, $adminBidang, ['status_usulan' => 'Diajukan']);

        // 1. Percobaan menyetujui via endpoint decide
        $resSetuju = $this->actingAs($adminBidang)
            ->post(route('rkbmd.decide', $usulan->id_usulan), [
                'keputusan' => 'setuju',
            ]);
        $resSetuju->assertForbidden();

        // 2. Percobaan menolak via endpoint decide
        $resTolak = $this->actingAs($adminBidang)
            ->post(route('rkbmd.decide', $usulan->id_usulan), [
                'keputusan' => 'tolak',
                'catatan_pengurus' => 'Dicoba tolak oleh admin bidang',
            ]);
        $resTolak->assertForbidden();

        // Status usulan tetap tidak berubah
        $usulan->refresh();
        $this->assertSame('Diajukan', $usulan->status_usulan);
        $this->assertNull($usulan->approved_at);
    }

    /**
     * 7. Admin UPT tidak dapat menyetujui atau menolak usulan.
     */
    public function test_admin_upt_tidak_dapat_menyetujui_atau_menolak_usulan(): void
    {
        $skpdUpt = $this->buatSkpd('UPT P2BTP', 'UPT');
        $adminUpt = $this->buatUser('Admin UPT', $skpdUpt, 'admin_upt_action');

        $usulan = $this->buatUsulan($skpdUpt, $adminUpt, ['status_usulan' => 'Diajukan']);

        // 1. Percobaan menyetujui via endpoint decide
        $resSetuju = $this->actingAs($adminUpt)
            ->post(route('rkbmd.decide', $usulan->id_usulan), [
                'keputusan' => 'setuju',
            ]);
        $resSetuju->assertForbidden();

        // 2. Percobaan menolak via endpoint decide
        $resTolak = $this->actingAs($adminUpt)
            ->post(route('rkbmd.decide', $usulan->id_usulan), [
                'keputusan' => 'tolak',
                'catatan_pengurus' => 'Dicoba tolak oleh admin UPT',
            ]);
        $resTolak->assertForbidden();

        // Status usulan tetap tidak berubah
        $usulan->refresh();
        $this->assertSame('Diajukan', $usulan->status_usulan);
        $this->assertNull($usulan->approved_at);
    }

    /**
     * 8. Super Admin tetap dapat menyetujui dan menolak sesuai alur yang sudah berlaku.
     */
    public function test_super_admin_tetap_dapat_menyetujui_dan_menolak_usulan(): void
    {
        $superAdmin = $this->buatUser('Super Admin', null, 'superadmin_decide');
        $skpd = $this->buatSkpd('Bidang Perlindungan Perkebunan', 'Bidang');

        $usulanSetuju = $this->buatUsulan($skpd, null, ['status_usulan' => 'Diajukan']);
        $usulanTolak = $this->buatUsulan($skpd, null, ['status_usulan' => 'Diajukan']);

        // 1. Super Admin menyetujui usulan
        $resSetuju = $this->actingAs($superAdmin)
            ->post(route('rkbmd.decide', $usulanSetuju->id_usulan), [
                'keputusan' => 'setuju',
            ]);
        $resSetuju->assertRedirect();
        $resSetuju->assertSessionHas('success');

        $usulanSetuju->refresh();
        $this->assertSame('Disetujui Pengurus Barang', $usulanSetuju->status_usulan);
        $this->assertEquals($superAdmin->id_user, $usulanSetuju->approved_by);
        $this->assertNotNull($usulanSetuju->approved_at);

        // 2. Super Admin menolak usulan dengan catatan
        $resTolak = $this->actingAs($superAdmin)
            ->post(route('rkbmd.decide', $usulanTolak->id_usulan), [
                'keputusan' => 'tolak',
                'catatan_pengurus' => 'Spesifikasi belum sesuai standar harga yang berlaku.',
            ]);
        $resTolak->assertRedirect();
        $resTolak->assertSessionHas('success');

        $usulanTolak->refresh();
        $this->assertSame('Ditolak', $usulanTolak->status_usulan);
        $this->assertSame('Spesifikasi belum sesuai standar harga yang berlaku.', $usulanTolak->catatan_pengurus);
        $this->assertEquals($superAdmin->id_user, $usulanTolak->approved_by);
    }

    /**
     * 9. Tombol Detail di halaman index mengarahkan ke halaman penuh (full page),
     * modal pop-up dihilangkan, halaman show memuat navigasi kembali dan seluruh 9 informasi usulan.
     */
    public function test_tombol_detail_di_index_mengarahkan_ke_halaman_penuh_show(): void
    {
        $superAdmin = $this->buatUser('Super Admin', null, 'superadmin_view_full');
        $skpd = $this->buatSkpd('Bidang Perlindungan Perkebunan', 'Bidang');
        $usulan = $this->buatUsulan($skpd, null, [
            'nama_barang' => 'Komputer All In One Desktop Layout',
            'spesifikasi' => 'Intel Core i7 Gen 13, RAM 32GB, SSD NVMe 1TB',
            'alasan_kebutuhan' => 'Penggantian PC lama yang rusak total untuk operasional GIS perkebunan.',
            'kebutuhan_maksimum' => 10,
            'kebutuhan_riil' => 5,
            'barang_optimalisasi' => [
                'kode' => '1.3.2.10.02.001',
                'nama' => 'PC Desktop Lama',
                'jumlah' => 2,
                'satuan' => 'Unit',
            ],
            'status_usulan' => 'Diajukan',
        ]);

        // 1. Verifikasi halaman index: Kolom Program/Kegiatan tampil, tombol aksi menggunakan ikon konsisten
        $resIndex = $this->actingAs($superAdmin)
            ->get(route('rkbmd.index'));

        $resIndex->assertOk();
        $resIndex->assertSee('Program / Kegiatan');
        $resIndex->assertSee($usulan->nama_program);
        $resIndex->assertSee($usulan->nama_sub_kegiatan);
        $resIndex->assertSee('href="' . route('rkbmd.show', $usulan->id_usulan) . '"', false);
        $resIndex->assertSee('title="Detail Usulan"', false);
        // Usulan berstatus Diajukan: Super Admin melihat tombol Setujui dan Tolak
        $resIndex->assertSee('title="Setujui Usulan"', false);
        $resIndex->assertSee('title="Tolak Usulan"', false);
        $resIndex->assertDontSee('data-open-modal="modalDetail-' . $usulan->id_usulan . '"', false);
        $resIndex->assertDontSee('id="modalDetail-' . $usulan->id_usulan . '"', false);

        // 2. Verifikasi halaman penuh detail (show)
        $resShow = $this->actingAs($superAdmin)
            ->get(route('rkbmd.show', $usulan->id_usulan));

        $resShow->assertOk();

        // Navigasi Kembali ke Daftar Usulan via tombol panah (tombol teks "Daftar Usulan" sudah dihapus)
        $resShow->assertSee('href="' . route('rkbmd.index') . '"', false);
        $resShow->assertSee('title="Kembali ke Daftar Usulan RKBMD"', false);
        $resShow->assertDontSee('<span>Daftar Usulan</span>', false);

        // 9 Informasi Usulan:
        // 1. Informasi Umum Usulan
        $resShow->assertSee('1. Informasi Umum Usulan');
        $resShow->assertSee($skpd->nama_skpd);
        $resShow->assertSee('TA 2027');
        $resShow->assertSee('Diajukan');

        // 2. Program dan Kegiatan RKBMD (Snapshot Format Gabungan telah dihapus)
        $resShow->assertSee('2. Program &amp; Kegiatan RKBMD', false);
        $resShow->assertSee($usulan->nama_program);
        $resShow->assertSee($usulan->nama_sub_kegiatan);
        $resShow->assertDontSee('Snapshot Format Gabungan');

        // 3. Rincian Barang yang Diusulkan
        $resShow->assertSee('3. Rincian Barang yang Diusulkan &amp; Analisis Kebutuhan', false);
        $resShow->assertSee($usulan->nama_barang);
        $resShow->assertSee($usulan->kode_barang);

        // 4. Kebutuhan Maksimum dan Kebutuhan Riil (Berdampingan)
        $resShow->assertSee('4. Perbandingan Kebutuhan Maksimum &amp; Kebutuhan Riil', false);
        $resShow->assertSee('Kebutuhan Maksimum');
        $resShow->assertSee('10');
        $resShow->assertSee('Kebutuhan Riil');
        $resShow->assertSee('5');

        // 5. Daftar Barang yang Dapat Dioptimalisasi (Tabel terstruktur)
        $resShow->assertSee('5. Daftar Barang yang Dapat Dioptimalisasi');
        $resShow->assertSee('PC Desktop Lama');
        $resShow->assertSee('1.3.2.10.02.001');
        $resShow->assertSee('Dioptimalkan');

        // 6. Spesifikasi Teknis
        $resShow->assertSee('6. Spesifikasi Teknis');
        $resShow->assertSee('Intel Core i7 Gen 13, RAM 32GB, SSD NVMe 1TB');

        // 7. Alasan Kebutuhan
        $resShow->assertSee('7. Alasan Kebutuhan');
        $resShow->assertSee('Penggantian PC lama yang rusak total untuk operasional GIS perkebunan.');

        // 8. Catatan Pengurus Barang
        $resShow->assertSee('8. Catatan Pengurus Barang');

        // 9. Status dan Riwayat Verifikasi
        $resShow->assertSee('9. Status &amp; Riwayat Verifikasi', false);

        // Tindakan: Super Admin dapat menyetujui dan menolak karena belum final
        $resShow->assertSee('Setujui Usulan');
        $resShow->assertSee('Tolak Usulan');
    }

    /**
     * 10. Admin Bidang dan Admin UPT hanya dapat melihat detail halaman penuh tanpa tombol Setujui dan Tolak.
     */
    public function test_admin_bidang_dan_upt_tidak_melihat_tombol_persetujuan_di_halaman_show(): void
    {
        $skpdBidang = $this->buatSkpd('Bidang Perlindungan Perkebunan', 'Bidang');
        $adminBidang = $this->buatUser('Admin Bidang', $skpdBidang, 'admin_bidang_show');
        $usulan = $this->buatUsulan($skpdBidang, null, ['status_usulan' => 'Diajukan']);

        $response = $this->actingAs($adminBidang)
            ->get(route('rkbmd.show', $usulan->id_usulan));

        $response->assertOk();
        $response->assertSee('href="' . route('rkbmd.index') . '"', false);
        $response->assertSee($usulan->nama_barang);

        // Tombol persetujuan/penolakan TIDAK ADA untuk Admin Bidang
        $response->assertDontSee('Setujui Usulan');
        $response->assertDontSee('Tolak Usulan');
    }

    /**
     * 11. Status Draf hanya tampil untuk pemilik dengan tombol: Detail, Ajukan, Edit, Batalkan.
     * Tidak boleh ada Setujui, Tolak, atau Hapus.
     */
    public function test_status_draf_hanya_tampil_untuk_pemilik_dengan_tombol_detail_ajukan_edit_batalkan(): void
    {
        $skpd = $this->buatSkpd('Bidang Perkebunan Draft Test ' . uniqid(), 'Bidang');
        $userPengusul = $this->buatUser('Admin Bidang', $skpd, 'pengusul_draft_user');

        $usulanDraft = $this->buatUsulan($skpd, $userPengusul, [
            'nama_barang' => 'Drone Pertanian Draft Test ' . uniqid(),
            'status_usulan' => 'Draft',
        ]);

        $response = $this->actingAs($userPengusul)
            ->get(route('rkbmd.index'));

        $response->assertOk();
        $response->assertSee($usulanDraft->nama_barang);
        $response->assertSee('Draf');

        // Memastikan tombol yang diizinkan untuk Draf tampil
        $response->assertSee('href="' . route('rkbmd.show', $usulanDraft->id_usulan) . '"', false);
        $response->assertSee('title="Detail Usulan"', false);
        $response->assertSee('title="Ajukan Usulan"', false);
        $response->assertSee('title="Edit Usulan"', false);
        $response->assertSee('title="Batalkan Usulan"', false);

        // Memastikan form submit ajukan dan form batalkan mengarah ke ID usulan ini
        $response->assertSee('action="' . route('rkbmd.submit', $usulanDraft->id_usulan) . '"', false);
        $response->assertSee('action="' . route('rkbmd.destroy', $usulanDraft->id_usulan) . '"', false);

        // Memastikan tombol Setujui, Tolak, dan Hapus TIDAK ADA pada SKPD ini
        $response->assertDontSee('title="Setujui Usulan"', false);
        $response->assertDontSee('title="Tolak Usulan"', false);
        $response->assertDontSee('title="Hapus Usulan"', false);
    }

    /**
     * 12. Batalkan usulan pada status Draf menghapus usulan milik pengusul.
     */
    public function test_batalkan_usulan_pada_status_draf_menghapus_usulan_milik_pengusul(): void
    {
        $skpd = $this->buatSkpd('Bidang Perkebunan Batal Test ' . uniqid(), 'Bidang');
        $userPengusul = $this->buatUser('Admin Bidang', $skpd, 'pengusul_batal_user');

        $usulanDraft = $this->buatUsulan($skpd, $userPengusul, [
            'nama_barang' => 'Barang Batal Draft ' . uniqid(),
            'status_usulan' => 'Draft',
        ]);

        $response = $this->actingAs($userPengusul)
            ->delete(route('rkbmd.destroy', $usulanDraft->id_usulan));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Usulan draf berhasil dibatalkan.');

        $this->assertDatabaseMissing('usulan_rkbmd', [
            'id_usulan' => $usulanDraft->id_usulan,
        ]);
    }

    /**
     * 13. Usulan berstatus Draf TIDAK dapat dilihat oleh Super Admin maupun Admin Bidang lain di halaman index.
     */
    public function test_usulan_berstatus_draf_tidak_dapat_dilihat_oleh_super_admin_maupun_user_lain(): void
    {
        $skpd1 = $this->buatSkpd('Bidang Satu Draft Secret ' . uniqid(), 'Bidang');
        $skpd2 = $this->buatSkpd('Bidang Dua Draft Secret ' . uniqid(), 'Bidang');

        $userPemilik = $this->buatUser('Admin Bidang', $skpd1, 'user_pemilik_secret');
        $userLain = $this->buatUser('Admin Bidang', $skpd2, 'user_lain_secret');
        $superAdmin = $this->buatUser('Super Admin', null, 'superadmin_no_draft');

        $usulanDraft = $this->buatUsulan($skpd1, $userPemilik, [
            'nama_barang' => 'Barang Rahasia Draft ' . uniqid(),
            'status_usulan' => 'Draft',
        ]);

        // 1. Super Admin buka index -> TIDAK boleh melihat usulan draft ini
        $resSuper = $this->actingAs($superAdmin)->get(route('rkbmd.index'));
        $resSuper->assertOk();
        $resSuper->assertDontSee($usulanDraft->nama_barang);

        // 2. User lain buka index -> TIDAK boleh melihat usulan draft ini
        $resLain = $this->actingAs($userLain)->get(route('rkbmd.index'));
        $resLain->assertOk();
        $resLain->assertDontSee($usulanDraft->nama_barang);
    }

    /**
     * 14. Akses direct URL ke usulan Draf milik orang lain menghasilkan 403 Forbidden.
     */
    public function test_direct_url_ke_usulan_draf_milik_orang_lain_menghasilkan_403(): void
    {
        $skpd1 = $this->buatSkpd('Bidang Direct URL 1 ' . uniqid(), 'Bidang');
        $skpd2 = $this->buatSkpd('Bidang Direct URL 2 ' . uniqid(), 'Bidang');

        $userPemilik = $this->buatUser('Admin Bidang', $skpd1, 'pemilik_direct_url');
        $userLain = $this->buatUser('Admin Bidang', $skpd2, 'lain_direct_url');
        $superAdmin = $this->buatUser('Super Admin', null, 'super_direct_url');

        $usulanDraft = $this->buatUsulan($skpd1, $userPemilik, [
            'nama_barang' => 'Barang Direct URL Draft ' . uniqid(),
            'status_usulan' => 'Draft',
        ]);

        // 1. Akses oleh Super Admin -> 403
        $resSuper = $this->actingAs($superAdmin)->get(route('rkbmd.show', $usulanDraft->id_usulan));
        $resSuper->assertForbidden();

        // 2. Akses oleh user lain -> 403
        $resLain = $this->actingAs($userLain)->get(route('rkbmd.show', $usulanDraft->id_usulan));
        $resLain->assertForbidden();

        // 3. Akses oleh pemilik sah -> 200 OK
        $resPemilik = $this->actingAs($userPemilik)->get(route('rkbmd.show', $usulanDraft->id_usulan));
        $resPemilik->assertOk();
        $resPemilik->assertSee($usulanDraft->nama_barang);
    }

    /**
     * 15. Pengusul tidak dapat mengedit usulan yang sudah diajukan (status Diajukan).
     */
    public function test_pengusul_tidak_dapat_mengedit_usulan_yang_sudah_diajukan(): void
    {
        $skpd = $this->buatSkpd('Bidang Edit Diajukan ' . uniqid(), 'Bidang');
        $userPengusul = $this->buatUser('Admin Bidang', $skpd, 'user_edit_diajukan');

        $usulanDiajukan = $this->buatUsulan($skpd, $userPengusul, [
            'nama_barang' => 'Barang Diajukan No Edit ' . uniqid(),
            'status_usulan' => 'Diajukan',
        ]);

        $response = $this->actingAs($userPengusul)
            ->patch(route('rkbmd.update', $usulanDiajukan->id_usulan), [
                'tahun_anggaran' => 2027,
                'jenis_usulan' => 'Pengadaan',
                'id_kegiatan' => $usulanDiajukan->id_kegiatan,
                'nama_barang' => 'Coba Ubah Nama Barang',
                'kode_barang' => $usulanDiajukan->kode_barang,
                'spesifikasi' => 'Spek baru',
                'jumlah' => 3,
                'satuan' => 'Unit',
                'kebutuhan_maksimum' => 5,
                'kebutuhan_riil' => 3,
                'alasan_kebutuhan' => 'Alasan baru yang dicoba diubah',
            ]);

        $response->assertStatus(422);

        $usulanDiajukan->refresh();
        $this->assertNotSame('Coba Ubah Nama Barang', $usulanDiajukan->nama_barang);
    }

    /**
     * 16. Pengusul tidak dapat mengedit usulan Draf milik orang lain.
     */
    public function test_pengusul_tidak_dapat_mengedit_usulan_draf_milik_orang_lain(): void
    {
        $skpd1 = $this->buatSkpd('Bidang Edit Orang 1 ' . uniqid(), 'Bidang');
        $skpd2 = $this->buatSkpd('Bidang Edit Orang 2 ' . uniqid(), 'Bidang');

        $userPemilik = $this->buatUser('Admin Bidang', $skpd1, 'user_pemilik_edit');
        $userPenyusup = $this->buatUser('Admin Bidang', $skpd2, 'user_penyusup_edit');

        $usulanDraft = $this->buatUsulan($skpd1, $userPemilik, [
            'nama_barang' => 'Barang Draft Hak Milik ' . uniqid(),
            'status_usulan' => 'Draft',
        ]);

        $response = $this->actingAs($userPenyusup)
            ->patch(route('rkbmd.update', $usulanDraft->id_usulan), [
                'tahun_anggaran' => 2027,
                'jenis_usulan' => 'Pengadaan',
                'id_kegiatan' => $usulanDraft->id_kegiatan,
                'nama_barang' => 'Barang Dibajak',
                'kode_barang' => $usulanDraft->kode_barang,
                'spesifikasi' => 'Spek dibajak',
                'jumlah' => 10,
                'satuan' => 'Unit',
                'kebutuhan_maksimum' => 10,
                'kebutuhan_riil' => 10,
                'alasan_kebutuhan' => 'Mencoba membajak',
            ]);

        $response->assertForbidden();

        $usulanDraft->refresh();
        $this->assertNotSame('Barang Dibajak', $usulanDraft->nama_barang);
    }

    /**
     * 17. Usulan berstatus Diajukan tidak dapat dihapus oleh siapa pun (baik pengusul maupun Super Admin).
     */
    public function test_usulan_berstatus_diajukan_tidak_dapat_dihapus_oleh_siapa_pun(): void
    {
        $skpd = $this->buatSkpd('Bidang Delete Protection ' . uniqid(), 'Bidang');
        $userPengusul = $this->buatUser('Admin Bidang', $skpd, 'user_delete_prot');
        $superAdmin = $this->buatUser('Super Admin', null, 'super_delete_prot');

        $usulanDiajukan = $this->buatUsulan($skpd, $userPengusul, [
            'nama_barang' => 'Barang Diajukan Protected ' . uniqid(),
            'status_usulan' => 'Diajukan',
        ]);

        // 1. Pengusul coba hapus -> 422
        $resPengusul = $this->actingAs($userPengusul)
            ->delete(route('rkbmd.destroy', $usulanDiajukan->id_usulan));
        $resPengusul->assertStatus(422);

        // 2. Super Admin coba hapus -> 422
        $resSuper = $this->actingAs($superAdmin)
            ->delete(route('rkbmd.destroy', $usulanDiajukan->id_usulan));
        $resSuper->assertStatus(422);

        // Record tetap ada di database
        $this->assertDatabaseHas('usulan_rkbmd', [
            'id_usulan' => $usulanDiajukan->id_usulan,
        ]);
    }

    /**
     * 18. Status Disetujui Pengurus Barang menampilkan badge Diterima dan tombol Detail serta Hapus untuk Super Admin.
     */
    public function test_status_disetujui_menampilkan_badge_diterima_dan_tombol_detail_serta_hapus(): void
    {
        $skpd = $this->buatSkpd('Bidang Diterima Test ' . uniqid(), 'Bidang');
        $superAdmin = $this->buatUser('Super Admin', null, 'super_diterima_test');

        $usulan = $this->buatUsulan($skpd, null, [
            'nama_barang' => 'Barang Diterima Official ' . uniqid(),
            'status_usulan' => 'Disetujui Pengurus Barang',
        ]);

        // 1. Index (filter ke SKPD spesifik agar hanya usulan ini yang diperiksa)
        $resIndex = $this->actingAs($superAdmin)->get(route('rkbmd.index', ['bidang' => [$skpd->id_skpd]]));
        $resIndex->assertOk();
        $resIndex->assertSee($usulan->nama_barang);
        $resIndex->assertSee('Diterima');

        // Memastikan tombol Detail dan Hapus tampil
        $resIndex->assertSee('href="' . route('rkbmd.show', $usulan->id_usulan) . '"', false);
        $resIndex->assertSee('title="Detail Usulan"', false);
        $resIndex->assertSee('title="Hapus Usulan"', false);

        // Memastikan route aksi lain tidak ada untuk usulan ini
        $resIndex->assertDontSee(route('rkbmd.submit', $usulan->id_usulan));
        $resIndex->assertDontSee(route('rkbmd.decide', $usulan->id_usulan));

        // 2. Show
        $resShow = $this->actingAs($superAdmin)->get(route('rkbmd.show', $usulan->id_usulan));
        $resShow->assertOk();
        $resShow->assertSee('Diterima');
        $resShow->assertDontSee('Setujui Usulan');
        $resShow->assertDontSee('Tolak Usulan');
    }

    /**
     * 19. Status Ditolak menampilkan badge Ditolak dan tombol Detail serta Hapus untuk Super Admin.
     */
    public function test_status_ditolak_menampilkan_badge_ditolak_dan_tombol_detail_serta_hapus(): void
    {
        $skpd = $this->buatSkpd('Bidang Ditolak Test ' . uniqid(), 'Bidang');
        $superAdmin = $this->buatUser('Super Admin', null, 'super_ditolak_test');

        $usulan = $this->buatUsulan($skpd, null, [
            'nama_barang' => 'Barang Ditolak Official ' . uniqid(),
            'status_usulan' => 'Ditolak',
            'catatan_pengurus' => 'Spesifikasi tidak relevan.',
        ]);

        // 1. Index (filter ke SKPD spesifik agar hanya usulan ini yang diperiksa)
        $resIndex = $this->actingAs($superAdmin)->get(route('rkbmd.index', ['bidang' => [$skpd->id_skpd]]));
        $resIndex->assertOk();
        $resIndex->assertSee($usulan->nama_barang);
        $resIndex->assertSee('Ditolak');

        // Memastikan tombol Detail dan Hapus tampil
        $resIndex->assertSee('href="' . route('rkbmd.show', $usulan->id_usulan) . '"', false);
        $resIndex->assertSee('title="Detail Usulan"', false);
        $resIndex->assertSee('title="Hapus Usulan"', false);

        // Memastikan route aksi lain tidak ada untuk usulan ini
        $resIndex->assertDontSee(route('rkbmd.submit', $usulan->id_usulan));
        $resIndex->assertDontSee(route('rkbmd.decide', $usulan->id_usulan));

        // 2. Show
        $resShow = $this->actingAs($superAdmin)->get(route('rkbmd.show', $usulan->id_usulan));
        $resShow->assertOk();
        $resShow->assertSee('Ditolak');
        $resShow->assertDontSee('Setujui Usulan');
        $resShow->assertDontSee('Tolak Usulan');
    }

    /**
     * 20. Super Admin dapat menghapus usulan berstatus final tanpa merusak master data.
     */
    public function test_super_admin_dapat_menghapus_usulan_berstatus_final(): void
    {
        $superAdmin = $this->buatUser('Super Admin', null, 'super_delete_final');
        $skpd = $this->buatSkpd('Bidang Delete Final ' . uniqid(), 'Bidang');

        $usulan = $this->buatUsulan($skpd, null, [
            'nama_barang' => 'Barang Final To Delete ' . uniqid(),
            'status_usulan' => 'Disetujui Pengurus Barang',
        ]);

        $response = $this->actingAs($superAdmin)
            ->delete(route('rkbmd.destroy', $usulan->id_usulan));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Usulan RKBMD berhasil dihapus.');

        $this->assertDatabaseMissing('usulan_rkbmd', [
            'id_usulan' => $usulan->id_usulan,
        ]);
    }

    /**
     * 21. Kolom Program / Kegiatan fallback dengan aman untuk data legacy tanpa separator.
     */
    public function test_kolom_program_kegiatan_fallback_aman_untuk_data_legacy(): void
    {
        $superAdmin = $this->buatUser('Super Admin', null, 'super_legacy_test');
        $skpd = $this->buatSkpd('Bidang Legacy Test ' . uniqid(), 'Bidang');

        $usulanLegacy = $this->buatUsulan($skpd, null, [
            'nama_barang' => 'Barang Legacy Test ' . uniqid(),
            'program_kegiatan' => 'Program Penunjang Pemerintahan Tanpa Separator',
            'id_kegiatan' => null,
            'bidang_urusan' => null,
            'nama_program' => null,
            'nama_sub_kegiatan' => null,
            'status_usulan' => 'Diajukan',
        ]);

        $response = $this->actingAs($superAdmin)
            ->get(route('rkbmd.index', ['bidang' => [$skpd->id_skpd]]));

        $response->assertOk();
        $response->assertSee('Program Penunjang Pemerintahan Tanpa Separator');
    }

    /**
     * 22. Matriks Visibilitas Draf dan Otorisasi Lengkap Seluruh Role (Opsi A):
     * - Admin Aset dapat melihat Draf miliknya sendiri.
     * - Admin Aset TIDAK dapat melihat Draf milik Admin Aset lain.
     * - Admin Bidang dapat melihat Draf miliknya sendiri.
     * - Admin UPT dapat melihat Draf miliknya sendiri.
     * - Admin Bidang TIDAK dapat melihat Draf milik Admin UPT.
     * - Admin UPT TIDAK dapat melihat Draf milik Admin Bidang.
     * - Draf pengguna lain menghasilkan HTTP 403 saat diakses via URL Detail.
     * - Draf milik sendiri dapat diedit dan diajukan.
     * - Usulan Diajukan, Diterima, dan Ditolak tetap mengikuti cakupan SKPD yang berlaku.
     */
    public function test_matriks_visibilitas_draf_dan_otorisasi_lengkap_seluruh_role(): void
    {
        $skpdSekretariat = $this->buatSkpd('Sekretariat Dinas ' . uniqid(), 'Bidang');
        $skpdBidang = $this->buatSkpd('Bidang Tanaman Pangan ' . uniqid(), 'Bidang');
        $skpdUpt = $this->buatSkpd('UPT Benih Tanaman ' . uniqid(), 'UPT');

        // Pengguna 1: Admin Aset A
        $adminAsetA = $this->buatUser('Admin Aset', $skpdSekretariat, 'admin_aset_a');
        // Pengguna 2: Admin Aset B
        $adminAsetB = $this->buatUser('Admin Aset', $skpdSekretariat, 'admin_aset_b');
        // Pengguna 3: Admin Bidang
        $adminBidang = $this->buatUser('Admin Bidang', $skpdBidang, 'admin_bidang_matriks');
        // Pengguna 4: Admin UPT
        $adminUpt = $this->buatUser('Admin UPT', $skpdUpt, 'admin_upt_matriks');

        // 1. Draf dibuat oleh Admin Aset A
        $draftAsetA = $this->buatUsulan($skpdSekretariat, $adminAsetA, [
            'nama_barang' => 'Draf Khusus Admin Aset A ' . uniqid(),
            'status_usulan' => 'Draft',
        ]);

        // 2. Draf dibuat oleh Admin Bidang
        $draftBidang = $this->buatUsulan($skpdBidang, $adminBidang, [
            'nama_barang' => 'Draf Khusus Admin Bidang ' . uniqid(),
            'status_usulan' => 'Draft',
        ]);

        // 3. Draf dibuat oleh Admin UPT
        $draftUpt = $this->buatUsulan($skpdUpt, $adminUpt, [
            'nama_barang' => 'Draf Khusus Admin UPT ' . uniqid(),
            'status_usulan' => 'Draft',
        ]);

        // 4. Usulan resmi (Diajukan) milik Bidang
        $resmiBidang = $this->buatUsulan($skpdBidang, $adminBidang, [
            'nama_barang' => 'Usulan Resmi Diajukan Bidang ' . uniqid(),
            'status_usulan' => 'Diajukan',
        ]);

        // =========================================================================
        // A. PENGUJIAN VISIBILITAS DAFTAR (INDEX)
        // =========================================================================

        // A.1 Admin Aset A:
        // - DAPAT melihat draf miliknya ($draftAsetA)
        // - TIDAK DAPAT melihat draf Admin Aset B, draf Bidang, draf UPT
        // - DAPAT melihat usulan resmi (Diajukan)
        $resAsetA = $this->actingAs($adminAsetA)->get(route('rkbmd.index'));
        $resAsetA->assertOk();
        $resAsetA->assertSee($draftAsetA->nama_barang);
        $resAsetA->assertDontSee($draftBidang->nama_barang);
        $resAsetA->assertDontSee($draftUpt->nama_barang);
        $resAsetA->assertSee($resmiBidang->nama_barang);

        // A.2 Admin Aset B:
        // - TIDAK DAPAT melihat draf milik Admin Aset A
        // - TIDAK DAPAT melihat draf Bidang dan draf UPT
        // - DAPAT melihat usulan resmi (Diajukan)
        $resAsetB = $this->actingAs($adminAsetB)->get(route('rkbmd.index'));
        $resAsetB->assertOk();
        $resAsetB->assertDontSee($draftAsetA->nama_barang);
        $resAsetB->assertDontSee($draftBidang->nama_barang);
        $resAsetB->assertDontSee($draftUpt->nama_barang);
        $resAsetB->assertSee($resmiBidang->nama_barang);

        // A.3 Admin Bidang:
        // - DAPAT melihat draf miliknya ($draftBidang)
        // - TIDAK DAPAT melihat draf milik Admin Aset A maupun Admin UPT
        // - DAPAT melihat usulan resmi milik bidangnya ($resmiBidang)
        $resBidang = $this->actingAs($adminBidang)->get(route('rkbmd.index'));
        $resBidang->assertOk();
        $resBidang->assertSee($draftBidang->nama_barang);
        $resBidang->assertDontSee($draftAsetA->nama_barang);
        $resBidang->assertDontSee($draftUpt->nama_barang);
        $resBidang->assertSee($resmiBidang->nama_barang);

        // A.4 Admin UPT:
        // - DAPAT melihat draf miliknya ($draftUpt)
        // - TIDAK DAPAT melihat draf milik Admin Aset A maupun Admin Bidang
        // - TIDAK DAPAT melihat usulan resmi milik Bidang (karena beda SKPD)
        $resUpt = $this->actingAs($adminUpt)->get(route('rkbmd.index'));
        $resUpt->assertOk();
        $resUpt->assertSee($draftUpt->nama_barang);
        $resUpt->assertDontSee($draftAsetA->nama_barang);
        $resUpt->assertDontSee($draftBidang->nama_barang);
        $resUpt->assertDontSee($resmiBidang->nama_barang);

        // =========================================================================
        // B. PENGUJIAN AKSES URL DETAIL (SHOW)
        // =========================================================================

        // B.1 Admin Aset B mencoba akses Detail Draf milik Admin Aset A -> HTTP 403
        $this->actingAs($adminAsetB)
            ->get(route('rkbmd.show', $draftAsetA->id_usulan))
            ->assertForbidden();

        // B.2 Admin Bidang mencoba akses Detail Draf milik Admin UPT -> HTTP 403
        $this->actingAs($adminBidang)
            ->get(route('rkbmd.show', $draftUpt->id_usulan))
            ->assertForbidden();

        // B.3 Admin UPT mencoba akses Detail Draf milik Admin Bidang -> HTTP 403
        $this->actingAs($adminUpt)
            ->get(route('rkbmd.show', $draftBidang->id_usulan))
            ->assertForbidden();

        // B.4 Admin Aset A mengakses Detail Draf miliknya sendiri -> OK (200)
        $this->actingAs($adminAsetA)
            ->get(route('rkbmd.show', $draftAsetA->id_usulan))
            ->assertOk();

        // B.5 Admin Bidang mengakses Detail Draf miliknya sendiri -> OK (200)
        $this->actingAs($adminBidang)
            ->get(route('rkbmd.show', $draftBidang->id_usulan))
            ->assertOk();

        // =========================================================================
        // C. PENGUJIAN EDIT & AJUKAN DRAF MILIK SENDIRI
        // =========================================================================

        // C.1 Admin Aset A mengedit Draf miliknya sendiri -> Sukses (Redirect)
        $kegiatan = MasterKegiatanRkbmd::first();
        $responseEdit = $this->actingAs($adminAsetA)
            ->patch(route('rkbmd.update', $draftAsetA->id_usulan), [
                'id_skpd' => $skpdSekretariat->id_skpd,
                'tahun_anggaran' => 2027,
                'jenis_usulan' => 'Pengadaan',
                'id_kegiatan' => $kegiatan->id_kegiatan,
                'nama_barang' => 'Draf Aset A Terupdate',
                'kode_barang' => '1.3.2.10.02',
                'jumlah' => 5,
                'satuan' => 'Unit',
                'kebutuhan_maksimum' => 10,
                'kebutuhan_riil' => 5,
                'alasan_kebutuhan' => 'Justifikasi update draf oleh pembuat.',
            ]);
        $responseEdit->assertRedirect();
        $this->assertDatabaseHas('usulan_rkbmd', [
            'id_usulan' => $draftAsetA->id_usulan,
            'nama_barang' => 'Draf Aset A Terupdate',
            'jumlah' => 5,
        ]);

        // C.2 Admin Aset B mencoba mengedit Draf milik Admin Aset A -> HTTP 403
        $this->actingAs($adminAsetB)
            ->patch(route('rkbmd.update', $draftAsetA->id_usulan), [
                'id_skpd' => $skpdSekretariat->id_skpd,
                'tahun_anggaran' => 2027,
                'jenis_usulan' => 'Pengadaan',
                'id_kegiatan' => $kegiatan->id_kegiatan,
                'nama_barang' => 'Hacked by Admin B',
                'kode_barang' => '1.3.2.10.02',
                'jumlah' => 1,
                'satuan' => 'Unit',
                'kebutuhan_maksimum' => 1,
                'kebutuhan_riil' => 1,
                'alasan_kebutuhan' => 'Coba edit milik orang lain.',
            ])
            ->assertForbidden();

        // C.3 Admin Aset A mengajukan Draf miliknya sendiri -> Status berubah 'Diajukan'
        $responseSubmit = $this->actingAs($adminAsetA)
            ->post(route('rkbmd.submit', $draftAsetA->id_usulan));
        $responseSubmit->assertRedirect();
        $this->assertDatabaseHas('usulan_rkbmd', [
            'id_usulan' => $draftAsetA->id_usulan,
            'status_usulan' => 'Diajukan',
        ]);
    }
}

