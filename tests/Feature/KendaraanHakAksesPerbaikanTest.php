<?php

namespace Tests\Feature;

use App\Http\Controllers\IzinKendaraanController;
use App\Models\Aset;
use App\Models\DetailMutasiAset;
use App\Models\IzinKendaraan;
use App\Models\Kendaraan;
use App\Models\MasterBarang;
use App\Models\MutasiAset;
use App\Models\PajakKendaraan;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\PenempatanAset;
use App\Models\RiwayatPlat;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Skpd;
use App\Models\User;
use App\Services\AsetScope;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KendaraanHakAksesPerbaikanTest extends TestCase
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

    private function buatUserRole(string $namaRole, int $idSkpd = 1): User
    {
        $role = Role::firstOrCreate(
            ['nama_role' => $namaRole],
            ['deskripsi' => $namaRole]
        );

        $skpd = Skpd::find($idSkpd) ?? Skpd::create([
            'id_skpd' => $idSkpd,
            'nama_skpd' => 'SKPD Test ' . $idSkpd,
            'jenis_skpd' => $namaRole === 'Admin UPT' ? 'UPT' : 'Bidang',
        ]);

        $pegawai = Pegawai::create([
            'nama_pegawai' => $namaRole . ' ' . uniqid(),
            'nip' => '19900101' . rand(100000, 999999),
            'id_skpd' => $skpd->id_skpd,
            'jabatan' => 'Staf Test',
        ]);

        return User::create([
            'username' => strtolower(str_replace(' ', '_', $namaRole)) . '_' . uniqid(),
            'password' => bcrypt('password123'),
            'id_role' => $role->id_role,
            'id_pegawai' => $pegawai->id_pegawai,
            'status_user' => 'aktif',
        ]);
    }

    private function buatKendaraanLengkap(int $idSkpd = 1, ?Pegawai $pemegang = null): array
    {
        $barang = MasterBarang::firstOrCreate(
            ['kode_barang' => 'KD-TEST-' . rand(1000, 9999)],
            ['nama_barang' => 'Mobil Dinas Test', 'id_kategori' => 1]
        );

        $aset = Aset::create([
            'id_barang' => $barang->id_barang,
            'id_skpd' => $idSkpd,
            'id_lokasi' => 1,
            'nomor_kartu_barang' => 'KIB-KD-' . uniqid(),
            'status_aset' => 'aktif',
            'kondisi' => 'Baik',
            'nilai_perolehan' => 150000000,
            'tanggal_pengadaan' => now()->subYears(2)->toDateString(),
            'tanggal_habis_pakai' => now()->addYears(5)->toDateString(),
        ]);

        $ruangan = Ruangan::firstOrCreate(
            ['id_skpd' => $idSkpd, 'nama_ruangan' => 'Ruang Dinas Test ' . $idSkpd],
            ['kode_ruangan' => 'R-KD-' . rand(100, 999), 'id_lokasi' => 1]
        );

        $penempatan = PenempatanAset::create([
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruangan->id_ruangan,
            'tanggal_mulai' => now()->subYears(2)->toDateString(),
            'status' => 'aktif',
            'is_finalized' => 1,
            'finalized_at' => now()->subYears(2),
        ]);

        $kendaraan = Kendaraan::create([
            'id_aset' => $aset->id_aset,
            'nama_kendaraan' => 'Toyota Innova Reborn ' . uniqid(),
            'jenis_kendaraan' => 'Roda Empat (Mobil)',
            'nomor_rangka' => 'MHR' . rand(100000, 999999),
            'nomor_mesin' => '2TR' . rand(100000, 999999),
            'merk' => 'Toyota',
            'tipe' => 'Innova',
            'pemegang' => $pemegang?->nama_pegawai,
            'status_penggunaan' => 'Tersedia',
        ]);

        $plat = RiwayatPlat::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'nomor_plat' => 'L ' . rand(1000, 9999) . ' WZ',
            'tanggal_berlaku' => now()->addYears(3)->toDateString(),
            'status' => 'Aktif',
            'ganti_plat' => false,
        ]);

        if ($pemegang) {
            PemegangAset::create([
                'id_aset' => $aset->id_aset,
                'id_pegawai' => $pemegang->id_pegawai,
                'tanggal_mulai' => now()->subYears(1)->toDateString(),
                'status' => 'aktif',
            ]);
        }

        return [$kendaraan, $aset, $plat, $ruangan, $penempatan];
    }

    /**
     * 1. Verifikasi route legacy peminjaman kendaraan sudah tidak ada (404 Not Found).
     */
    public function test_route_legacy_peminjaman_tidak_dapat_diakses(): void
    {
        $admin = $this->buatUserRole('Admin Aset');
        [$kendaraan] = $this->buatKendaraanLengkap();
        $this->loginSebagai($admin);

        // kendaraan.izin.store sudah dihapus
        $resStore = $this->post("/kendaraan/{$kendaraan->id_kendaraan}/izin", []);
        $resStore->assertNotFound();

        // kendaraan.izin.approve sudah dihapus
        $resApprove = $this->post("/kendaraan/{$kendaraan->id_kendaraan}/izin/1", []);
        $resApprove->assertNotFound();

        // kendaraan.izin.destroy sudah dihapus
        $resDestroy = $this->delete("/kendaraan/{$kendaraan->id_kendaraan}/izin/1");
        $resDestroy->assertNotFound();
    }

    /**
     * 2. Verifikasi hak akses CUD kendaraan hanya untuk Admin Aset.
     */
    public function test_hak_akses_cud_kendaraan(): void
    {
        $adminAset = $this->buatUserRole('Admin Aset');
        $adminUpt = $this->buatUserRole('Admin UPT');
        $adminBidang = $this->buatUserRole('Admin Bidang');
        $pegawai = $this->buatUserRole('Pegawai');

        [$knd] = $this->buatKendaraanLengkap();

        // Admin Aset dapat mengakses CUD endpoints
        $this->loginSebagai($adminAset);
        $resCreate = $this->get(route('kendaraan.create'));
        $resCreate->assertOk();

        // Non-Admin Aset ditolak (403) pada operasi CUD
        foreach ([$adminUpt, $adminBidang, $pegawai] as $user) {
            $this->loginSebagai($user);

            $this->post(route('kendaraan.store'), [])->assertForbidden();
            $this->post(route('kendaraan.update', $knd->id_kendaraan), [])->assertForbidden();
            $this->delete(route('kendaraan.destroy', $knd->id_kendaraan))->assertForbidden();
            $this->post(route('kendaraan.import'), [])->assertForbidden();
        }
    }

    /**
     * 3. Verifikasi tautan pemegang kendaraan:
     * - Admin Aset & Admin UPT melihat tautan ke master-data.pegawai.show
     * - Admin Bidang & Pegawai melihat teks biasa (bukan link, dan tidak ada rute aset-barang.show)
     */
    public function test_tampilan_pemegang_kendaraan_pada_berbagai_role(): void
    {
        $adminAset = $this->buatUserRole('Admin Aset');
        $adminUpt = $this->buatUserRole('Admin UPT');
        $adminBidang = $this->buatUserRole('Admin Bidang');
        $pegawaiUser = $this->buatUserRole('Pegawai');

        $pemegangPegawai = Pegawai::create([
            'nama_pegawai' => 'Drs. Pemegang Uji Coba',
            'nip' => '198001012005011001',
            'id_skpd' => 1,
            'jabatan' => 'Kepala Seksi',
        ]);

        [$kendaraan] = $this->buatKendaraanLengkap(idSkpd: 1, pemegang: $pemegangPegawai);

        $expectedPegawaiUrl = route('master-data.pegawai.show', $pemegangPegawai->id_pegawai);

        // 3.1 Admin Aset: dapat tautan ke profil pegawai
        $this->loginSebagai($adminAset);
        $resIndexAset = $this->get(route('kendaraan.index'));
        $resIndexAset->assertOk();
        $resIndexAset->assertSee($expectedPegawaiUrl, false);
        $resIndexAset->assertDontSee('/aset-barang/' . $pemegangPegawai->id_pegawai, false);

        $resShowAset = $this->get(route('kendaraan.show', $kendaraan->id_kendaraan));
        $resShowAset->assertOk();
        $resShowAset->assertSee($expectedPegawaiUrl, false);

        // 3.2 Admin UPT: dapat tautan ke profil pegawai
        $this->loginSebagai($adminUpt);
        $resIndexUpt = $this->get(route('kendaraan.index'));
        $resIndexUpt->assertOk();
        $resIndexUpt->assertSee($expectedPegawaiUrl, false);

        // 3.3 Admin Bidang: teks biasa, TIDAK ada link ke profil pegawai dan TIDAK ada aset-barang.show
        $this->loginSebagai($adminBidang);
        $resIndexBidang = $this->get(route('kendaraan.index'));
        $resIndexBidang->assertOk();
        $resIndexBidang->assertSee('Drs. Pemegang Uji Coba');
        $resIndexBidang->assertDontSee($expectedPegawaiUrl, false);
        $resIndexBidang->assertDontSee('/aset-barang/' . $pemegangPegawai->id_pegawai, false);

        $resShowBidang = $this->get(route('kendaraan.show', $kendaraan->id_kendaraan));
        $resShowBidang->assertForbidden();

        // 3.4 Pegawai: teks biasa, TIDAK ada link ke profil pegawai dan TIDAK ada aset-barang.show
        $this->loginSebagai($pegawaiUser);
        $resIndexPegawai = $this->get(route('kendaraan.index'));
        $resIndexPegawai->assertOk();
        $resIndexPegawai->assertSee('Drs. Pemegang Uji Coba');
        $resIndexPegawai->assertDontSee($expectedPegawaiUrl, false);
        $resIndexPegawai->assertDontSee('/aset-barang/' . $pemegangPegawai->id_pegawai, false);
    }

    /**
     * 4. Verifikasi bukti pembayaran pajak:
     * - Hanya Admin Aset yang boleh mengunduh / pratinjau bukti
     * - Admin Bidang, Admin UPT, dan Pegawai ditolak HTTP 403
     */
    public function test_otorisasi_bukti_pembayaran_pajak(): void
    {
        Storage::fake('public');

        $adminAset = $this->buatUserRole('Admin Aset');
        $adminUpt = $this->buatUserRole('Admin UPT');
        $adminBidang = $this->buatUserRole('Admin Bidang');
        $pegawai = $this->buatUserRole('Pegawai');

        [$kendaraan] = $this->buatKendaraanLengkap();

        $path = 'pajak/bukti_test_' . uniqid() . '.pdf';
        Storage::disk('public')->put($path, '%PDF-1.4 dummy test');

        $pajak = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tanggal_bayar' => now()->subDays(10)->toDateString(),
            'tanggal_berakhir' => now()->addDays(355)->toDateString(),
            'nominal' => 1250000,
            'total_pajak' => 1250000,
            'status' => 'Aktif',
            'pajak_5_tahunan' => false,
            'bukti_pajak_path' => $path,
            'bukti_pajak_nama' => 'bukti_pkb.pdf',
        ]);

        // 4.1 Admin Aset dapat mengunduh dan pratinjau
        $this->loginSebagai($adminAset);
        $resDownload = $this->get(route('kendaraan.pajak.bukti', [$kendaraan->id_kendaraan, $pajak->id_pajak]));
        $resDownload->assertOk();

        $resPreview = $this->get(route('kendaraan.pajak.preview', [$kendaraan->id_kendaraan, $pajak->id_pajak]));
        $resPreview->assertOk();

        // 4.2 Non-Admin Aset ditolak HTTP 403 di backend
        foreach ([$adminUpt, $adminBidang, $pegawai] as $user) {
            $this->loginSebagai($user);

            $this->get(route('kendaraan.pajak.bukti', [$kendaraan->id_kendaraan, $pajak->id_pajak]))
                ->assertForbidden();

            $this->get(route('kendaraan.pajak.preview', [$kendaraan->id_kendaraan, $pajak->id_pajak]))
                ->assertForbidden();
        }
    }

    /**
     * 5. Verifikasi status penggunaan dinamis:
     * - Izin disetujui di masa depan TIDAK mengubah status_penggunaan menjadi 'Digunakan' hari ini.
     * - Izin disetujui untuk waktu sekarang mengubah status_penggunaan menjadi 'Digunakan'.
     */
    public function test_status_penggunaan_dinamis_kendaraan(): void
    {
        $adminAset = $this->buatUserRole('Admin Aset');
        $pemohon = $this->buatUserRole('Pegawai');

        [$kendaraan] = $this->buatKendaraanLengkap();
        $this->assertEquals('Tersedia', $kendaraan->status_penggunaan);

        // 5.1 Pengajuan izin 3 hari di masa depan
        $izinMasaDepan = IzinKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'id_pegawai_pengaju' => $pemohon->pegawai?->id_pegawai,
            'nama_pengemudi' => 'Driver Masa Depan',
            'tujuan' => 'Dinas Masa Depan',
            'durasi' => '1 Hari',
            'tanggal_berangkat' => now()->addDays(3)->toDateString(),
            'waktu_berangkat' => '08:00:00',
            'tanggal_kembali' => now()->addDays(3)->toDateString(),
            'waktu_kembali' => '17:00:00',
            'status_approval' => 'Menunggu',
        ]);

        $this->loginSebagai($adminAset);
        $resApprove = $this->post(route('layanan.izin-kendaraan.status', $izinMasaDepan->id_izin), [
            'status_approval' => 'Disetujui',
        ]);
        $resApprove->assertRedirect(route('layanan.izin-kendaraan.index'));

        // Status approval Disetujui, tapi kendaraan TETAP Tersedia hari ini!
        $this->assertEquals('Disetujui', $izinMasaDepan->fresh()->status_approval);
        $this->assertEquals('Tersedia', $kendaraan->fresh()->status_penggunaan);

        // 5.2 Pengajuan izin untuk hari ini (sekarang)
        $izinHariIni = IzinKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'id_pegawai_pengaju' => $pemohon->pegawai?->id_pegawai,
            'nama_pengemudi' => 'Driver Hari Ini',
            'tujuan' => 'Dinas Hari Ini',
            'durasi' => '1 Hari',
            'tanggal_berangkat' => now()->toDateString(),
            'waktu_berangkat' => '00:01:00',
            'tanggal_kembali' => now()->toDateString(),
            'waktu_kembali' => '23:59:00',
            'status_approval' => 'Menunggu',
        ]);

        $resApproveHariIni = $this->post(route('layanan.izin-kendaraan.status', $izinHariIni->id_izin), [
            'status_approval' => 'Disetujui',
        ]);
        $resApproveHariIni->assertRedirect(route('layanan.izin-kendaraan.index'));

        // Status kendaraan menjadi Digunakan karena jadwal aktif saat ini
        $this->assertEquals('Disetujui', $izinHariIni->fresh()->status_approval);
        $this->assertEquals('Digunakan', $kendaraan->fresh()->status_penggunaan);

        // 5.3 Saat diselesaikan / dikembalikan, status kembali Tersedia
        $this->loginSebagai($pemohon);
        $resSelesai = $this->post(route('layanan.izin-kendaraan.selesai', $izinHariIni->id_izin), [
            'catatan_kondisi' => 'Selesai tanpa kendala.',
        ]);
        $resSelesai->assertRedirect(route('layanan.izin-kendaraan.index'));
        $this->assertEquals('Selesai', $izinHariIni->fresh()->status_approval);
        $this->assertEquals('Tersedia', $kendaraan->fresh()->status_penggunaan);
    }

    /**
     * 6. Verifikasi export kendaraan menyertakan platAktif tanpa error.
     */
    public function test_ekspor_kendaraan_berhasil(): void
    {
        $adminAset = $this->buatUserRole('Admin Aset');
        [$kendaraan] = $this->buatKendaraanLengkap();

        $this->loginSebagai($adminAset);
        $response = $this->get(route('kendaraan.export', ['mode' => 'semua']));
        $response->assertOk();
        $this->assertTrue(str_contains((string) $response->headers->get('content-disposition'), '.xlsx'));
    }
}

