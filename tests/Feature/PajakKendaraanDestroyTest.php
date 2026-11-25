<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Kendaraan;
use App\Models\MasterBarang;
use App\Models\PajakKendaraan;
use App\Models\Pegawai;
use App\Models\PenempatanAset;
use App\Models\RiwayatPlat;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Skpd;
use App\Models\User;
use App\Services\AsetScope;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PajakKendaraanDestroyTest extends TestCase
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

    private function userAdminAset(): User
    {
        $role = Role::firstOrCreate(
            ['nama_role' => 'Admin Aset'],
            ['deskripsi' => 'Administrator Aset']
        );

        $skpd = Skpd::firstOrCreate(
            ['nama_skpd' => 'Dinas Perkebunan Test'],
            ['jenis_skpd' => 'Sekretariat']
        );

        $pegawai = Pegawai::create([
            'nama_pegawai' => 'Admin Aset ' . uniqid(),
            'nip' => '19850101' . rand(100000, 999999),
            'id_skpd' => $skpd->id_skpd,
            'jabatan' => 'Pengurus Barang',
        ]);

        return User::create([
            'username' => 'admin_aset_' . uniqid(),
            'password' => bcrypt('password123'),
            'id_role' => $role->id_role,
            'id_pegawai' => $pegawai->id_pegawai,
            'status_user' => 'aktif',
        ]);
    }

    private function buatKendaraan(): Kendaraan
    {
        $barang = MasterBarang::firstOrCreate(
            ['nama_barang' => 'Mobil Dinas Uji Pajak ' . uniqid()],
            ['kode_barang' => 'KND_PJK_' . uniqid(), 'id_kategori' => 2]
        );

        $ruangan = Ruangan::firstOrCreate(
            ['nama_ruangan' => 'Pool Kendaraan'],
            ['id_skpd' => 1, 'id_lokasi' => 1, 'status' => 'Aktif']
        );

        $aset = Aset::create([
            'id_barang' => $barang->id_barang,
            'id_lokasi' => 1,
            'nomor_kartu_barang' => 'NKB_PJK_' . uniqid(),
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => true,
        ]);

        PenempatanAset::create([
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruangan->id_ruangan,
            'tanggal_mulai' => now()->toDateString(),
            'status' => 'aktif',
            'is_finalized' => 1,
            'finalized_at' => now(),
        ]);

        return Kendaraan::create([
            'id_aset' => $aset->id_aset,
            'nama_kendaraan' => 'Kendaraan Uji Pajak ' . uniqid(),
            'status_penggunaan' => 'Tersedia',
        ]);
    }

    /**
     * Skenario 1: Upaya menghapus Pajak 5 Tahunan berstatus Aktif ditolak HTTP 403 sesuai aturan bisnis.
     * Data pajak dan relasi plat aktif tetap utuh dan terlindungi.
     */
    public function test_skenario_1_hapus_pajak_5_tahunan_aktif_mengembalikan_plat_ke_sebelumnya(): void
    {
        $admin = $this->userAdminAset();
        $kendaraan = $this->buatKendaraan();

        // Plat A (didemosi sebelumnya)
        $platA = RiwayatPlat::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'nomor_plat' => 'L 1111 AA',
            'tanggal_berlaku' => '2026-05-10',
            'ganti_plat' => false,
            'status' => 'Tidak Aktif',
        ]);

        // Plat B (aktif saat ini, dibuat oleh pajak 5 tahunan)
        $platB = RiwayatPlat::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'nomor_plat' => 'L 2222 BB',
            'tanggal_berlaku' => '2031-05-10',
            'ganti_plat' => true,
            'status' => 'Aktif',
        ]);

        // Pajak 5 Tahunan Aktif yang bersangkutan
        $pajakAktif = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB 5 Tahunan',
            'tahun' => 2031,
            'tanggal_berakhir' => '2031-05-10',
            'pajak_5_tahunan' => true,
            'status' => 'Aktif',
        ]);

        $response = $this->actingAs($admin)->deleteJson(
            route('kendaraan.pajak.destroy', [$kendaraan, $pajakAktif])
        );

        // Aturan bisnis: DELETE ditolak dengan HTTP 403 Forbidden
        $response->assertForbidden();

        // Pajak 5 tahunan TIDAK terhapus
        $this->assertNotNull(PajakKendaraan::find($pajakAktif->id_pajak));

        // Plat B tetap ada dan tetap Aktif
        $this->assertNotNull(RiwayatPlat::find($platB->id_plat));
        $this->assertSame('Aktif', $platB->fresh()->status);

        // Plat A tetap Tidak Aktif
        $this->assertSame('Tidak Aktif', $platA->fresh()->status);
        $this->assertSame('L 2222 BB', $kendaraan->platAktif()->first()?->nomor_plat);
    }

    /**
     * Skenario 2: Upaya menghapus Pajak 5 Tahunan historis/tidak aktif ditolak HTTP 403 sesuai aturan bisnis.
     * Seluruh data transaksi pajak (historis maupun aktif) tetap utuh.
     */
    public function test_skenario_2_hapus_pajak_5_tahunan_historis_hanya_menghapus_data_pajak(): void
    {
        $admin = $this->userAdminAset();
        $kendaraan = $this->buatKendaraan();

        $platA = RiwayatPlat::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'nomor_plat' => 'L 1111 AA',
            'tanggal_berlaku' => '2021-05-10',
            'ganti_plat' => false,
            'status' => 'Tidak Aktif',
        ]);

        $platB = RiwayatPlat::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'nomor_plat' => 'L 2222 BB',
            'tanggal_berlaku' => '2026-05-10',
            'ganti_plat' => true,
            'status' => 'Aktif',
        ]);

        // Pajak 5 tahunan historis (Tidak Aktif)
        $pajakHistoris = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB 5 Tahunan',
            'tahun' => 2021,
            'tanggal_berakhir' => '2021-05-10',
            'pajak_5_tahunan' => true,
            'status' => 'Tidak Aktif',
        ]);

        // Pajak 5 tahunan aktif saat ini
        $pajakAktif = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB 5 Tahunan',
            'tahun' => 2026,
            'tanggal_berakhir' => '2026-05-10',
            'pajak_5_tahunan' => true,
            'status' => 'Aktif',
        ]);

        $response = $this->actingAs($admin)->deleteJson(
            route('kendaraan.pajak.destroy', [$kendaraan, $pajakHistoris])
        );

        // Aturan bisnis: DELETE ditolak dengan HTTP 403 Forbidden
        $response->assertForbidden();

        // Pajak historis TIDAK terhapus
        $this->assertNotNull(PajakKendaraan::find($pajakHistoris->id_pajak));

        // Pajak aktif tetap ada
        $this->assertNotNull(PajakKendaraan::find($pajakAktif->id_pajak));
        $this->assertSame('Aktif', $pajakAktif->fresh()->status);
    }

    /**
     * Skenario 3: Plat aktif terbaru tetap menjadi plat aktif saat upaya hapus pajak historis ditolak.
     */
    public function test_skenario_3_plat_aktif_terbaru_tetap_aktif_setelah_pajak_historis_dihapus(): void
    {
        $admin = $this->userAdminAset();
        $kendaraan = $this->buatKendaraan();

        $platA = RiwayatPlat::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'nomor_plat' => 'L 3333 CC',
            'tanggal_berlaku' => '2020-01-01',
            'ganti_plat' => false,
            'status' => 'Tidak Aktif',
        ]);

        $platB = RiwayatPlat::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'nomor_plat' => 'L 4444 DD',
            'tanggal_berlaku' => '2025-01-01',
            'ganti_plat' => true,
            'status' => 'Aktif',
        ]);

        $pajakHistoris = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB 5 Tahunan',
            'tahun' => 2020,
            'tanggal_berakhir' => '2020-01-01',
            'pajak_5_tahunan' => true,
            'status' => 'Tidak Aktif',
        ]);

        $this->actingAs($admin)->deleteJson(
            route('kendaraan.pajak.destroy', [$kendaraan, $pajakHistoris])
        )->assertForbidden();

        // Plat B tetap ada dan tetap berstatus Aktif
        $this->assertNotNull(RiwayatPlat::find($platB->id_plat));
        $this->assertSame('Aktif', $platB->fresh()->status);
        $this->assertSame('L 4444 DD', $kendaraan->platAktif()->first()?->nomor_plat);
    }

    /**
     * Skenario 4: Plat lama tetap tidak aktif saat upaya hapus pajak historis ditolak.
     */
    public function test_skenario_4_plat_lama_tidak_diaktifkan_kembali_ketika_pajak_historis_dihapus(): void
    {
        $admin = $this->userAdminAset();
        $kendaraan = $this->buatKendaraan();

        $platA = RiwayatPlat::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'nomor_plat' => 'L 5555 EE',
            'tanggal_berlaku' => '2019-06-01',
            'ganti_plat' => false,
            'status' => 'Tidak Aktif',
        ]);

        $platB = RiwayatPlat::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'nomor_plat' => 'L 6666 FF',
            'tanggal_berlaku' => '2024-06-01',
            'ganti_plat' => true,
            'status' => 'Aktif',
        ]);

        $pajakHistoris = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB 5 Tahunan',
            'tahun' => 2019,
            'tanggal_berakhir' => '2019-06-01',
            'pajak_5_tahunan' => true,
            'status' => 'Tidak Aktif',
        ]);

        $this->actingAs($admin)->deleteJson(
            route('kendaraan.pajak.destroy', [$kendaraan, $pajakHistoris])
        )->assertForbidden();

        // Plat A tetap berstatus Tidak Aktif
        $this->assertSame('Tidak Aktif', $platA->fresh()->status);
    }

    /**
     * Skenario 5: Upaya menghapus Pajak Tahunan ditolak HTTP 403, data pajak dan plat tidak berubah.
     */
    public function test_skenario_5_menghapus_pajak_tahunan_tidak_mengubah_riwayat_plat(): void
    {
        $admin = $this->userAdminAset();
        $kendaraan = $this->buatKendaraan();

        $plat = RiwayatPlat::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'nomor_plat' => 'L 7777 GG',
            'tanggal_berlaku' => '2026-08-15',
            'ganti_plat' => false,
            'status' => 'Aktif',
        ]);

        $pajakTahunan = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB Tahunan',
            'tahun' => 2026,
            'tanggal_berakhir' => '2026-08-15',
            'pajak_5_tahunan' => false,
            'status' => 'Aktif',
        ]);

        $response = $this->actingAs($admin)->deleteJson(
            route('kendaraan.pajak.destroy', [$kendaraan, $pajakTahunan])
        );

        $response->assertForbidden();

        // Pajak tahunan TIDAK terhapus
        $this->assertNotNull(PajakKendaraan::find($pajakTahunan->id_pajak));

        // Plat aktif sama sekali tidak berubah
        $this->assertNotNull(RiwayatPlat::find($plat->id_plat));
        $this->assertSame('Aktif', $plat->fresh()->status);
        $this->assertSame('L 7777 GG', $kendaraan->platAktif()->first()?->nomor_plat);
    }

    /**
     * Skenario 6: Riwayat Plat tetap menampilkan maksimal tiga plat terakhir.
     */
    public function test_skenario_6_riwayat_plat_tetap_menampilkan_maksimal_tiga_plat_terakhir(): void
    {
        $admin = $this->userAdminAset();
        $kendaraan = $this->buatKendaraan();

        // Buat 4 plat dalam riwayat dengan jeda created_at bertahap
        $p1 = RiwayatPlat::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'nomor_plat' => 'L 1001 AA',
            'tanggal_berlaku' => '2010-01-01',
            'ganti_plat' => false,
            'status' => 'Tidak Aktif',
        ]);
        DB::table('riwayat_plat')->where('id_plat', $p1->id_plat)->update(['created_at' => now()->subMinutes(40)]);

        $p2 = RiwayatPlat::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'nomor_plat' => 'L 1002 BB',
            'tanggal_berlaku' => '2015-01-01',
            'ganti_plat' => true,
            'status' => 'Tidak Aktif',
        ]);
        DB::table('riwayat_plat')->where('id_plat', $p2->id_plat)->update(['created_at' => now()->subMinutes(30)]);

        $p3 = RiwayatPlat::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'nomor_plat' => 'L 1003 CC',
            'tanggal_berlaku' => '2020-01-01',
            'ganti_plat' => true,
            'status' => 'Tidak Aktif',
        ]);
        DB::table('riwayat_plat')->where('id_plat', $p3->id_plat)->update(['created_at' => now()->subMinutes(20)]);

        $p4 = RiwayatPlat::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'nomor_plat' => 'L 1004 DD',
            'tanggal_berlaku' => '2025-01-01',
            'ganti_plat' => true,
            'status' => 'Aktif',
        ]);
        DB::table('riwayat_plat')->where('id_plat', $p4->id_plat)->update(['created_at' => now()->subMinutes(10)]);

        $response = $this->actingAs($admin)->get(route('kendaraan.show', $kendaraan));
        $response->assertOk();

        // Verifikasi dari view atau query controller bahwa riwayatPlat yang di-load dibatasi limit 3
        $kendaraanShow = $response->viewData('kendaraan');
        $this->assertCount(3, $kendaraanShow->riwayatPlat);

        // Plat Aktif (p4) harus termasuk di dalam 3 data tersebut
        $nomorPlats = $kendaraanShow->riwayatPlat->pluck('nomor_plat')->all();
        $this->assertContains('L 1004 DD', $nomorPlats);
        $this->assertNotContains('L 1001 AA', $nomorPlats); // Plat tertua tidak ditampilkan
    }

    /**
     * Skenario 7: Upaya penghapusan data pajak ditolak 403 dan tidak merusak riwayat pajak atau data kendaraan.
     */
    public function test_skenario_7_penghapusan_data_pajak_tidak_merusak_riwayat_pajak_atau_data_kendaraan(): void
    {
        $admin = $this->userAdminAset();
        $kendaraan = $this->buatKendaraan();

        $pajak1 = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB Tahunan 2024',
            'tahun' => 2024,
            'tanggal_berakhir' => '2024-05-10',
            'nominal' => 500000,
            'pajak_5_tahunan' => false,
            'status' => 'Tidak Aktif',
        ]);

        $pajak2 = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB Tahunan 2025',
            'tahun' => 2025,
            'tanggal_berakhir' => '2025-05-10',
            'nominal' => 550000,
            'pajak_5_tahunan' => false,
            'status' => 'Aktif',
        ]);

        $pajak3 = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB 5 Tahunan',
            'tahun' => 2026,
            'tanggal_berakhir' => '2026-05-10',
            'nominal' => 1200000,
            'pajak_5_tahunan' => true,
            'status' => 'Tidak Aktif',
        ]);

        // Hapus pajak 3 (5 Tahunan historis) ditolak 403 sesuai aturan bisnis
        $this->actingAs($admin)->deleteJson(
            route('kendaraan.pajak.destroy', [$kendaraan, $pajak3])
        )->assertForbidden();

        // Seluruh data pajak (pajak 1, 2, dan 3) tetap utuh
        $this->assertNotNull(PajakKendaraan::find($pajak1->id_pajak));
        $this->assertEquals(500000, PajakKendaraan::find($pajak1->id_pajak)->nominal);

        $this->assertNotNull(PajakKendaraan::find($pajak2->id_pajak));
        $this->assertEquals(550000, PajakKendaraan::find($pajak2->id_pajak)->nominal);

        $this->assertNotNull(PajakKendaraan::find($pajak3->id_pajak));
        $this->assertEquals(1200000, PajakKendaraan::find($pajak3->id_pajak)->nominal);

        // Data kendaraan master tetap utuh
        $this->assertSame('Tersedia', $kendaraan->fresh()->status_penggunaan);
        $this->assertNotNull($kendaraan->fresh()->aset);
    }

    /**
     * Skenario 8: Penghapusan transaksi pajak selalu ditolak secara aman (403 Forbidden) sesuai aturan bisnis.
     */
    public function test_skenario_8_penghapusan_gagal_secara_aman_jika_relasi_plat_tidak_pasti(): void
    {
        $admin = $this->userAdminAset();
        $kendaraan = $this->buatKendaraan();

        // Plat aktif saat ini memiliki tanggal berbeda atau ganti_plat false
        $platAktif = RiwayatPlat::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'nomor_plat' => 'L 9999 ZZ',
            'tanggal_berlaku' => '2025-01-01',
            'ganti_plat' => false,
            'status' => 'Aktif',
        ]);

        // Pajak 5 Tahunan berstatus Aktif dengan tanggal jatuh tempo berbeda yang tidak punya plat terkait
        $pajakAktifTanpaRelasi = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB 5 Tahunan',
            'tahun' => 2035,
            'tanggal_berakhir' => '2035-12-31',
            'pajak_5_tahunan' => true,
            'status' => 'Aktif',
        ]);

        $response = $this->actingAs($admin)->deleteJson(
            route('kendaraan.pajak.destroy', [$kendaraan, $pajakAktifTanpaRelasi])
        );

        // Harus ditolak dengan HTTP 403 Forbidden sesuai aturan bisnis
        $response->assertForbidden();

        // Pajak TIDAK terhapus
        $this->assertNotNull(PajakKendaraan::find($pajakAktifTanpaRelasi->id_pajak));

        // Plat aktif TIDAK terhapus dan tetap Aktif
        $this->assertNotNull(RiwayatPlat::find($platAktif->id_plat));
        $this->assertSame('Aktif', $platAktif->fresh()->status);
        $this->assertSame('L 9999 ZZ', $kendaraan->platAktif()->first()?->nomor_plat);
    }
}
