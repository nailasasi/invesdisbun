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
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IzinKendaraanAutoRejectTest extends TestCase
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

    private function buatKendaraan(bool $isFinalized = true, string $statusPenggunaan = 'Tersedia'): Kendaraan
    {
        $barang = MasterBarang::firstOrCreate(
            ['nama_barang' => 'Mobil Dinas Test ' . uniqid()],
            ['kode_barang' => 'KND_TEST_' . uniqid(), 'id_kategori' => 2]
        );

        $ruangan = Ruangan::firstOrCreate(
            ['nama_ruangan' => 'Pool Kendaraan'],
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

        if ($isFinalized) {
            PenempatanAset::create([
                'id_aset' => $aset->id_aset,
                'id_ruangan' => $ruangan->id_ruangan,
                'tanggal_mulai' => now()->toDateString(),
                'status' => 'aktif',
                'is_finalized' => 1,
                'finalized_at' => now(),
            ]);
        }

        return Kendaraan::create([
            'id_aset' => $aset->id_aset,
            'nama_kendaraan' => 'Kendaraan Test ' . uniqid(),
            'status_penggunaan' => $statusPenggunaan,
        ]);
    }

    private function buatPegawaiRuangAset(): Pegawai
    {
        $ruangAset = Ruangan::firstOrCreate(
            ['nama_ruangan' => 'Ruang Aset'],
            ['id_skpd' => 1, 'id_lokasi' => 1, 'status' => 'Aktif']
        );

        return Pegawai::create([
            'nama_pegawai' => 'Pengurus Ruang Aset ' . uniqid(),
            'nip' => '19880101' . rand(100000, 999999),
            'id_skpd' => 1,
            'id_ruangan' => $ruangAset->id_ruangan,
            'jabatan' => 'Penata Laksana Barang',
        ]);
    }

    private function buatIzin(Kendaraan $kendaraan, User $pengaju, array $attributes = []): IzinKendaraan
    {
        return IzinKendaraan::create(array_merge([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'id_pegawai_pengaju' => $pengaju->pegawai?->id_pegawai,
            'id_pengurus_barang' => $this->buatPegawaiRuangAset()->id_pegawai,
            'nama_pengemudi' => 'Driver ' . uniqid(),
            'tujuan' => 'Dinas Lapangan',
            'durasi' => '4 jam',
            'tanggal_berangkat' => '2026-12-01',
            'waktu_berangkat' => '08:00:00',
            'tanggal_kembali' => '2026-12-01',
            'waktu_kembali' => '12:00:00',
            'status_approval' => 'Menunggu',
            'alasan_penolakan' => null,
        ], $attributes));
    }

    /**
     * Skenario 1: Dua permohonan kendaraan sama dan jadwal berbenturan: approval pertama berhasil.
     */
    public function test_skenario_1_dua_permohonan_jadwal_berbenturan_approval_pertama_berhasil(): void
    {
        $admin = $this->buatAdminAset();
        $pegawai1 = $this->buatPegawai();
        $pegawai2 = $this->buatPegawai();
        $kendaraan = $this->buatKendaraan();

        $izin1 = $this->buatIzin($kendaraan, $pegawai1, [
            'tanggal_berangkat' => '2026-12-05',
            'waktu_berangkat' => '08:00:00',
            'tanggal_kembali' => '2026-12-05',
            'waktu_kembali' => '12:00:00',
        ]);

        $this->loginSebagai($admin);

        $response = $this->post(route('layanan.izin-kendaraan.status', $izin1), [
            'status_approval' => 'Disetujui',
        ]);

        $response->assertRedirect(route('layanan.izin-kendaraan.index'));
        $response->assertSessionHas('success', 'Pengajuan izin kendaraan disetujui.');

        $this->assertEquals('Disetujui', $izin1->fresh()->status_approval);
        $this->assertNull($izin1->fresh()->alasan_penolakan);
    }

    /**
     * Skenario 2: Approval kedua otomatis mengubah permohonan menjadi Ditolak.
     */
    public function test_skenario_2_approval_kedua_otomatis_mengubah_permohonan_menjadi_ditolak(): void
    {
        $admin = $this->buatAdminAset();
        $pegawai1 = $this->buatPegawai();
        $pegawai2 = $this->buatPegawai();
        $kendaraan = $this->buatKendaraan();

        $izin1 = $this->buatIzin($kendaraan, $pegawai1, [
            'tanggal_berangkat' => '2026-12-05',
            'waktu_berangkat' => '08:00:00',
            'tanggal_kembali' => '2026-12-05',
            'waktu_kembali' => '12:00:00',
            'status_approval' => 'Disetujui',
        ]);

        $izin2 = $this->buatIzin($kendaraan, $pegawai2, [
            'tanggal_berangkat' => '2026-12-05',
            'waktu_berangkat' => '10:00:00',
            'tanggal_kembali' => '2026-12-05',
            'waktu_kembali' => '14:00:00',
            'status_approval' => 'Menunggu',
        ]);

        $this->loginSebagai($admin);

        $response = $this->post(route('layanan.izin-kendaraan.status', $izin2), [
            'status_approval' => 'Disetujui',
        ]);

        $response->assertRedirect(route('layanan.izin-kendaraan.index'));
        $response->assertSessionHas('error', 'Permohonan izin otomatis ditolak karena kendaraan digunakan.');

        $this->assertEquals('Ditolak', $izin2->fresh()->status_approval);
    }

    /**
     * Skenario 3: Alasan penolakan otomatis tersimpan persis "Kendaraan digunakan".
     */
    public function test_skenario_3_alasan_penolakan_otomatis_tersimpan_persis_kendaraan_digunakan(): void
    {
        $admin = $this->buatAdminAset();
        $pegawai1 = $this->buatPegawai();
        $pegawai2 = $this->buatPegawai();
        $kendaraan = $this->buatKendaraan();

        $izin1 = $this->buatIzin($kendaraan, $pegawai1, [
            'tanggal_berangkat' => '2026-12-06',
            'waktu_berangkat' => '09:00:00',
            'tanggal_kembali' => '2026-12-06',
            'waktu_kembali' => '15:00:00',
            'status_approval' => 'Disetujui',
        ]);

        $izin2 = $this->buatIzin($kendaraan, $pegawai2, [
            'tanggal_berangkat' => '2026-12-06',
            'waktu_berangkat' => '11:00:00',
            'tanggal_kembali' => '2026-12-06',
            'waktu_kembali' => '13:00:00',
            'status_approval' => 'Menunggu',
        ]);

        $this->loginSebagai($admin);

        $this->post(route('layanan.izin-kendaraan.status', $izin2), [
            'status_approval' => 'Disetujui',
        ]);

        $this->assertSame('Kendaraan digunakan', $izin2->fresh()->alasan_penolakan);
    }

    /**
     * Skenario 4: Permohonan pertama tetap Disetujui.
     */
    public function test_skenario_4_permohonan_pertama_tetap_disetujui(): void
    {
        $admin = $this->buatAdminAset();
        $pegawai1 = $this->buatPegawai();
        $pegawai2 = $this->buatPegawai();
        $kendaraan = $this->buatKendaraan();

        $izin1 = $this->buatIzin($kendaraan, $pegawai1, [
            'tanggal_berangkat' => '2026-12-07',
            'waktu_berangkat' => '08:00:00',
            'tanggal_kembali' => '2026-12-07',
            'waktu_kembali' => '11:00:00',
            'status_approval' => 'Disetujui',
        ]);

        $izin2 = $this->buatIzin($kendaraan, $pegawai2, [
            'tanggal_berangkat' => '2026-12-07',
            'waktu_berangkat' => '09:00:00',
            'tanggal_kembali' => '2026-12-07',
            'waktu_kembali' => '12:00:00',
            'status_approval' => 'Menunggu',
        ]);

        $this->loginSebagai($admin);

        // Approval permohonan kedua berbenturan
        $this->post(route('layanan.izin-kendaraan.status', $izin2), [
            'status_approval' => 'Disetujui',
        ]);

        // Pastikan izin 1 sama sekali tidak berubah
        $this->assertEquals('Disetujui', $izin1->fresh()->status_approval);
        $this->assertNull($izin1->fresh()->alasan_penolakan);
    }

    /**
     * Skenario 5: Kendaraan sama dengan jadwal tidak berbenturan: keduanya boleh disetujui.
     */
    public function test_skenario_5_kendaraan_sama_jadwal_tidak_berbenturan_keduanya_boleh_disetujui(): void
    {
        $admin = $this->buatAdminAset();
        $pegawai1 = $this->buatPegawai();
        $pegawai2 = $this->buatPegawai();
        $kendaraan = $this->buatKendaraan();

        $izin1 = $this->buatIzin($kendaraan, $pegawai1, [
            'tanggal_berangkat' => '2026-12-08',
            'waktu_berangkat' => '08:00:00',
            'tanggal_kembali' => '2026-12-08',
            'waktu_kembali' => '10:00:00',
            'status_approval' => 'Menunggu',
        ]);

        $izin2 = $this->buatIzin($kendaraan, $pegawai2, [
            'tanggal_berangkat' => '2026-12-08',
            'waktu_berangkat' => '14:00:00',
            'tanggal_kembali' => '2026-12-08',
            'waktu_kembali' => '17:00:00',
            'status_approval' => 'Menunggu',
        ]);

        $this->loginSebagai($admin);

        // Setujui izin 1
        $res1 = $this->post(route('layanan.izin-kendaraan.status', $izin1), [
            'status_approval' => 'Disetujui',
        ]);
        $res1->assertSessionHas('success', 'Pengajuan izin kendaraan disetujui.');

        // Setujui izin 2
        $res2 = $this->post(route('layanan.izin-kendaraan.status', $izin2), [
            'status_approval' => 'Disetujui',
        ]);
        $res2->assertSessionHas('success', 'Pengajuan izin kendaraan disetujui.');

        $this->assertEquals('Disetujui', $izin1->fresh()->status_approval);
        $this->assertEquals('Disetujui', $izin2->fresh()->status_approval);
    }

    /**
     * Skenario 6: Kendaraan berbeda dengan jadwal sama: keduanya boleh disetujui.
     */
    public function test_skenario_6_kendaraan_berbeda_jadwal_sama_keduanya_boleh_disetujui(): void
    {
        $admin = $this->buatAdminAset();
        $pegawai1 = $this->buatPegawai();
        $pegawai2 = $this->buatPegawai();
        $kendaraan1 = $this->buatKendaraan();
        $kendaraan2 = $this->buatKendaraan();

        $izin1 = $this->buatIzin($kendaraan1, $pegawai1, [
            'tanggal_berangkat' => '2026-12-09',
            'waktu_berangkat' => '08:00:00',
            'tanggal_kembali' => '2026-12-09',
            'waktu_kembali' => '12:00:00',
            'status_approval' => 'Menunggu',
        ]);

        $izin2 = $this->buatIzin($kendaraan2, $pegawai2, [
            'tanggal_berangkat' => '2026-12-09',
            'waktu_berangkat' => '08:00:00',
            'tanggal_kembali' => '2026-12-09',
            'waktu_kembali' => '12:00:00',
            'status_approval' => 'Menunggu',
        ]);

        $this->loginSebagai($admin);

        $res1 = $this->post(route('layanan.izin-kendaraan.status', $izin1), [
            'status_approval' => 'Disetujui',
        ]);
        $res1->assertSessionHas('success');

        $res2 = $this->post(route('layanan.izin-kendaraan.status', $izin2), [
            'status_approval' => 'Disetujui',
        ]);
        $res2->assertSessionHas('success');

        $this->assertEquals('Disetujui', $izin1->fresh()->status_approval);
        $this->assertEquals('Disetujui', $izin2->fresh()->status_approval);
    }

    /**
     * Skenario 7: Jadwal bersambung tepat pada batas waktu: keduanya boleh disetujui.
     * Contoh: 08:00 - 12:00 dan 12:00 - 16:00.
     */
    public function test_skenario_7_jadwal_bersambung_tepat_pada_batas_waktu_keduanya_boleh_disetujui(): void
    {
        $admin = $this->buatAdminAset();
        $pegawai1 = $this->buatPegawai();
        $pegawai2 = $this->buatPegawai();
        $kendaraan = $this->buatKendaraan();

        $izin1 = $this->buatIzin($kendaraan, $pegawai1, [
            'tanggal_berangkat' => '2026-12-10',
            'waktu_berangkat' => '08:00:00',
            'tanggal_kembali' => '2026-12-10',
            'waktu_kembali' => '12:00:00',
            'status_approval' => 'Menunggu',
        ]);

        $izin2 = $this->buatIzin($kendaraan, $pegawai2, [
            'tanggal_berangkat' => '2026-12-10',
            'waktu_berangkat' => '12:00:00',
            'tanggal_kembali' => '2026-12-10',
            'waktu_kembali' => '16:00:00',
            'status_approval' => 'Menunggu',
        ]);

        $this->loginSebagai($admin);

        $res1 = $this->post(route('layanan.izin-kendaraan.status', $izin1), [
            'status_approval' => 'Disetujui',
        ]);
        $res1->assertSessionHas('success');

        $res2 = $this->post(route('layanan.izin-kendaraan.status', $izin2), [
            'status_approval' => 'Disetujui',
        ]);
        $res2->assertSessionHas('success');

        $this->assertEquals('Disetujui', $izin1->fresh()->status_approval);
        $this->assertEquals('Disetujui', $izin2->fresh()->status_approval);
    }

    /**
     * Skenario 8: Permohonan Ditolak tidak menghalangi permohonan lain.
     */
    public function test_skenario_8_permohonan_ditolak_tidak_menghalangi_permohonan_lain(): void
    {
        $admin = $this->buatAdminAset();
        $pegawai1 = $this->buatPegawai();
        $pegawai2 = $this->buatPegawai();
        $kendaraan = $this->buatKendaraan();

        // Izin 1 berstatus Ditolak pada jadwal 08:00 - 12:00
        $izin1 = $this->buatIzin($kendaraan, $pegawai1, [
            'tanggal_berangkat' => '2026-12-11',
            'waktu_berangkat' => '08:00:00',
            'tanggal_kembali' => '2026-12-11',
            'waktu_kembali' => '12:00:00',
            'status_approval' => 'Ditolak',
            'alasan_penolakan' => 'Kendaraan dalam perbaikan',
        ]);

        // Izin 2 pada jadwal yang sama 09:00 - 11:00
        $izin2 = $this->buatIzin($kendaraan, $pegawai2, [
            'tanggal_berangkat' => '2026-12-11',
            'waktu_berangkat' => '09:00:00',
            'tanggal_kembali' => '2026-12-11',
            'waktu_kembali' => '11:00:00',
            'status_approval' => 'Menunggu',
        ]);

        $this->loginSebagai($admin);

        // Izin 2 disetujui (tidak boleh terhalang oleh Izin 1 yang Ditolak)
        $res = $this->post(route('layanan.izin-kendaraan.status', $izin2), [
            'status_approval' => 'Disetujui',
        ]);

        $res->assertSessionHas('success', 'Pengajuan izin kendaraan disetujui.');
        $this->assertEquals('Disetujui', $izin2->fresh()->status_approval);
    }

    /**
     * Skenario 9: Approval bersamaan untuk kendaraan dan jadwal berbenturan tidak menghasilkan double approval.
     */
    public function test_skenario_9_approval_bersamaan_tidak_menghasilkan_double_approval(): void
    {
        $admin = $this->buatAdminAset();
        $pegawai1 = $this->buatPegawai();
        $pegawai2 = $this->buatPegawai();
        $kendaraan = $this->buatKendaraan();

        $izin1 = $this->buatIzin($kendaraan, $pegawai1, [
            'tanggal_berangkat' => '2026-12-12',
            'waktu_berangkat' => '08:00:00',
            'tanggal_kembali' => '2026-12-12',
            'waktu_kembali' => '12:00:00',
            'status_approval' => 'Menunggu',
        ]);

        $izin2 = $this->buatIzin($kendaraan, $pegawai2, [
            'tanggal_berangkat' => '2026-12-12',
            'waktu_berangkat' => '10:00:00',
            'tanggal_kembali' => '2026-12-12',
            'waktu_kembali' => '14:00:00',
            'status_approval' => 'Menunggu',
        ]);

        $this->loginSebagai($admin);

        // Mensimulasikan approval berurutan di bawah transaction & lockForUpdate
        $this->post(route('layanan.izin-kendaraan.status', $izin1), ['status_approval' => 'Disetujui']);
        $this->post(route('layanan.izin-kendaraan.status', $izin2), ['status_approval' => 'Disetujui']);

        $izin1Fresh = $izin1->fresh();
        $izin2Fresh = $izin2->fresh();

        // Pastikan hanya satu yang Disetujui dan satu yang Ditolak (tidak pernah keduanya Disetujui)
        $disetujuiCount = collect([$izin1Fresh->status_approval, $izin2Fresh->status_approval])
            ->filter(fn ($s) => $s === 'Disetujui')
            ->count();

        $this->assertEquals(1, $disetujuiCount);
        $this->assertEquals('Disetujui', $izin1Fresh->status_approval);
        $this->assertEquals('Ditolak', $izin2Fresh->status_approval);
        $this->assertEquals('Kendaraan digunakan', $izin2Fresh->alasan_penolakan);
    }

    /**
     * Skenario 10: Penolakan otomatis tidak mengubah status penggunaan kendaraan secara keliru.
     */
    public function test_skenario_10_penolakan_otomatis_tidak_mengubah_status_penggunaan_secara_keliru(): void
    {
        $admin = $this->buatAdminAset();
        $pegawai1 = $this->buatPegawai();
        $pegawai2 = $this->buatPegawai();

        // Buat kendaraan berstatus Tersedia
        $kendaraan = $this->buatKendaraan(true, 'Tersedia');

        // Izin 1 di masa depan (misal: 2026-12-15)
        $izin1 = $this->buatIzin($kendaraan, $pegawai1, [
            'tanggal_berangkat' => '2026-12-15',
            'waktu_berangkat' => '08:00:00',
            'tanggal_kembali' => '2026-12-15',
            'waktu_kembali' => '12:00:00',
            'status_approval' => 'Disetujui',
        ]);

        // Status kendaraan hari ini tetap Tersedia karena izin 1 adalah masa depan
        $this->assertEquals('Tersedia', $kendaraan->fresh()->status_penggunaan);

        // Izin 2 berbenturan dengan izin 1 di masa depan
        $izin2 = $this->buatIzin($kendaraan, $pegawai2, [
            'tanggal_berangkat' => '2026-12-15',
            'waktu_berangkat' => '10:00:00',
            'tanggal_kembali' => '2026-12-15',
            'waktu_kembali' => '14:00:00',
            'status_approval' => 'Menunggu',
        ]);

        $this->loginSebagai($admin);

        // Admin Aset mencoba menyetujui izin 2 -> otomatis Ditolak
        $response = $this->post(route('layanan.izin-kendaraan.status', $izin2), [
            'status_approval' => 'Disetujui',
        ]);

        $response->assertSessionHas('error', 'Permohonan izin otomatis ditolak karena kendaraan digunakan.');

        // Status izin 2 Ditolak
        $this->assertEquals('Ditolak', $izin2->fresh()->status_approval);
        $this->assertEquals('Kendaraan digunakan', $izin2->fresh()->alasan_penolakan);

        // Status izin 1 tetap Disetujui
        $this->assertEquals('Disetujui', $izin1->fresh()->status_approval);

        // Status kendaraan tetap Tersedia (tidak dirusak menjadi status lain)
        $this->assertEquals('Tersedia', $kendaraan->fresh()->status_penggunaan);
    }

    /**
     * Skenario 11: Alur penolakan manual oleh Admin Aset tetap berfungsi seperti sebelumnya.
     */
    public function test_skenario_11_alur_penolakan_manual_tetap_berfungsi_normal(): void
    {
        $admin = $this->buatAdminAset();
        $pegawai = $this->buatPegawai();
        $kendaraan = $this->buatKendaraan();

        $izin = $this->buatIzin($kendaraan, $pegawai, [
            'tanggal_berangkat' => '2026-12-16',
            'waktu_berangkat' => '08:00:00',
            'tanggal_kembali' => '2026-12-16',
            'waktu_kembali' => '12:00:00',
            'status_approval' => 'Menunggu',
        ]);

        $this->loginSebagai($admin);

        $alasanManual = 'Kendaraan sedang dijadwalkan untuk servis rutin di bengkel resmi.';

        $response = $this->post(route('layanan.izin-kendaraan.status', $izin), [
            'status_approval' => 'Ditolak',
            'alasan_penolakan' => $alasanManual,
        ]);

        $response->assertRedirect(route('layanan.izin-kendaraan.index'));
        $response->assertSessionHas('success', 'Pengajuan izin kendaraan ditolak.');

        $this->assertEquals('Ditolak', $izin->fresh()->status_approval);
        $this->assertSame($alasanManual, $izin->fresh()->alasan_penolakan);
    }
}

