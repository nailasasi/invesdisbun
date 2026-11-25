<?php

namespace Tests\Feature;

use App\Http\Controllers\IzinKendaraanController;
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

class IzinKendaraanPerbaikanTest extends TestCase
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
        Carbon::setTestNow();
        DB::rollBack();
        parent::tearDown();
    }

    private function namaDatabase(): string
    {
        return $this->namaDatabasePengujian();
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

        $ruangAset = Ruangan::firstOrCreate(
            ['nama_ruangan' => 'Ruang Aset'],
            ['id_skpd' => $skpd->id_skpd, 'id_lokasi' => 1, 'status' => 'Aktif']
        );

        $pegawai = Pegawai::create([
            'nama_pegawai' => 'Admin Aset ' . uniqid(),
            'nip' => '19850101' . rand(100000, 999999),
            'id_skpd' => $skpd->id_skpd,
            'id_ruangan' => $ruangAset->id_ruangan,
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

    private function buatPegawaiRuanganLain(): Pegawai
    {
        $ruangLain = Ruangan::firstOrCreate(
            ['nama_ruangan' => 'Ruang IT & Humas ' . uniqid()],
            ['id_skpd' => 1, 'id_lokasi' => 1, 'status' => 'Aktif']
        );

        return Pegawai::create([
            'nama_pegawai' => 'Pegawai Non Aset ' . uniqid(),
            'nip' => '19910101' . rand(100000, 999999),
            'id_skpd' => 1,
            'id_ruangan' => $ruangLain->id_ruangan,
            'jabatan' => 'Pranata Komputer',
        ]);
    }

    private function buatKendaraan(): Kendaraan
    {
        $barang = MasterBarang::firstOrCreate(
            ['nama_barang' => 'Mobil Dinas Operasional ' . uniqid()],
            ['kode_barang' => 'KND_OP_' . uniqid(), 'id_kategori' => 2]
        );

        $ruangan = Ruangan::firstOrCreate(
            ['nama_ruangan' => 'Pool Kendaraan'],
            ['id_skpd' => 1, 'id_lokasi' => 1, 'status' => 'Aktif']
        );

        $aset = Aset::create([
            'id_barang' => $barang->id_barang,
            'id_lokasi' => 1,
            'nomor_kartu_barang' => 'NKB_PERBAIKAN_' . uniqid(),
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
        ]);

        PenempatanAset::create([
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruangan->id_ruangan,
            'tanggal_penempatan' => now()->toDateString(),
            'status' => 'Aktif',
            'is_finalized' => 1,
        ]);

        return Kendaraan::create([
            'id_aset' => $aset->id_aset,
            'jenis_kendaraan' => 'Roda 4',
            'nomor_rangka' => 'MH1TEST' . rand(100000, 999999),
            'nomor_mesin' => 'MCHTEST' . rand(100000, 999999),
            'status_penggunaan' => 'Tersedia',
        ]);
    }

    // =========================================================================
    // 1. PENGUJIAN ESTIMASI DURASI (12 KASUS WAJIB)
    // =========================================================================

    public function test_12_skenario_estimasi_durasi(): void
    {
        $base = Carbon::parse('2026-11-20 08:00:00');

        $cases = [
            ['menit' => 180, 'desc' => '3 jam', 'expected_prefix' => 'Setengah Hari'],
            ['menit' => 300, 'desc' => '5 jam', 'expected_prefix' => 'Setengah Hari'],
            ['menit' => 301, 'desc' => '5 jam 1 menit', 'expected_prefix' => '1 Hari'],
            ['menit' => 480, 'desc' => '8 jam', 'expected_prefix' => '1 Hari'],
            ['menit' => 1440, 'desc' => '24 jam', 'expected_prefix' => '1 Hari'],
            ['menit' => 1500, 'desc' => '25 jam', 'expected_prefix' => '1 Hari + Setengah Hari'],
            ['menit' => 1740, 'desc' => '29 jam', 'expected_prefix' => '1 Hari + Setengah Hari'],
            ['menit' => 1741, 'desc' => '29 jam 1 menit', 'expected_prefix' => '2 Hari'],
            ['menit' => 1800, 'desc' => '30 jam', 'expected_prefix' => '2 Hari'],
            ['menit' => 2880, 'desc' => '48 jam', 'expected_prefix' => '2 Hari'],
            ['menit' => 3060, 'desc' => '51 jam', 'expected_prefix' => '2 Hari + Setengah Hari'],
            ['menit' => 4320, 'desc' => '72 jam', 'expected_prefix' => '3 Hari'],
        ];

        foreach ($cases as $c) {
            $end = $base->copy()->addMinutes($c['menit']);
            $hasil = IzinKendaraanController::hitungDurasi($base, $end);
            $this->assertStringStartsWith(
                $c['expected_prefix'] . ' (',
                $hasil,
                "Gagal pada kasus {$c['desc']} ({$c['menit']} menit): hasil didapat '{$hasil}'"
            );
        }
    }

    public function test_durasi_malam_hari_melewati_tengah_malam(): void
    {
        // 20 Nov 22:00 s.d. 21 Nov 02:00 (4 jam, lintas tanggal)
        $start = Carbon::parse('2026-11-20 22:00:00');
        $end = Carbon::parse('2026-11-21 02:00:00');
        $hasil = IzinKendaraanController::hitungDurasi($start, $end);

        $this->assertStringStartsWith('Setengah Hari (', $hasil);
        $this->assertStringContainsString('22:00, 20 Nov - 02:00, 21 Nov 2026', $hasil);
    }

    public function test_durasi_panjang_lebih_dari_72_jam(): void
    {
        // 5 hari penuh (120 jam)
        $start = Carbon::parse('2026-11-20 08:00:00');
        $end = Carbon::parse('2026-11-25 08:00:00');
        $hasil = IzinKendaraanController::hitungDurasi($start, $end);
        $this->assertStringStartsWith('5 Hari (', $hasil);

        // 5 hari + 4 jam (124 jam)
        $end2 = Carbon::parse('2026-11-25 12:00:00');
        $hasil2 = IzinKendaraanController::hitungDurasi($start, $end2);
        $this->assertStringStartsWith('5 Hari + Setengah Hari (', $hasil2);

        // 5 hari + 7 jam (127 jam)
        $end3 = Carbon::parse('2026-11-25 15:00:00');
        $hasil3 = IzinKendaraanController::hitungDurasi($start, $end3);
        $this->assertStringStartsWith('6 Hari (', $hasil3);
    }

    public function test_backend_menghitung_ulang_durasi_saat_store_tanpa_percaya_input_request(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-20 07:00:00'));
        $pemohon = $this->buatPegawai();
        $pengurus = $this->buatPegawaiRuangAset();
        $knd = $this->buatKendaraan();

        $this->loginSebagai($pemohon);

        // Kirim manipulasi durasi '10 Menit'
        $response = $this->post(route('layanan.izin-kendaraan.store'), [
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pengurus_barang' => $pengurus->id_pegawai,
            'nama_pengemudi' => 'Driver Durasi',
            'tujuan' => 'Dinas Luar Kota',
            'tanggal_berangkat' => '2026-11-20',
            'waktu_berangkat' => '08:00',
            'tanggal_kembali' => '2026-11-21',
            'waktu_kembali' => '13:00', // 29 jam -> 1 Hari + Setengah Hari
            'durasi' => 'Palsu 10 Menit',
        ]);

        $response->assertRedirect(route('layanan.izin-kendaraan.index'));
        $izin = IzinKendaraan::where('id_kendaraan', $knd->id_kendaraan)->latest('id_izin')->first();
        $this->assertNotNull($izin);
        $this->assertNotEquals('Palsu 10 Menit', $izin->durasi);
        $this->assertStringStartsWith('1 Hari + Setengah Hari (', $izin->durasi);
    }

    // =========================================================================
    // 2. PENGUJIAN TANGGAL MASA LALU & VALIDASI JADWAL
    // =========================================================================

    public function test_ketersediaan_menolak_tanggal_mulai_kemarin(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-20 10:00:00'));
        $pemohon = $this->buatPegawai();
        $this->loginSebagai($pemohon);

        $res = $this->getJson(route('layanan.izin-kendaraan.ketersediaan', [
            'tanggal_berangkat' => '2026-11-19', // Kemarin
            'waktu_berangkat' => '08:00',
            'tanggal_kembali' => '2026-11-20',
            'waktu_kembali' => '17:00',
        ]));

        $res->assertStatus(422);
        $this->assertFalse($res->json('success'));
        $this->assertStringContainsString('Tanggal mulai pemakaian tidak boleh lebih awal dari hari ini', $res->json('message'));
    }

    public function test_ketersediaan_menolak_jam_mulai_hari_ini_yang_sudah_berlalu(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-20 14:00:00'));
        $pemohon = $this->buatPegawai();
        $this->loginSebagai($pemohon);

        $res = $this->getJson(route('layanan.izin-kendaraan.ketersediaan', [
            'tanggal_berangkat' => '2026-11-20',
            'waktu_berangkat' => '08:00', // 6 jam yang lalu
            'tanggal_kembali' => '2026-11-20',
            'waktu_kembali' => '17:00',
        ]));

        $res->assertStatus(422);
        $this->assertFalse($res->json('success'));
        $this->assertStringContainsString('Jadwal mulai pemakaian tidak boleh sudah berlalu', $res->json('message'));
    }

    public function test_ketersediaan_menerima_jam_mulai_hari_ini_yang_belum_berlalu(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-20 09:00:00'));
        $pemohon = $this->buatPegawai();
        $this->buatKendaraan();
        $this->loginSebagai($pemohon);

        $res = $this->getJson(route('layanan.izin-kendaraan.ketersediaan', [
            'tanggal_berangkat' => '2026-11-20',
            'waktu_berangkat' => '10:00', // 1 jam ke depan
            'tanggal_kembali' => '2026-11-20',
            'waktu_kembali' => '15:00',
        ]));

        $res->assertOk();
        $this->assertTrue($res->json('success'));
        $this->assertNotEmpty($res->json('kendaraan'));
    }

    public function test_store_menolak_tanggal_mulai_kemarin(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-20 10:00:00'));
        $pemohon = $this->buatPegawai();
        $pengurus = $this->buatPegawaiRuangAset();
        $knd = $this->buatKendaraan();

        $this->loginSebagai($pemohon);

        $res = $this->post(route('layanan.izin-kendaraan.store'), [
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pengurus_barang' => $pengurus->id_pegawai,
            'nama_pengemudi' => 'Driver',
            'tujuan' => 'Dinas',
            'tanggal_berangkat' => '2026-11-19', // Kemarin
            'waktu_berangkat' => '08:00',
            'tanggal_kembali' => '2026-11-20',
            'waktu_kembali' => '17:00',
        ]);

        $res->assertSessionHasErrors('tanggal_berangkat');
    }

    public function test_store_menolak_jam_mulai_hari_ini_yang_sudah_berlalu(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-20 15:00:00'));
        $pemohon = $this->buatPegawai();
        $pengurus = $this->buatPegawaiRuangAset();
        $knd = $this->buatKendaraan();

        $this->loginSebagai($pemohon);

        $res = $this->post(route('layanan.izin-kendaraan.store'), [
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pengurus_barang' => $pengurus->id_pegawai,
            'nama_pengemudi' => 'Driver',
            'tujuan' => 'Dinas',
            'tanggal_berangkat' => '2026-11-20',
            'waktu_berangkat' => '10:00', // 5 jam lalu
            'tanggal_kembali' => '2026-11-20',
            'waktu_kembali' => '17:00',
        ]);

        $res->assertSessionHasErrors('waktu_berangkat');
    }

    public function test_store_menolak_tanggal_selesai_sebelum_tanggal_mulai(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-20 07:00:00'));
        $pemohon = $this->buatPegawai();
        $pengurus = $this->buatPegawaiRuangAset();
        $knd = $this->buatKendaraan();

        $this->loginSebagai($pemohon);

        $res = $this->post(route('layanan.izin-kendaraan.store'), [
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pengurus_barang' => $pengurus->id_pegawai,
            'nama_pengemudi' => 'Driver',
            'tujuan' => 'Dinas',
            'tanggal_berangkat' => '2026-11-22',
            'waktu_berangkat' => '08:00',
            'tanggal_kembali' => '2026-11-21', // Sebelum mulai
            'waktu_kembali' => '17:00',
        ]);

        $res->assertSessionHasErrors('tanggal_kembali');
    }

    public function test_store_menolak_jam_selesai_sebelum_jam_mulai_pada_tanggal_sama(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-20 07:00:00'));
        $pemohon = $this->buatPegawai();
        $pengurus = $this->buatPegawaiRuangAset();
        $knd = $this->buatKendaraan();

        $this->loginSebagai($pemohon);

        $res = $this->post(route('layanan.izin-kendaraan.store'), [
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pengurus_barang' => $pengurus->id_pegawai,
            'nama_pengemudi' => 'Driver',
            'tujuan' => 'Dinas',
            'tanggal_berangkat' => '2026-11-22',
            'waktu_berangkat' => '15:00',
            'tanggal_kembali' => '2026-11-22',
            'waktu_kembali' => '11:00', // Sebelum jam mulai
        ]);

        $res->assertSessionHasErrors('tanggal_kembali');
    }

    public function test_durasi_lebih_dari_14_hari_tetap_dapat_diajukan(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-20 07:00:00'));
        $pemohon = $this->buatPegawai();
        $pengurus = $this->buatPegawaiRuangAset();
        $knd = $this->buatKendaraan();

        $this->loginSebagai($pemohon);

        // 20 hari pemakaian
        $res = $this->post(route('layanan.izin-kendaraan.store'), [
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pengurus_barang' => $pengurus->id_pegawai,
            'nama_pengemudi' => 'Driver Ekspedisi',
            'tujuan' => 'Dinas Lapangan Panjang',
            'tanggal_berangkat' => '2026-11-21',
            'waktu_berangkat' => '08:00',
            'tanggal_kembali' => '2026-12-11',
            'waktu_kembali' => '08:00',
        ]);

        $res->assertRedirect(route('layanan.izin-kendaraan.index'));
        $res->assertSessionHas('success');

        $izin = IzinKendaraan::where('id_kendaraan', $knd->id_kendaraan)->latest('id_izin')->first();
        $this->assertNotNull($izin);
        $this->assertStringStartsWith('20 Hari (', $izin->durasi);
    }

    // =========================================================================
    // 3. PENGUJIAN PENGURUS BARANG
    // =========================================================================

    public function test_store_menerima_pengurus_barang_dari_ruang_aset(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-20 07:00:00'));
        $pemohon = $this->buatPegawai();
        $pengurus = $this->buatPegawaiRuangAset();
        $knd = $this->buatKendaraan();

        $this->loginSebagai($pemohon);

        $res = $this->post(route('layanan.izin-kendaraan.store'), [
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pengurus_barang' => $pengurus->id_pegawai,
            'nama_pengemudi' => 'Driver',
            'tujuan' => 'Dinas',
            'tanggal_berangkat' => '2026-11-20',
            'waktu_berangkat' => '08:00',
            'tanggal_kembali' => '2026-11-20',
            'waktu_kembali' => '12:00',
        ]);

        $res->assertRedirect(route('layanan.izin-kendaraan.index'));
        $res->assertSessionHas('success');
    }

    public function test_store_menolak_pengurus_barang_dari_ruangan_lain(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-20 07:00:00'));
        $pemohon = $this->buatPegawai();
        $pengurusLain = $this->buatPegawaiRuanganLain();
        $knd = $this->buatKendaraan();

        $this->loginSebagai($pemohon);

        $res = $this->post(route('layanan.izin-kendaraan.store'), [
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pengurus_barang' => $pengurusLain->id_pegawai, // Bukan Ruang Aset
            'nama_pengemudi' => 'Driver',
            'tujuan' => 'Dinas',
            'tanggal_berangkat' => '2026-11-20',
            'waktu_berangkat' => '08:00',
            'tanggal_kembali' => '2026-11-20',
            'waktu_kembali' => '12:00',
        ]);

        $res->assertSessionHasErrors('id_pengurus_barang');
    }

    public function test_store_menolak_pengurus_barang_id_tidak_valid(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-20 07:00:00'));
        $pemohon = $this->buatPegawai();
        $knd = $this->buatKendaraan();

        $this->loginSebagai($pemohon);

        $res = $this->post(route('layanan.izin-kendaraan.store'), [
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pengurus_barang' => 99999999, // Tidak ada di database
            'nama_pengemudi' => 'Driver',
            'tujuan' => 'Dinas',
            'tanggal_berangkat' => '2026-11-20',
            'waktu_berangkat' => '08:00',
            'tanggal_kembali' => '2026-11-20',
            'waktu_kembali' => '12:00',
        ]);

        $res->assertSessionHasErrors('id_pengurus_barang');
    }

    // =========================================================================
    // 4. PENGUJIAN PENGUNCIAN STATUS APPROVAL
    // =========================================================================

    public function test_approval_dapat_memproses_pengajuan_berstatus_menunggu(): void
    {
        $admin = $this->buatAdminAset();
        $pemohon = $this->buatPegawai();
        $pengurus = $this->buatPegawaiRuangAset();
        $knd = $this->buatKendaraan();

        $izin = IzinKendaraan::create([
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pegawai_pengaju' => $pemohon->pegawai->id_pegawai,
            'id_pengurus_barang' => $pengurus->id_pegawai,
            'nama_pengemudi' => 'Driver',
            'tujuan' => 'Dinas',
            'tanggal_berangkat' => '2026-12-01',
            'waktu_berangkat' => '08:00',
            'tanggal_kembali' => '2026-12-01',
            'waktu_kembali' => '12:00',
            'status_approval' => 'Menunggu',
        ]);

        $this->loginSebagai($admin);

        $res = $this->post(route('layanan.izin-kendaraan.status', $izin->id_izin), [
            'status_approval' => 'Disetujui',
        ]);

        $res->assertRedirect(route('layanan.izin-kendaraan.index'));
        $this->assertEquals('Disetujui', $izin->fresh()->status_approval);
    }

    public function test_approval_menolak_pengajuan_yang_sudah_disetujui(): void
    {
        $admin = $this->buatAdminAset();
        $pemohon = $this->buatPegawai();
        $pengurus = $this->buatPegawaiRuangAset();
        $knd = $this->buatKendaraan();

        $izin = IzinKendaraan::create([
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pegawai_pengaju' => $pemohon->pegawai->id_pegawai,
            'id_pengurus_barang' => $pengurus->id_pegawai,
            'nama_pengemudi' => 'Driver',
            'tujuan' => 'Dinas',
            'tanggal_berangkat' => '2026-12-01',
            'waktu_berangkat' => '08:00',
            'tanggal_kembali' => '2026-12-01',
            'waktu_kembali' => '12:00',
            'status_approval' => 'Disetujui', // Sudah Disetujui
        ]);

        $this->loginSebagai($admin);

        $res = $this->post(route('layanan.izin-kendaraan.status', $izin->id_izin), [
            'status_approval' => 'Ditolak',
            'alasan_penolakan' => 'Coba ubah status',
        ]);

        $res->assertSessionHas('error');
        $this->assertEquals('Disetujui', $izin->fresh()->status_approval);
    }

    public function test_approval_menolak_pengajuan_yang_sudah_ditolak(): void
    {
        $admin = $this->buatAdminAset();
        $pemohon = $this->buatPegawai();
        $pengurus = $this->buatPegawaiRuangAset();
        $knd = $this->buatKendaraan();

        $izin = IzinKendaraan::create([
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pegawai_pengaju' => $pemohon->pegawai->id_pegawai,
            'id_pengurus_barang' => $pengurus->id_pegawai,
            'nama_pengemudi' => 'Driver',
            'tujuan' => 'Dinas',
            'tanggal_berangkat' => '2026-12-01',
            'waktu_berangkat' => '08:00',
            'tanggal_kembali' => '2026-12-01',
            'waktu_kembali' => '12:00',
            'status_approval' => 'Ditolak',
            'alasan_penolakan' => 'Awal ditolak',
        ]);

        $this->loginSebagai($admin);

        $res = $this->post(route('layanan.izin-kendaraan.status', $izin->id_izin), [
            'status_approval' => 'Disetujui',
        ]);

        $res->assertSessionHas('error');
        $this->assertEquals('Ditolak', $izin->fresh()->status_approval);
    }

    public function test_approval_menolak_pengajuan_yang_sudah_dibatalkan(): void
    {
        $admin = $this->buatAdminAset();
        $pemohon = $this->buatPegawai();
        $pengurus = $this->buatPegawaiRuangAset();
        $knd = $this->buatKendaraan();

        $izin = IzinKendaraan::create([
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pegawai_pengaju' => $pemohon->pegawai->id_pegawai,
            'id_pengurus_barang' => $pengurus->id_pegawai,
            'nama_pengemudi' => 'Driver',
            'tujuan' => 'Dinas',
            'tanggal_berangkat' => '2026-12-01',
            'waktu_berangkat' => '08:00',
            'tanggal_kembali' => '2026-12-01',
            'waktu_kembali' => '12:00',
            'status_approval' => 'Dibatalkan',
        ]);

        $this->loginSebagai($admin);

        $res = $this->post(route('layanan.izin-kendaraan.status', $izin->id_izin), [
            'status_approval' => 'Disetujui',
        ]);

        $res->assertSessionHas('error');
        $this->assertEquals('Dibatalkan', $izin->fresh()->status_approval);
    }

    public function test_approval_menolak_pengajuan_yang_sudah_selesai(): void
    {
        $admin = $this->buatAdminAset();
        $pemohon = $this->buatPegawai();
        $pengurus = $this->buatPegawaiRuangAset();
        $knd = $this->buatKendaraan();

        $izin = IzinKendaraan::create([
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pegawai_pengaju' => $pemohon->pegawai->id_pegawai,
            'id_pengurus_barang' => $pengurus->id_pegawai,
            'nama_pengemudi' => 'Driver',
            'tujuan' => 'Dinas',
            'tanggal_berangkat' => '2026-12-01',
            'waktu_berangkat' => '08:00',
            'tanggal_kembali' => '2026-12-01',
            'waktu_kembali' => '12:00',
            'status_approval' => 'Selesai',
        ]);

        $this->loginSebagai($admin);

        $res = $this->post(route('layanan.izin-kendaraan.status', $izin->id_izin), [
            'status_approval' => 'Disetujui',
        ]);

        $res->assertSessionHas('error');
        $this->assertEquals('Selesai', $izin->fresh()->status_approval);
    }

    public function test_role_non_admin_aset_ditolak_403_saat_update_status(): void
    {
        $pemohon = $this->buatPegawai();
        $pengurus = $this->buatPegawaiRuangAset();
        $knd = $this->buatKendaraan();

        $izin = IzinKendaraan::create([
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pegawai_pengaju' => $pemohon->pegawai->id_pegawai,
            'id_pengurus_barang' => $pengurus->id_pegawai,
            'nama_pengemudi' => 'Driver',
            'tujuan' => 'Dinas',
            'tanggal_berangkat' => '2026-12-01',
            'waktu_berangkat' => '08:00',
            'tanggal_kembali' => '2026-12-01',
            'waktu_kembali' => '12:00',
            'status_approval' => 'Menunggu',
        ]);

        $this->loginSebagai($pemohon);

        $res = $this->post(route('layanan.izin-kendaraan.status', $izin->id_izin), [
            'status_approval' => 'Disetujui',
        ]);

        $res->assertForbidden();
    }

    // =========================================================================
    // 5. PENGUJIAN PROFIL PEGAWAI PEMOHON
    // =========================================================================

    public function test_user_tanpa_relasi_pegawai_ditolak_mengajukan_izin(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-20 07:00:00'));
        $role = Role::firstOrCreate(
            ['nama_role' => 'Pegawai'],
            ['deskripsi' => 'Pegawai']
        );

        // Buat user dengan id_pegawai yang tidak memiliki relasi pegawai di tabel pegawai
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        $userTanpaPegawai = User::create([
            'username' => 'user_yatim_' . uniqid(),
            'password' => bcrypt('password123'),
            'id_role' => $role->id_role,
            'id_pegawai' => 99999999, // Pegawai fiktif sehingga $user->pegawai bernilai null
            'status_user' => 'aktif',
        ]);
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $pengurus = $this->buatPegawaiRuangAset();
        $knd = $this->buatKendaraan();

        $this->loginSebagai($userTanpaPegawai);

        $res = $this->post(route('layanan.izin-kendaraan.store'), [
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pengurus_barang' => $pengurus->id_pegawai,
            'nama_pengemudi' => 'Driver',
            'tujuan' => 'Dinas',
            'tanggal_berangkat' => '2026-11-20',
            'waktu_berangkat' => '08:00',
            'tanggal_kembali' => '2026-11-20',
            'waktu_kembali' => '12:00',
        ]);

        $res->assertSessionHasErrors('id_pegawai_pengaju');

        // Pastikan tidak ada record izin tersimpan
        $count = IzinKendaraan::where('id_kendaraan', $knd->id_kendaraan)->count();
        $this->assertEquals(0, $count);
    }
}
