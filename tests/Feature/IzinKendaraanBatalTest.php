<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\IzinKendaraan;
use App\Models\Kendaraan;
use App\Models\MasterBarang;
use App\Models\Pegawai;
use App\Models\PenempatanAset;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Skpd;
use App\Models\User;
use App\Services\AsetScope;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IzinKendaraanBatalTest extends TestCase
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

    private function buatAdminAset(): User
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

    private function buatPegawai(int $idSkpd = 1): User
    {
        $role = Role::firstOrCreate(
            ['nama_role' => 'Pegawai'],
            ['deskripsi' => 'Pegawai']
        );

        $skpd = Skpd::find($idSkpd) ?? Skpd::create([
            'id_skpd' => $idSkpd,
            'nama_skpd' => 'SKPD Test ' . $idSkpd,
            'jenis_skpd' => 'Bidang',
        ]);

        $ruangan = Ruangan::firstOrCreate(
            ['nama_ruangan' => 'Ruangan Pegawai ' . $idSkpd],
            ['id_skpd' => $idSkpd, 'status' => 'Aktif']
        );

        $pegawai = Pegawai::create([
            'nama_pegawai' => 'Pegawai ' . uniqid(),
            'nip' => '19900101' . rand(100000, 999999),
            'id_skpd' => $skpd->id_skpd,
            'id_ruangan' => $ruangan->id_ruangan,
            'jabatan' => 'Staf Analis',
        ]);

        return User::create([
            'username' => 'pegawai_' . uniqid(),
            'password' => bcrypt('password123'),
            'id_role' => $role->id_role,
            'id_pegawai' => $pegawai->id_pegawai,
            'status_user' => 'aktif',
        ]);
    }

    private function buatKendaraan(): Kendaraan
    {
        $barang = MasterBarang::firstOrCreate(
            ['nama_barang' => 'Mobil Dinas Operasional Test'],
            ['kode_barang' => 'KND_TEST_' . uniqid(), 'id_kategori' => 2]
        );

        $ruangan = Ruangan::firstOrCreate(
            ['nama_ruangan' => 'Ruangan Kendaraan Test'],
            ['id_skpd' => 1, 'id_lokasi' => 1, 'status' => 'Aktif']
        );

        $aset = Aset::create([
            'id_barang' => $barang->id_barang,
            'id_lokasi' => 1,
            'nomor_kartu_barang' => 'NKB_KND_' . uniqid(),
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
            'nama_kendaraan' => 'Innova Venturer Test ' . uniqid(),
            'status_penggunaan' => 'Tersedia',
        ]);
    }

    private function buatIzinKendaraan(User $userPengaju, Kendaraan $kendaraan, string $status = 'Menunggu'): IzinKendaraan
    {
        return IzinKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'id_pegawai_pengaju' => $userPengaju->pegawai?->id_pegawai,
            'nama_pengemudi' => $userPengaju->pegawai?->nama_pegawai,
            'tujuan' => 'Perjalanan Dinas Lapangan ' . uniqid(),
            'durasi' => '1 Hari (08:00 - 17:00)',
            'tanggal_berangkat' => now()->addDays(2)->format('Y-m-d'),
            'waktu_berangkat' => '08:00:00',
            'tanggal_kembali' => now()->addDays(2)->format('Y-m-d'),
            'waktu_kembali' => '17:00:00',
            'status_approval' => $status,
        ]);
    }

    /**
     * Skenario 1: Pemilik pengajuan berstatus Menunggu dapat membatalkan pengajuannya.
     */
    public function test_pemilik_pengajuan_menunggu_dapat_membatalkan_pengajuannya(): void
    {
        $pemilik = $this->buatPegawai();
        $kendaraan = $this->buatKendaraan();
        $izin = $this->buatIzinKendaraan($pemilik, $kendaraan, 'Menunggu');

        $this->loginSebagai($pemilik);

        // Sebelum batal, pastikan tombol "Batalkan Ajuan" terlihat di halaman layanan
        $resView = $this->get(route('layanan.izin-kendaraan.index'));
        $resView->assertOk();
        $resView->assertSee('Batalkan Ajuan');

        // Eksekusi pembatalan
        $response = $this->post(route('layanan.izin-kendaraan.batal', $izin->id_izin));

        $response->assertRedirect(route('layanan.izin-kendaraan.index'));
        $response->assertSessionHas('success', 'Pengajuan izin kendaraan berhasil dibatalkan.');

        // Verifikasi status di database berubah menjadi Dibatalkan
        $this->assertEquals('Dibatalkan', $izin->fresh()->status_approval);

        // Setelah dibatalkan, status "Dibatalkan" muncul dan tombol "Batalkan Ajuan" tidak ada lagi
        $resAfter = $this->get(route('layanan.izin-kendaraan.index'));
        $resAfter->assertOk();
        $resAfter->assertSee('Dibatalkan');
        $resAfter->assertDontSee('Batalkan Ajuan');
    }

    /**
     * Skenario 2: Pengguna tidak dapat membatalkan pengajuan milik orang lain.
     */
    public function test_pengguna_lain_tidak_dapat_membatalkan_pengajuan_orang_lain(): void
    {
        $pemilik = $this->buatPegawai();
        $penggunaLain = $this->buatPegawai();
        $adminAset = $this->buatAdminAset();
        $kendaraan = $this->buatKendaraan();
        $izin = $this->buatIzinKendaraan($pemilik, $kendaraan, 'Menunggu');

        // A. Pegawai lain mencoba membatalkan -> HTTP 403
        $this->loginSebagai($penggunaLain);
        $resLain = $this->post(route('layanan.izin-kendaraan.batal', $izin->id_izin));
        $resLain->assertForbidden();
        $this->assertEquals('Menunggu', $izin->fresh()->status_approval);

        // B. Admin Aset (bukan pengaju) mencoba membatalkan -> HTTP 403
        $this->loginSebagai($adminAset);
        $resAdmin = $this->post(route('layanan.izin-kendaraan.batal', $izin->id_izin));
        $resAdmin->assertForbidden();
        $this->assertEquals('Menunggu', $izin->fresh()->status_approval);

        // Di tampilan Admin Aset, tombol "Batalkan Ajuan" untuk pengajuan milik orang lain tidak tampil
        $resAdminView = $this->get(route('layanan.izin-kendaraan.index'));
        $resAdminView->assertOk();
        $resAdminView->assertDontSee('Batalkan Ajuan');
    }

    /**
     * Skenario 3: Pengajuan Disetujui tidak dapat dibatalkan.
     */
    public function test_pengajuan_disetujui_tidak_dapat_dibatalkan(): void
    {
        $pemilik = $this->buatPegawai();
        $kendaraan = $this->buatKendaraan();
        $izin = $this->buatIzinKendaraan($pemilik, $kendaraan, 'Disetujui');

        $this->loginSebagai($pemilik);

        // Tombol Batalkan Ajuan tidak tampil di view
        $resView = $this->get(route('layanan.izin-kendaraan.index'));
        $resView->assertOk();
        $resView->assertDontSee('Batalkan Ajuan');

        // Request langsung ke route batal ditolak HTTP 403
        $response = $this->post(route('layanan.izin-kendaraan.batal', $izin->id_izin));
        $response->assertForbidden();

        $this->assertEquals('Disetujui', $izin->fresh()->status_approval);
    }

    /**
     * Skenario 4: Pengajuan Ditolak tidak dapat dibatalkan.
     */
    public function test_pengajuan_ditolak_tidak_dapat_dibatalkan(): void
    {
        $pemilik = $this->buatPegawai();
        $kendaraan = $this->buatKendaraan();
        $izin = $this->buatIzinKendaraan($pemilik, $kendaraan, 'Ditolak');

        $this->loginSebagai($pemilik);

        // Tombol Batalkan Ajuan tidak tampil di view
        $resView = $this->get(route('layanan.izin-kendaraan.index'));
        $resView->assertOk();
        $resView->assertDontSee('Batalkan Ajuan');

        // Request langsung ke route batal ditolak HTTP 403
        $response = $this->post(route('layanan.izin-kendaraan.batal', $izin->id_izin));
        $response->assertForbidden();

        $this->assertEquals('Ditolak', $izin->fresh()->status_approval);
    }

    /**
     * Skenario 5: Aksi Terima/ACC dan Tolak oleh Admin Aset tetap berfungsi normal.
     */
    public function test_aksi_terima_dan_tolak_admin_aset_tetap_berfungsi(): void
    {
        $adminAset = $this->buatAdminAset();
        $pemilik = $this->buatPegawai();
        $kendaraan = $this->buatKendaraan();

        // 5.1 Test Setujui / ACC untuk izin masa depan (status_penggunaan tetap Tersedia)
        $izinMasaDepan = $this->buatIzinKendaraan($pemilik, $kendaraan, 'Menunggu');
        $this->loginSebagai($adminAset);

        $resAccMasaDepan = $this->post(route('layanan.izin-kendaraan.status', $izinMasaDepan->id_izin), [
            'status_approval' => 'Disetujui',
        ]);
        $resAccMasaDepan->assertRedirect(route('layanan.izin-kendaraan.index'));
        $this->assertEquals('Disetujui', $izinMasaDepan->fresh()->status_approval);
        $this->assertEquals('Tersedia', $kendaraan->fresh()->status_penggunaan);

        // 5.1b Test Setujui / ACC untuk izin yang sedang berlangsung hari ini (status_penggunaan menjadi Digunakan)
        $izinAktif = IzinKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'id_pegawai_pengaju' => $pemilik->pegawai?->id_pegawai,
            'nama_pengemudi' => $pemilik->pegawai?->nama_pegawai,
            'tujuan' => 'Perjalanan Dinas Aktif',
            'durasi' => '1 Hari',
            'tanggal_berangkat' => now()->toDateString(),
            'waktu_berangkat' => '00:01:00',
            'tanggal_kembali' => now()->toDateString(),
            'waktu_kembali' => '23:59:00',
            'status_approval' => 'Menunggu',
        ]);

        $resAccAktif = $this->post(route('layanan.izin-kendaraan.status', $izinAktif->id_izin), [
            'status_approval' => 'Disetujui',
        ]);
        $resAccAktif->assertRedirect(route('layanan.izin-kendaraan.index'));
        $this->assertEquals('Disetujui', $izinAktif->fresh()->status_approval);
        $this->assertEquals('Digunakan', $kendaraan->fresh()->status_penggunaan);

        // 5.2 Test Tolak (dengan alasan penolakan)
        $izinTolak = $this->buatIzinKendaraan($pemilik, $kendaraan, 'Menunggu');
        $resTolak = $this->post(route('layanan.izin-kendaraan.status', $izinTolak->id_izin), [
            'status_approval' => 'Ditolak',
            'alasan_penolakan' => 'Kendaraan dijadwalkan servis berkala.',
        ]);
        $resTolak->assertRedirect(route('layanan.izin-kendaraan.index'));
        $this->assertEquals('Ditolak', $izinTolak->fresh()->status_approval);
        $this->assertEquals('Kendaraan dijadwalkan servis berkala.', $izinTolak->fresh()->alasan_penolakan);
    }

    /**
     * Skenario 6: Aksi Unduh Surat dan Kembalikan Kendaraan tetap berfungsi untuk pengajuan Disetujui.
     */
    public function test_aksi_unduh_surat_dan_kembalikan_kendaraan_tetap_berfungsi(): void
    {
        $pemilik = $this->buatPegawai();
        $kendaraan = $this->buatKendaraan();
        $izin = $this->buatIzinKendaraan($pemilik, $kendaraan, 'Disetujui');
        $kendaraan->update(['status_penggunaan' => 'Digunakan']);

        $this->loginSebagai($pemilik);

        // 6.1 Unduh surat izin (.docx)
        $resUnduh = $this->get(route('layanan.izin-kendaraan.download', $izin->id_izin));
        $resUnduh->assertOk();
        $this->assertStringContainsString('wordprocessingml.document', $resUnduh->headers->get('Content-Type') ?? '');

        // 6.2 Kembalikan kendaraan
        $resKembalikan = $this->post(route('layanan.izin-kendaraan.selesai', $izin->id_izin), [
            'catatan_kondisi' => 'Kendaraan dikembalikan dalam kondisi bersih dan bensin terisi.',
            'waktu_pengembalian' => now()->format('Y-m-d H:i:s'),
        ]);

        $resKembalikan->assertRedirect(route('layanan.izin-kendaraan.index'));
        $this->assertEquals('Selesai', $izin->fresh()->status_approval);
        $this->assertEquals('Tersedia', $kendaraan->fresh()->status_penggunaan);
    }

    /**
     * Skenario 7: Riwayat pengajuan yang dibatalkan tetap tersimpan dan tidak dihapus permanen.
     */
    public function test_riwayat_pengajuan_yang_dibatalkan_tetap_tersimpan_dan_tidak_dihapus(): void
    {
        $pemilik = $this->buatPegawai();
        $kendaraan = $this->buatKendaraan();
        $izin = $this->buatIzinKendaraan($pemilik, $kendaraan, 'Menunggu');

        $totalAwal = IzinKendaraan::count();
        $this->loginSebagai($pemilik);

        $this->post(route('layanan.izin-kendaraan.batal', $izin->id_izin));

        // Data tidak terhapus (count sama)
        $this->assertEquals($totalAwal, IzinKendaraan::count());

        // Record tetap ada di database dengan status Dibatalkan
        $record = IzinKendaraan::find($izin->id_izin);
        $this->assertNotNull($record);
        $this->assertEquals('Dibatalkan', $record->status_approval);
        $this->assertEquals($pemilik->pegawai->id_pegawai, $record->id_pegawai_pengaju);

        // Tampil pada daftar riwayat pengajuan milik pemohon
        $resView = $this->get(route('layanan.izin-kendaraan.index'));
        $resView->assertOk();
        $resView->assertSee('Dibatalkan');
        $resView->assertSee($kendaraan->nama_kendaraan);
    }
}
