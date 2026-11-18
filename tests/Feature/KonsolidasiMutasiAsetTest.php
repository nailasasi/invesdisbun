<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\DetailMutasiAset;
use App\Models\MasterBarang;
use App\Models\MutasiAset;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\PenempatanAset;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Skpd;
use App\Models\User;
use App\Services\AsetScope;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pengujian komprehensif Konsolidasi Mutasi Aset (Refactoring Tahap 1):
 * - AsetMutasiService dipanggil secara konsisten oleh AsetBarangController dan AsetRuanganController.
 * - Validasi ruangan tujuan aktif (status = 'Aktif') di kedua controller.
 * - Pencegahan IDOR pada AsetRuanganController (ruangan asal mismatch).
 * - Penutupan pemegang lama dan penempatan baru langsung is_finalized = 1.
 * - Hak akses 4 role (Admin Aset, Admin UPT, Admin Bidang, Pegawai).
 */
class KonsolidasiMutasiAsetTest extends TestCase
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

    private function userByRole(string $namaRole): User
    {
        return User::whereHas('role', fn ($r) => $r->where('nama_role', $namaRole))->firstOrFail();
    }

    private function buatRuangan(Skpd $skpd, string $status = 'Aktif'): Ruangan
    {
        return Ruangan::create([
            'nama_ruangan' => 'Ruang Uji ' . uniqid(),
            'id_skpd' => $skpd->id_skpd,
            'id_lokasi' => $skpd->id_lokasi ?? 1,
            'status' => $status,
        ]);
    }

    private function buatPegawai(Skpd $skpd, ?Ruangan $ruangan = null): Pegawai
    {
        return Pegawai::create([
            'nama_pegawai' => 'Pegawai Uji ' . uniqid(),
            'nip' => '198' . rand(10000000, 99999999),
            'id_skpd' => $skpd->id_skpd,
            'id_ruangan' => $ruangan?->id_ruangan,
            'jabatan' => 'Staf Pengujian',
        ]);
    }

    private function buatAset(Skpd $skpd): Aset
    {
        $mb = MasterBarang::first() ?? MasterBarang::create([
            'kode_barang' => '02.06.01.01.01',
            'nama_barang' => 'Laptop Uji',
        ]);

        return Aset::create([
            'id_barang' => $mb->id_barang,
            'nama_barang' => 'Laptop Uji Mutasi',
            'nomor_kartu_barang' => 'NKB-' . uniqid(),
            'tanggal_pengadaan' => '2025-01-01',
            'tanggal_habis_pakai' => '2030-01-01',
            'nilai_perolehan' => 15000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
            'id_skpd' => $skpd->id_skpd,
            'id_lokasi' => $skpd->id_lokasi ?? 1,
        ]);
    }

    // =========================================================================
    // 1. MUTASI GANTI PEMEGANG (AsetBarangController)
    // =========================================================================

    public function test_mutasi_ganti_pemegang_sukses_dan_hasilkan_bast(): void
    {
        $admin = $this->userByRole('Admin Aset');
        $skpd = Skpd::where('jenis_skpd', 'Sekretariat')->first() ?? Skpd::firstOrFail();

        $ruang1 = $this->buatRuangan($skpd);
        $ruang2 = $this->buatRuangan($skpd);

        $pegawaiLama = $this->buatPegawai($skpd, $ruang1);
        $pegawaiBaru = $this->buatPegawai($skpd, $ruang2);

        $aset = $this->buatAset($skpd);

        // Pasang pemegang lama
        PemegangAset::create([
            'id_aset' => $aset->id_aset,
            'id_pegawai' => $pegawaiLama->id_pegawai,
            'tanggal_mulai' => '2025-01-01',
            'status' => 'aktif',
        ]);
        PenempatanAset::create([
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruang1->id_ruangan,
            'tanggal_mulai' => '2025-01-01',
            'status' => 'aktif',
            'is_finalized' => 1,
        ]);

        $res = $this->actingAs($admin)->postJson(route('aset-barang.aset.mutasi', $aset->id_aset), [
            'tipe' => 'pegawai',
            'id_pegawai' => $pegawaiBaru->id_pegawai,
            'keterangan' => 'Mutasi ke pegawai baru untuk pengujian',
        ]);

        $res->assertOk();
        $res->assertJson([
            'success' => true,
            'message' => 'Pemegang aset berhasil diganti.',
            'warning' => null,
        ]);
        $this->assertNotEmpty($res->json('download_bast_url'));
        $this->assertNotEmpty($res->json('id_mutasi'));

        // Pastikan pemegang lama dinonaktifkan
        $this->assertDatabaseHas('pemegang_aset', [
            'id_aset' => $aset->id_aset,
            'id_pegawai' => $pegawaiLama->id_pegawai,
            'status' => 'tidak aktif',
        ]);

        // Pastikan pemegang baru aktif
        $this->assertDatabaseHas('pemegang_aset', [
            'id_aset' => $aset->id_aset,
            'id_pegawai' => $pegawaiBaru->id_pegawai,
            'status' => 'aktif',
        ]);

        // Penempatan baru mengikuti ruang kerja pegawai baru dan LANGSUNG is_finalized = 1
        $penempatanBaru = PenempatanAset::where('id_aset', $aset->id_aset)
            ->where('status', 'aktif')
            ->first();
        $this->assertNotNull($penempatanBaru);
        $this->assertEquals($ruang2->id_ruangan, $penempatanBaru->id_ruangan);
        $this->assertEquals(1, $penempatanBaru->is_finalized);

        // Riwayat mutasi tercatat
        $this->assertDatabaseHas('mutasi_aset', [
            'id_mutasi' => $res->json('id_mutasi'),
            'jenis_mutasi' => 'Ganti Pemegang',
        ]);
        $this->assertDatabaseHas('detail_mutasi_aset', [
            'id_mutasi' => $res->json('id_mutasi'),
            'id_aset' => $aset->id_aset,
            'pegawai_lama' => $pegawaiLama->id_pegawai,
            'pegawai_baru' => $pegawaiBaru->id_pegawai,
        ]);
    }

    public function test_mutasi_ganti_pemegang_ke_pegawai_tanpa_ruangan_tampilkan_warning(): void
    {
        $admin = $this->userByRole('Admin Aset');
        $skpd = Skpd::where('jenis_skpd', 'Sekretariat')->first() ?? Skpd::firstOrFail();

        $ruang1 = $this->buatRuangan($skpd);
        $pegawaiLama = $this->buatPegawai($skpd, $ruang1);
        $pegawaiBaruTanpaRuang = $this->buatPegawai($skpd, null);

        $aset = $this->buatAset($skpd);

        PemegangAset::create([
            'id_aset' => $aset->id_aset,
            'id_pegawai' => $pegawaiLama->id_pegawai,
            'tanggal_mulai' => '2025-01-01',
            'status' => 'aktif',
        ]);
        PenempatanAset::create([
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruang1->id_ruangan,
            'tanggal_mulai' => '2025-01-01',
            'status' => 'aktif',
            'is_finalized' => 1,
        ]);

        $res = $this->actingAs($admin)->postJson(route('aset-barang.aset.mutasi', $aset->id_aset), [
            'tipe' => 'pegawai',
            'id_pegawai' => $pegawaiBaruTanpaRuang->id_pegawai,
            'keterangan' => 'Mutasi ke pegawai tanpa ruang',
        ]);

        $res->assertOk();
        $res->assertJson([
            'success' => true,
            'message' => 'Pemegang aset berhasil diganti.',
        ]);
        $this->assertNotNull($res->json('warning'));
        $this->assertStringContainsString('Pegawai belum punya ruangan', $res->json('warning'));

        // Penempatan aktif lama harus ditutup sehingga aset tidak punya penempatan aktif
        $this->assertDatabaseMissing('penempatan_aset', [
            'id_aset' => $aset->id_aset,
            'status' => 'aktif',
        ]);
    }

    // =========================================================================
    // 2. MUTASI PINDAH RUANGAN (AsetBarangController)
    // =========================================================================

    public function test_mutasi_pindah_ruangan_aset_barang_sukses(): void
    {
        $admin = $this->userByRole('Admin Aset');
        $skpd = Skpd::where('jenis_skpd', 'Sekretariat')->first() ?? Skpd::firstOrFail();

        $ruangAsal = $this->buatRuangan($skpd);
        $ruangTujuan = $this->buatRuangan($skpd);

        $aset = $this->buatAset($skpd);

        // Barang melekat (tanpa pemegang), punya penempatan aktif yang sudah difinalisasi
        PenempatanAset::create([
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruangAsal->id_ruangan,
            'tanggal_mulai' => '2025-01-01',
            'status' => 'aktif',
            'is_finalized' => 1,
        ]);

        $res = $this->actingAs($admin)->postJson(route('aset-barang.aset.mutasi', $aset->id_aset), [
            'tipe' => 'ruangan',
            'id_ruangan' => $ruangTujuan->id_ruangan,
            'keterangan' => 'Pindah ke ruang rapat',
        ]);

        $res->assertOk();
        $res->assertJson([
            'success' => true,
        ]);

        // Penempatan lama ditutup
        $this->assertDatabaseHas('penempatan_aset', [
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruangAsal->id_ruangan,
            'status' => 'tidak aktif',
        ]);

        // Penempatan baru dibuka dengan is_finalized = 1
        $this->assertDatabaseHas('penempatan_aset', [
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruangTujuan->id_ruangan,
            'status' => 'aktif',
            'is_finalized' => 1,
        ]);
    }

    public function test_mutasi_pindah_ruangan_aset_barang_ditolak_jika_ruangan_tidak_aktif(): void
    {
        $admin = $this->userByRole('Admin Aset');
        $skpd = Skpd::where('jenis_skpd', 'Sekretariat')->first() ?? Skpd::firstOrFail();

        $ruangAsal = $this->buatRuangan($skpd);
        $ruangTujuanTidakAktif = $this->buatRuangan($skpd, 'Nonaktif');

        $aset = $this->buatAset($skpd);

        PenempatanAset::create([
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruangAsal->id_ruangan,
            'tanggal_mulai' => '2025-01-01',
            'status' => 'aktif',
            'is_finalized' => 1,
        ]);

        $res = $this->actingAs($admin)->postJson(route('aset-barang.aset.mutasi', $aset->id_aset), [
            'tipe' => 'ruangan',
            'id_ruangan' => $ruangTujuanTidakAktif->id_ruangan,
            'keterangan' => 'Pindah ke ruangan rusak',
        ]);

        $res->assertStatus(422);
    }

    public function test_mutasi_pindah_ruangan_aset_barang_ditolak_jika_aset_memiliki_pemegang(): void
    {
        $admin = $this->userByRole('Admin Aset');
        $skpd = Skpd::where('jenis_skpd', 'Sekretariat')->first() ?? Skpd::firstOrFail();

        $ruangAsal = $this->buatRuangan($skpd);
        $ruangTujuan = $this->buatRuangan($skpd);
        $pegawai = $this->buatPegawai($skpd, $ruangAsal);

        $aset = $this->buatAset($skpd);

        PemegangAset::create([
            'id_aset' => $aset->id_aset,
            'id_pegawai' => $pegawai->id_pegawai,
            'tanggal_mulai' => '2025-01-01',
            'status' => 'aktif',
        ]);
        PenempatanAset::create([
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruangAsal->id_ruangan,
            'tanggal_mulai' => '2025-01-01',
            'status' => 'aktif',
            'is_finalized' => 1,
        ]);

        $res = $this->actingAs($admin)->postJson(route('aset-barang.aset.mutasi', $aset->id_aset), [
            'tipe' => 'ruangan',
            'id_ruangan' => $ruangTujuan->id_ruangan,
        ]);

        $res->assertStatus(422);
    }

    // =========================================================================
    // 3. MUTASI PINDAH RUANGAN (AsetRuanganController) & IDOR PREVENTION
    // =========================================================================

    public function test_mutasi_ruangan_controller_sukses(): void
    {
        $admin = $this->userByRole('Admin Aset');
        $skpd = Skpd::where('jenis_skpd', 'Sekretariat')->first() ?? Skpd::firstOrFail();

        $ruangAsal = $this->buatRuangan($skpd);
        $ruangTujuan = $this->buatRuangan($skpd);

        $aset = $this->buatAset($skpd);

        PenempatanAset::create([
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruangAsal->id_ruangan,
            'tanggal_mulai' => '2025-01-01',
            'status' => 'aktif',
            'is_finalized' => 1,
        ]);

        $res = $this->actingAs($admin)->postJson(route('aset-ruangan.aset.mutasi-ruangan', [
            'ruangan' => $ruangAsal->id_ruangan,
            'aset' => $aset->id_aset,
        ]), [
            'id_ruangan_tujuan' => $ruangTujuan->id_ruangan,
            'keterangan' => 'Pindah ruangan via detail ruangan',
        ]);

        $res->assertOk();
        $res->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('penempatan_aset', [
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruangTujuan->id_ruangan,
            'status' => 'aktif',
            'is_finalized' => 1,
        ]);
    }

    public function test_mutasi_ruangan_controller_ditolak_jika_ruangan_tujuan_tidak_aktif(): void
    {
        $admin = $this->userByRole('Admin Aset');
        $skpd = Skpd::where('jenis_skpd', 'Sekretariat')->first() ?? Skpd::firstOrFail();

        $ruangAsal = $this->buatRuangan($skpd);
        $ruangTujuanTidakAktif = $this->buatRuangan($skpd, 'Nonaktif');

        $aset = $this->buatAset($skpd);

        PenempatanAset::create([
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruangAsal->id_ruangan,
            'tanggal_mulai' => '2025-01-01',
            'status' => 'aktif',
            'is_finalized' => 1,
        ]);

        $res = $this->actingAs($admin)->postJson(route('aset-ruangan.aset.mutasi-ruangan', [
            'ruangan' => $ruangAsal->id_ruangan,
            'aset' => $aset->id_aset,
        ]), [
            'id_ruangan_tujuan' => $ruangTujuanTidakAktif->id_ruangan,
        ]);

        $res->assertStatus(422);
    }

    public function test_mutasi_ruangan_controller_ditolak_jika_idor_ruangan_asal_tidak_cocok(): void
    {
        $admin = $this->userByRole('Admin Aset');
        $skpd = Skpd::where('jenis_skpd', 'Sekretariat')->first() ?? Skpd::firstOrFail();

        $ruangSebenarnya = $this->buatRuangan($skpd);
        $ruangPalsuDiUrl = $this->buatRuangan($skpd);
        $ruangTujuan = $this->buatRuangan($skpd);

        $aset = $this->buatAset($skpd);

        // Aset sebenarnya berada di ruangSebenarnya
        PenempatanAset::create([
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruangSebenarnya->id_ruangan,
            'tanggal_mulai' => '2025-01-01',
            'status' => 'aktif',
            'is_finalized' => 1,
        ]);

        // Penyerang mengirim request melalui URL ruangPalsuDiUrl
        $res = $this->actingAs($admin)->postJson(route('aset-ruangan.aset.mutasi-ruangan', [
            'ruangan' => $ruangPalsuDiUrl->id_ruangan,
            'aset' => $aset->id_aset,
        ]), [
            'id_ruangan_tujuan' => $ruangTujuan->id_ruangan,
        ]);

        $res->assertStatus(422);
    }

    // =========================================================================
    // 4. HAK AKSES PER ROLE
    // =========================================================================

    public function test_admin_bidang_dan_pegawai_ditolak_melakukan_mutasi(): void
    {
        $skpd = Skpd::where('jenis_skpd', 'Sekretariat')->first() ?? Skpd::firstOrFail();
        $ruang = $this->buatRuangan($skpd);
        $aset = $this->buatAset($skpd);

        PenempatanAset::create([
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruang->id_ruangan,
            'tanggal_mulai' => '2025-01-01',
            'status' => 'aktif',
            'is_finalized' => 1,
        ]);

        $userBidang = $this->userByRole('Admin Bidang');
        $userPegawai = $this->userByRole('Pegawai');

        // Admin Bidang ditolak di AsetBarang mutasi
        $this->actingAs($userBidang)->postJson(route('aset-barang.aset.mutasi', $aset->id_aset), [
            'tipe' => 'ruangan',
            'id_ruangan' => $ruang->id_ruangan,
        ])->assertForbidden();

        // Pegawai ditolak di AsetBarang mutasi
        $this->actingAs($userPegawai)->postJson(route('aset-barang.aset.mutasi', $aset->id_aset), [
            'tipe' => 'ruangan',
            'id_ruangan' => $ruang->id_ruangan,
        ])->assertForbidden();

        // Admin Bidang ditolak di AsetRuangan mutasi
        $this->actingAs($userBidang)->postJson(route('aset-ruangan.aset.mutasi-ruangan', [
            'ruangan' => $ruang->id_ruangan,
            'aset' => $aset->id_aset,
        ]), [
            'id_ruangan_tujuan' => $ruang->id_ruangan,
        ])->assertForbidden();

        // Pegawai ditolak di AsetRuangan mutasi
        $this->actingAs($userPegawai)->postJson(route('aset-ruangan.aset.mutasi-ruangan', [
            'ruangan' => $ruang->id_ruangan,
            'aset' => $aset->id_aset,
        ]), [
            'id_ruangan_tujuan' => $ruang->id_ruangan,
        ])->assertForbidden();
    }
}
