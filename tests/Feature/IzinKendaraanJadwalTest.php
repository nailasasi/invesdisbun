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

class IzinKendaraanJadwalTest extends TestCase
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

    /**
     * Skenario Form UI: Urutan field form benar dan dropdown kendaraan awal dinonaktifkan.
     */
    public function test_tampilan_form_memiliki_urutan_field_benar_dan_dropdown_kendaraan_awal_kosong(): void
    {
        $pemohon = $this->buatPegawai();
        $this->loginSebagai($pemohon);

        $response = $this->get(route('layanan.izin-kendaraan.index'));
        $response->assertOk();

        $content = $response->getContent();

        // Verifikasi urutan elemen input di HTML form
        $posTglMulai = strpos($content, 'name="tanggal_berangkat"');
        $posWktMulai = strpos($content, 'name="waktu_berangkat"');
        $posTglSelesai = strpos($content, 'name="tanggal_kembali"');
        $posWktSelesai = strpos($content, 'name="waktu_kembali"');
        $posKendaraan = strpos($content, 'name="id_kendaraan"');
        $posPengurus = strpos($content, 'name="id_pengurus_barang"');
        $posPengemudi = strpos($content, 'name="nama_pengemudi"');
        $posDurasi = strpos($content, 'name="durasi"');
        $posTujuan = strpos($content, 'name="tujuan"');

        $this->assertTrue($posTglMulai < $posWktMulai);
        $this->assertTrue($posWktMulai < $posTglSelesai);
        $this->assertTrue($posTglSelesai < $posWktSelesai);
        $this->assertTrue($posWktSelesai < $posKendaraan);
        $this->assertTrue($posKendaraan < $posPengurus);
        $this->assertTrue($posPengurus < $posPengemudi);
        $this->assertTrue($posPengemudi < $posDurasi);
        $this->assertTrue($posDurasi < $posTujuan);

        // Verifikasi dropdown awal dinonaktifkan dan menampilkan instruksi
        $response->assertSee('Isi tanggal &amp; jam pemakaian terlebih dahulu', false);
        $response->assertSee('Pilih tanggal dan jam pemakaian untuk memuat kendaraan yang tersedia.');
        $response->assertSee('disabled', false);
    }

    /**
     * Skenario Durasi: Kalkulasi durasi otomatis bekerja akurat untuk setengah hari, 1 hari, dan multi-hari.
     */
    public function test_kalkulasi_durasi_otomatis(): void
    {
        // Setengah hari
        $start1 = Carbon::parse('2026-12-25 08:00:00');
        $end1 = Carbon::parse('2026-12-25 12:00:00');
        $this->assertEquals('Setengah Hari (08:00 - 12:00)', IzinKendaraanController::hitungDurasi($start1, $end1));

        // 1 hari penuh
        $start2 = Carbon::parse('2026-12-25 08:00:00');
        $end2 = Carbon::parse('2026-12-25 17:00:00');
        $this->assertEquals('1 Hari (08:00 - 17:00)', IzinKendaraanController::hitungDurasi($start2, $end2));

        // Multi hari
        $start3 = Carbon::parse('2026-12-25 08:00:00');
        $end3 = Carbon::parse('2026-12-27 15:00:00');
        $rentang3 = $start3->translatedFormat('H:i, d M') . ' - ' . $end3->translatedFormat('H:i, d M Y');
        $this->assertEquals('3 Hari (' . $rentang3 . ')', IzinKendaraanController::hitungDurasi($start3, $end3));
    }

    /**
     * Skenario 1 & 2: Kendaraan belum difinalisasi tidak muncul, yang sudah difinalisasi dan tersedia muncul.
     */
    public function test_kendaraan_belum_difinalisasi_tidak_muncul_yang_terfinalisasi_muncul(): void
    {
        $pemohon = $this->buatPegawai();
        $this->loginSebagai($pemohon);

        $kndBelumFinal = $this->buatKendaraan(isFinalized: false);
        $kndSudahFinal = $this->buatKendaraan(isFinalized: true);

        $response = $this->getJson(route('layanan.izin-kendaraan.ketersediaan', [
            'tanggal_berangkat' => '2026-12-01',
            'waktu_berangkat' => '08:00',
            'tanggal_kembali' => '2026-12-01',
            'waktu_kembali' => '17:00',
        ]));

        $response->assertOk();
        $ids = collect($response->json('kendaraan'))->pluck('id')->all();

        $this->assertNotContains($kndBelumFinal->id_kendaraan, $ids);
        $this->assertContains($kndSudahFinal->id_kendaraan, $ids);
    }

    /**
     * Skenario 3: Kendaraan dengan pengajuan Menunggu pada jadwal yang bertabrakan tidak muncul.
     */
    public function test_kendaraan_dengan_pengajuan_menunggu_yang_bertabrakan_tidak_muncul(): void
    {
        $pemohon = $this->buatPegawai();
        $knd = $this->buatKendaraan(isFinalized: true);

        // Buat pengajuan 'Menunggu' dari 10:00 - 14:00
        IzinKendaraan::create([
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pegawai_pengaju' => $pemohon->pegawai->id_pegawai,
            'tanggal_berangkat' => '2026-12-05',
            'waktu_berangkat' => '10:00:00',
            'tanggal_kembali' => '2026-12-05',
            'waktu_kembali' => '14:00:00',
            'status_approval' => 'Menunggu',
        ]);

        $this->loginSebagai($pemohon);

        // Request jadwal yang overlap (misal 12:00 - 16:00)
        $resOverlap = $this->getJson(route('layanan.izin-kendaraan.ketersediaan', [
            'tanggal_berangkat' => '2026-12-05',
            'waktu_berangkat' => '12:00',
            'tanggal_kembali' => '2026-12-05',
            'waktu_kembali' => '16:00',
        ]));

        $resOverlap->assertOk();
        $ids = collect($resOverlap->json('kendaraan'))->pluck('id')->all();
        $this->assertNotContains($knd->id_kendaraan, $ids);
    }

    /**
     * Skenario 4: Kendaraan dengan pengajuan Disetujui pada jadwal yang bertabrakan tidak muncul.
     */
    public function test_kendaraan_dengan_pengajuan_disetujui_yang_bertabrakan_tidak_muncul(): void
    {
        $pemohon = $this->buatPegawai();
        $knd = $this->buatKendaraan(isFinalized: true);

        // Buat pengajuan 'Disetujui' multi-hari (10 Des - 12 Des)
        IzinKendaraan::create([
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pegawai_pengaju' => $pemohon->pegawai->id_pegawai,
            'tanggal_berangkat' => '2026-12-10',
            'waktu_berangkat' => '08:00:00',
            'tanggal_kembali' => '2026-12-12',
            'waktu_kembali' => '17:00:00',
            'status_approval' => 'Disetujui',
        ]);

        $this->loginSebagai($pemohon);

        // Request tanggal 11 Des (di tengah rentang multi-hari)
        $resOverlap = $this->getJson(route('layanan.izin-kendaraan.ketersediaan', [
            'tanggal_berangkat' => '2026-12-11',
            'waktu_berangkat' => '09:00',
            'tanggal_kembali' => '2026-12-11',
            'waktu_kembali' => '15:00',
        ]));

        $resOverlap->assertOk();
        $ids = collect($resOverlap->json('kendaraan'))->pluck('id')->all();
        $this->assertNotContains($knd->id_kendaraan, $ids);
    }

    /**
     * Skenario 5: Kendaraan dengan jadwal berbeda tetap dapat dipilih.
     */
    public function test_kendaraan_dengan_jadwal_berbeda_tetap_dapat_dipilih(): void
    {
        $pemohon = $this->buatPegawai();
        $knd = $this->buatKendaraan(isFinalized: true);

        // Pengajuan pagi 08:00 - 11:00 (Disetujui)
        IzinKendaraan::create([
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pegawai_pengaju' => $pemohon->pegawai->id_pegawai,
            'tanggal_berangkat' => '2026-12-15',
            'waktu_berangkat' => '08:00:00',
            'tanggal_kembali' => '2026-12-15',
            'waktu_kembali' => '11:00:00',
            'status_approval' => 'Disetujui',
        ]);

        $this->loginSebagai($pemohon);

        // Request siang 13:00 - 17:00 pada hari yang sama (tidak bentrok)
        $resBedaJam = $this->getJson(route('layanan.izin-kendaraan.ketersediaan', [
            'tanggal_berangkat' => '2026-12-15',
            'waktu_berangkat' => '13:00',
            'tanggal_kembali' => '2026-12-15',
            'waktu_kembali' => '17:00',
        ]));

        $resBedaJam->assertOk();
        $ids = collect($resBedaJam->json('kendaraan'))->pluck('id')->all();
        $this->assertContains($knd->id_kendaraan, $ids);
    }

    /**
     * Skenario 6: Pengajuan Ditolak, Dibatalkan, dan Selesai tidak menghalangi pemesanan.
     */
    public function test_pengajuan_ditolak_dibatalkan_selesai_tidak_menghalangi_pemesanan(): void
    {
        $pemohon = $this->buatPegawai();
        $knd = $this->buatKendaraan(isFinalized: true);

        // Pengajuan Ditolak pada rentang waktu yang sama
        IzinKendaraan::create([
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pegawai_pengaju' => $pemohon->pegawai->id_pegawai,
            'tanggal_berangkat' => '2026-12-20',
            'waktu_berangkat' => '08:00:00',
            'tanggal_kembali' => '2026-12-20',
            'waktu_kembali' => '17:00:00',
            'status_approval' => 'Ditolak',
        ]);

        // Pengajuan Dibatalkan pada rentang waktu yang sama
        IzinKendaraan::create([
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pegawai_pengaju' => $pemohon->pegawai->id_pegawai,
            'tanggal_berangkat' => '2026-12-20',
            'waktu_berangkat' => '08:00:00',
            'tanggal_kembali' => '2026-12-20',
            'waktu_kembali' => '17:00:00',
            'status_approval' => 'Dibatalkan',
        ]);

        // Pengajuan Selesai pada rentang waktu yang sama
        IzinKendaraan::create([
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pegawai_pengaju' => $pemohon->pegawai->id_pegawai,
            'tanggal_berangkat' => '2026-12-20',
            'waktu_berangkat' => '08:00:00',
            'tanggal_kembali' => '2026-12-20',
            'waktu_kembali' => '17:00:00',
            'status_approval' => 'Selesai',
        ]);

        $this->loginSebagai($pemohon);

        $res = $this->getJson(route('layanan.izin-kendaraan.ketersediaan', [
            'tanggal_berangkat' => '2026-12-20',
            'waktu_berangkat' => '08:00',
            'tanggal_kembali' => '2026-12-20',
            'waktu_kembali' => '17:00',
        ]));

        $res->assertOk();
        $ids = collect($res->json('kendaraan'))->pluck('id')->all();
        $this->assertContains($knd->id_kendaraan, $ids);
    }

    /**
     * Skenario 7: Kendaraan yang sedang digunakan secara aktual tidak dapat dipilih untuk waktu saat ini.
     */
    public function test_kendaraan_yang_sedang_digunakan_aktual_tidak_dapat_dipilih_untuk_waktu_sekarang(): void
    {
        $pemohon = $this->buatPegawai();
        $knd = $this->buatKendaraan(isFinalized: true, statusPenggunaan: 'Digunakan');

        $this->loginSebagai($pemohon);

        // Pilih jadwal yang mencakup saat ini (now)
        $res = $this->getJson(route('layanan.izin-kendaraan.ketersediaan', [
            'tanggal_berangkat' => now()->format('Y-m-d'),
            'waktu_berangkat' => now()->format('H:i'),
            'tanggal_kembali' => now()->addHours(2)->format('Y-m-d'),
            'waktu_kembali' => now()->addHours(2)->format('H:i'),
        ]));

        $res->assertOk();
        $ids = collect($res->json('kendaraan'))->pluck('id')->all();
        $this->assertNotContains($knd->id_kendaraan, $ids);
    }

    /**
     * Skenario 8: Kendaraan yang sudah dikembalikan dapat dipilih kembali jika jadwal tersedia.
     */
    public function test_kendaraan_yang_sudah_dikembalikan_dapat_dipilih_kembali(): void
    {
        $pemohon = $this->buatPegawai();
        $knd = $this->buatKendaraan(isFinalized: true, statusPenggunaan: 'Tersedia');

        $this->loginSebagai($pemohon);

        $res = $this->getJson(route('layanan.izin-kendaraan.ketersediaan', [
            'tanggal_berangkat' => now()->addDays(5)->format('Y-m-d'),
            'waktu_berangkat' => '08:00',
            'tanggal_kembali' => now()->addDays(5)->format('Y-m-d'),
            'waktu_kembali' => '17:00',
        ]));

        $res->assertOk();
        $ids = collect($res->json('kendaraan'))->pluck('id')->all();
        $this->assertContains($knd->id_kendaraan, $ids);
    }

    /**
     * Skenario 9: Backend menolak pengajuan dengan jadwal bertabrakan.
     */
    public function test_backend_menolak_pengajuan_dengan_jadwal_bertabrakan(): void
    {
        $pemohonA = $this->buatPegawai();
        $pemohonB = $this->buatPegawai();
        $pengurus = $this->buatPegawaiRuangAset();
        $knd = $this->buatKendaraan(isFinalized: true);

        // Pemohon A mengajukan 10:00 - 15:00
        $this->loginSebagai($pemohonA);
        $resA = $this->post(route('layanan.izin-kendaraan.store'), [
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pengurus_barang' => $pengurus->id_pegawai,
            'nama_pengemudi' => 'Driver A',
            'tujuan' => 'Dinas Surabaya',
            'tanggal_berangkat' => '2026-12-25',
            'waktu_berangkat' => '10:00',
            'tanggal_kembali' => '2026-12-25',
            'waktu_kembali' => '15:00',
        ]);
        $resA->assertRedirect(route('layanan.izin-kendaraan.index'));
        $resA->assertSessionHas('success');

        // Pemohon B mencoba mengajukan kendaraan yang sama pada 14:00 - 18:00 (overlap)
        $this->loginSebagai($pemohonB);
        $resB = $this->post(route('layanan.izin-kendaraan.store'), [
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pengurus_barang' => $pengurus->id_pegawai,
            'nama_pengemudi' => 'Driver B',
            'tujuan' => 'Dinas Sidoarjo',
            'tanggal_berangkat' => '2026-12-25',
            'waktu_berangkat' => '14:00',
            'tanggal_kembali' => '2026-12-25',
            'waktu_kembali' => '18:00',
        ]);

        $resB->assertSessionHasErrors(['id_kendaraan']);
    }

    /**
     * Skenario 10: Backend menolak tanggal atau jam yang tidak valid (selesai <= mulai).
     */
    public function test_backend_menolak_tanggal_atau_jam_yang_tidak_valid(): void
    {
        $pemohon = $this->buatPegawai();
        $pengurus = $this->buatPegawaiRuangAset();
        $knd = $this->buatKendaraan(isFinalized: true);

        $this->loginSebagai($pemohon);

        // Jam selesai (08:00) lebih awal daripada jam mulai (10:00) pada hari yang sama
        $response = $this->post(route('layanan.izin-kendaraan.store'), [
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pengurus_barang' => $pengurus->id_pegawai,
            'nama_pengemudi' => 'Driver Test',
            'tujuan' => 'Dinas Malang',
            'tanggal_berangkat' => '2026-12-28',
            'waktu_berangkat' => '10:00',
            'tanggal_kembali' => '2026-12-28',
            'waktu_kembali' => '08:00',
        ]);

        $response->assertSessionHasErrors(['tanggal_kembali']);
    }

    /**
     * Skenario 11: Dropdown Pengurus Barang hanya menampilkan pegawai Ruang Aset.
     */
    public function test_dropdown_pengurus_barang_hanya_menampilkan_pegawai_ruang_aset(): void
    {
        $pemohon = $this->buatPegawai();
        $pengurusRuangAset = $this->buatPegawaiRuangAset();

        // Buat pegawai di ruangan lain
        $ruangLain = Ruangan::create([
            'nama_ruangan' => 'Ruang Non Aset ' . uniqid(),
            'id_skpd' => 1,
            'status' => 'Aktif',
        ]);
        $pegawaiLain = Pegawai::create([
            'nama_pegawai' => 'Bukan Pegawai Aset ' . uniqid(),
            'nip' => '19950101' . rand(100000, 999999),
            'id_skpd' => 1,
            'id_ruangan' => $ruangLain->id_ruangan,
            'jabatan' => 'Staf Bidang',
        ]);

        $this->loginSebagai($pemohon);

        $response = $this->get(route('layanan.izin-kendaraan.index'));
        $response->assertOk();

        // Pegawai Ruang Aset harus muncul
        $response->assertSee($pengurusRuangAset->nama_pegawai);

        // Pegawai ruangan lain tidak boleh muncul sebagai opsi pengurus barang
        $response->assertDontSee($pegawaiLain->nama_pegawai);
    }

    /**
     * Skenario 12: Aksi ACC, Tolak, Batalkan Ajuan, Unduh Surat, dan Kembalikan Kendaraan tetap berfungsi.
     */
    public function test_seluruh_aksi_lama_tetap_berfungsi_normal(): void
    {
        $adminAset = $this->buatAdminAset();
        $pemohon = $this->buatPegawai();
        $pengurus = $this->buatPegawaiRuangAset();
        $knd = $this->buatKendaraan(isFinalized: true);

        // 12.1 Ajukan izin dengan jadwal lengkap
        Carbon::setTestNow(Carbon::parse(now()->toDateString() . ' 08:00:00'));
        $this->loginSebagai($pemohon);
        $resStore = $this->post(route('layanan.izin-kendaraan.store'), [
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pengurus_barang' => $pengurus->id_pegawai,
            'nama_pengemudi' => 'Driver Alur Lengkap',
            'tujuan' => 'Dinas Surabaya - Malang',
            'tanggal_berangkat' => now()->toDateString(),
            'waktu_berangkat' => '08:00',
            'tanggal_kembali' => now()->toDateString(),
            'waktu_kembali' => '23:59',
        ]);
        $resStore->assertRedirect(route('layanan.izin-kendaraan.index'));

        $izin = IzinKendaraan::where('id_kendaraan', $knd->id_kendaraan)->latest('id_izin')->first();
        $this->assertNotNull($izin);
        $this->assertEquals('Menunggu', $izin->status_approval);
        $this->assertEquals('1 Hari (08:00 - 23:59)', $izin->durasi);

        // 12.2 ACC oleh Admin Aset
        $this->loginSebagai($adminAset);
        $resAcc = $this->post(route('layanan.izin-kendaraan.status', $izin->id_izin), [
            'status_approval' => 'Disetujui',
        ]);
        $resAcc->assertRedirect(route('layanan.izin-kendaraan.index'));
        $this->assertEquals('Disetujui', $izin->fresh()->status_approval);
        $this->assertEquals('Digunakan', $knd->fresh()->status_penggunaan);

        // 12.3 Unduh Surat
        $this->loginSebagai($pemohon);
        $resUnduh = $this->get(route('layanan.izin-kendaraan.download', $izin->id_izin));
        $resUnduh->assertOk();

        // 12.4 Kembalikan Kendaraan
        $resKembalikan = $this->post(route('layanan.izin-kendaraan.selesai', $izin->id_izin), [
            'catatan_kondisi' => 'Kondisi kendaraan bersih.',
            'waktu_pengembalian' => now()->format('Y-m-d H:i:s'),
        ]);
        $resKembalikan->assertRedirect(route('layanan.izin-kendaraan.index'));
        $this->assertEquals('Selesai', $izin->fresh()->status_approval);
        $this->assertEquals('Tersedia', $knd->fresh()->status_penggunaan);

        // 12.5 Test Pembatalan oleh Pemohon pada Pengajuan Baru
        $resStore2 = $this->post(route('layanan.izin-kendaraan.store'), [
            'id_kendaraan' => $knd->id_kendaraan,
            'id_pengurus_barang' => $pengurus->id_pegawai,
            'nama_pengemudi' => 'Driver Alur Batal',
            'tujuan' => 'Dinas Gresik',
            'tanggal_berangkat' => '2026-12-31',
            'waktu_berangkat' => '09:00',
            'tanggal_kembali' => '2026-12-31',
            'waktu_kembali' => '12:00',
        ]);
        $resStore2->assertRedirect(route('layanan.izin-kendaraan.index'));

        $izin2 = IzinKendaraan::where('id_kendaraan', $knd->id_kendaraan)->latest('id_izin')->first();
        $resBatal = $this->post(route('layanan.izin-kendaraan.batal', $izin2->id_izin));
        $resBatal->assertRedirect(route('layanan.izin-kendaraan.index'));
        $this->assertEquals('Dibatalkan', $izin2->fresh()->status_approval);
    }
}

