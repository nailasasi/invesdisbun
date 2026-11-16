<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Kendaraan;
use App\Models\PajakKendaraan;
use App\Models\Pegawai;
use App\Models\RiwayatPlat;
use App\Models\Role;
use App\Models\Skpd;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KelolaPajakKendaraanTest extends TestCase
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
        foreach (file(base_path('.env')) as $line) {
            if (str_starts_with(trim($line), 'DB_DATABASE=')) {
                $nilai = trim(explode('=', $line, 2)[1]);

                return $nilai === '' ? 'invesdisbun' : $nilai;
            }
        }

        return 'invesdisbun';
    }

    private function loginSebagai(User $user)
    {
        return $this->actingAs($user);
    }

    private function buatUserRole(string $namaRole): User
    {
        $role = Role::firstOrCreate(
            ['nama_role' => $namaRole],
            ['deskripsi' => $namaRole]
        );

        $skpd = Skpd::first() ?? Skpd::create(
            ['nama_skpd' => 'Dinas Perkebunan', 'jenis_skpd' => 'Bidang']
        );

        $pegawai = Pegawai::create([
            'nama_pegawai' => $namaRole . ' ' . uniqid(),
            'nip' => '19900101' . rand(100000, 999999),
            'id_skpd' => $skpd->id_skpd,
            'jabatan' => 'Staf Pengujian',
        ]);

        return User::create([
            'username' => strtolower(str_replace(' ', '_', $namaRole)) . '_' . uniqid(),
            'password' => bcrypt('password123'),
            'id_role' => $role->id_role,
            'id_pegawai' => $pegawai->id_pegawai,
            'status_user' => 'aktif',
        ]);
    }

    private function buatKendaraan(string $plat = 'L 1234 AA', ?string $tglBerlakuPlat = null): Kendaraan
    {
        $skpd = Skpd::first() ?? Skpd::create(
            ['nama_skpd' => 'Dinas Perkebunan', 'jenis_skpd' => 'Bidang']
        );

        $aset = Aset::create([
            'id_skpd' => $skpd->id_skpd,
            'id_lokasi' => 1,
            'nomor_kartu_barang' => 'NKB-' . uniqid(),
            'kode_barang' => '02.03.01.01.01',
            'nama_barang' => 'Mobil Dinas Test ' . uniqid(),
            'nup' => rand(1000, 9999),
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => true,
        ]);

        $kendaraan = Kendaraan::create([
            'id_aset' => $aset->id_aset,
            'jenis_kendaraan' => 'Roda 4',
            'merk' => 'Toyota',
            'tipe' => 'Innova',
            'nomor_rangka' => 'MHF' . uniqid(),
            'nomor_mesin' => '1TR' . uniqid(),
        ]);

        RiwayatPlat::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'nomor_plat' => $plat,
            'tanggal_berlaku' => $tglBerlakuPlat,
            'status' => 'Aktif',
            'ganti_plat' => false,
        ]);

        return $kendaraan;
    }

    // ==========================================
    // A. PENGUJIAN HAK AKSES
    // ==========================================

    public function test_admin_aset_dapat_mengakses_halaman_detail_kendaraan(): void
    {
        $admin = $this->buatUserRole('Admin Aset');
        $kendaraan = $this->buatKendaraan();

        $response = $this->loginSebagai($admin)->get(route('kendaraan.show', $kendaraan));
        $response->assertOk();
        $response->assertSee('Detail Kendaraan');
        $response->assertSee('Kelola Pajak Kendaraan');
    }

    public function test_admin_bidang_admin_upt_dan_pegawai_ditolak_mengakses_detail_kendaraan(): void
    {
        $kendaraan = $this->buatKendaraan();

        foreach (['Admin Bidang', 'Admin UPT', 'Pegawai'] as $roleName) {
            $user = $this->buatUserRole($roleName);
            $response = $this->loginSebagai($user)->get(route('kendaraan.show', $kendaraan));
            $response->assertForbidden();
        }
    }

    public function test_halaman_index_menampilkan_nama_kendaraan_sebagai_link_hanya_untuk_admin_aset(): void
    {
        $kendaraanDinas = $this->buatKendaraan('L 5555 ZZ');
        $detailUrlDinas = route('kendaraan.show', $kendaraanDinas);

        // 1. Admin Aset melihat tautan ke detail kendaraan
        $adminAset = $this->buatUserRole('Admin Aset');
        $resAset = $this->loginSebagai($adminAset)->get(route('kendaraan.index'));
        $resAset->assertOk();
        $resAset->assertSee($detailUrlDinas, false);

        // 2. Admin Bidang melihat nama kendaraan sebagai teks biasa (TIDAK ADA LINK DETAIL)
        $adminBidang = $this->buatUserRole('Admin Bidang');
        $resBidang = $this->loginSebagai($adminBidang)->get(route('kendaraan.index'));
        $resBidang->assertOk();
        $resBidang->assertDontSee($detailUrlDinas, false);

        // 3. Pegawai tidak melihat tautan ke detail kendaraan
        $pegawai = $this->buatUserRole('Pegawai');
        $resPegawai = $this->loginSebagai($pegawai)->get(route('kendaraan.index'));
        $resPegawai->assertOk();
        $resPegawai->assertDontSee($detailUrlDinas, false);

        // 4. Admin UPT tidak melihat tautan ke detail kendaraan
        $adminUpt = $this->buatUserRole('Admin UPT');
        $resUpt = $this->loginSebagai($adminUpt)->get(route('kendaraan.index'));
        $resUpt->assertOk();
        $resUpt->assertDontSee($detailUrlDinas, false);
    }

    // ==========================================
    // B. PENGUJIAN TAMBAH TRANSAKSI PAJAK
    // ==========================================

    public function test_tambah_transaksi_pajak_tahunan_berhasil_dan_mendemosi_tahunan_lama(): void
    {
        Storage::fake('public');
        $admin = $this->buatUserRole('Admin Aset');
        $kendaraan = $this->buatKendaraan();

        // Buat 1 transaksi tahunan lama
        $pajakTahunanLama = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'Tahunan',
            'tahun' => 2025,
            'tanggal_bayar' => '2025-05-10',
            'tanggal_berakhir' => '2026-05-10',
            'nominal' => 1000000,
            'total_pajak' => 1000000,
            'pajak_5_tahunan' => false,
            'status' => 'Aktif',
        ]);

        $fileBukti = UploadedFile::fake()->create('bukti_pajak.pdf', 500, 'application/pdf');

        $response = $this->loginSebagai($admin)->postJson(route('kendaraan.pajak.store', $kendaraan), [
            'pajak_5_tahunan' => 0,
            'tahun' => 2026,
            'tanggal_bayar' => '2026-05-10',
            'tanggal_berakhir' => '2027-05-10',
            'nominal' => 1200000,
            'bukti_pajak' => $fileBukti,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // Transaksi lama berubah menjadi Tidak Aktif
        $this->assertSame('Tidak Aktif', $pajakTahunanLama->fresh()->status);

        // Transaksi baru tersimpan aktif
        $pajakBaru = PajakKendaraan::where('id_kendaraan', $kendaraan->id_kendaraan)
            ->where('status', 'Aktif')
            ->first();

        $this->assertNotNull($pajakBaru);
        $this->assertSame('Tahunan', $pajakBaru->jenis_pajak);
        $this->assertSame(2026, $pajakBaru->tahun);
        $this->assertSame('2027-05-10', $pajakBaru->tanggal_berakhir->format('Y-m-d'));
        $this->assertNull($pajakBaru->tanggal_berakhir_stnk);
        $this->assertEquals(1200000, (float) $pajakBaru->nominal);
        $this->assertEquals(1200000, (float) $pajakBaru->total_pajak);
        $this->assertFalse($pajakBaru->pajak_5_tahunan);
        $this->assertNotNull($pajakBaru->bukti_pajak_path);
        Storage::disk('public')->assertExists($pajakBaru->bukti_pajak_path);
    }

    public function test_tambah_transaksi_pajak_5_tahunan_menyimpan_dua_tanggal_terpisah_dan_mendemosi_semua_pkb_aktif_sebelumnya(): void
    {
        Storage::fake('public');
        $admin = $this->buatUserRole('Admin Aset');
        $kendaraan = $this->buatKendaraan('L 1234 AA');

        // Setup: ada transaksi tahunan aktif dan 5-tahunan lama aktif
        $tahunanLama = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'Tahunan',
            'tahun' => 2025,
            'tanggal_bayar' => '2025-05-10',
            'tanggal_berakhir' => '2026-05-10',
            'nominal' => 1000000,
            'total_pajak' => 1000000,
            'pajak_5_tahunan' => false,
            'status' => 'Aktif',
        ]);

        $limaTahunanLama = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => '5 Tahunan',
            'tahun' => 2021,
            'tanggal_bayar' => '2021-05-10',
            'tanggal_berakhir' => '2022-05-10',
            'tanggal_berakhir_stnk' => '2026-05-10',
            'nominal' => 2000000,
            'total_pajak' => 2000000,
            'pajak_5_tahunan' => true,
            'status' => 'Aktif',
        ]);

        $fileBukti = UploadedFile::fake()->create('bukti_5th.pdf', 800, 'application/pdf');

        $response = $this->loginSebagai($admin)->postJson(route('kendaraan.pajak.store', $kendaraan), [
            'pajak_5_tahunan' => 1,
            'tahun' => 2026,
            'tanggal_bayar' => '2026-05-10',
            'tanggal_berakhir' => '2027-05-10', // Jatuh tempo PKB berikutnya
            'tanggal_berakhir_stnk' => '2031-05-10', // Masa berlaku 5 Tahunan STNK
            'nomor_plat_baru' => 'L 1234 AA',
            'nominal' => 2500000,
            'bukti_pajak' => $fileBukti,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // Verifikasi: 5-Tahunan lama didemosi menjadi Tidak Aktif
        $this->assertSame('Tidak Aktif', $limaTahunanLama->fresh()->status);

        // Verifikasi: Tahunan aktif lama JUGA didemosi menjadi Tidak Aktif
        // (Karena transaksi 5 Tahunan menjadi sumber informasi terbaru untuk jatuh tempo PKB dan STNK)
        $this->assertSame('Tidak Aktif', $tahunanLama->fresh()->status);

        // Verifikasi: Record 5 Tahunan baru menyimpan kedua tanggal secara terpisah
        $pajakBaru = PajakKendaraan::where('id_kendaraan', $kendaraan->id_kendaraan)
            ->where('status', 'Aktif')
            ->first();

        $this->assertNotNull($pajakBaru);
        $this->assertTrue($pajakBaru->pajak_5_tahunan);
        $this->assertSame('2027-05-10', $pajakBaru->tanggal_berakhir->format('Y-m-d'));
        $this->assertSame('2031-05-10', $pajakBaru->tanggal_berakhir_stnk->format('Y-m-d'));
        $this->assertNotEquals($pajakBaru->tanggal_berakhir->format('Y-m-d'), $pajakBaru->tanggal_berakhir_stnk->format('Y-m-d'));
    }

    // ==========================================
    // C. PENGUJIAN PENGELOLAAN PLAT NOMOR
    // ==========================================

    public function test_transaksi_5_tahunan_dengan_nomor_plat_sama_memperbarui_plat_aktif_tanpa_membuat_baris_baru(): void
    {
        Storage::fake('public');
        $admin = $this->buatUserRole('Admin Aset');
        $kendaraan = $this->buatKendaraan('L 8888 BB', '2026-05-10');

        $jumlahPlatAwal = RiwayatPlat::where('id_kendaraan', $kendaraan->id_kendaraan)->count();
        $this->assertSame(1, $jumlahPlatAwal);

        $fileBukti = UploadedFile::fake()->create('bukti.pdf', 300, 'application/pdf');

        $response = $this->loginSebagai($admin)->postJson(route('kendaraan.pajak.store', $kendaraan), [
            'pajak_5_tahunan' => 1,
            'tahun' => 2026,
            'tanggal_bayar' => '2026-05-10',
            'tanggal_berakhir' => '2027-05-10',
            'tanggal_berakhir_stnk' => '2031-05-10',
            'nomor_plat_baru' => 'L 8888 BB', // Sama dengan nomor plat aktif
            'nominal' => 2000000,
            'bukti_pajak' => $fileBukti,
        ]);

        $response->assertOk();

        // Jumlah baris di riwayat_plat TIDAK BERTAMBAH
        $this->assertSame(1, RiwayatPlat::where('id_kendaraan', $kendaraan->id_kendaraan)->count());

        // Data plat aktif diperbarui tanggal berlakunya
        $platAktif = $kendaraan->platAktif;
        $this->assertSame('L 8888 BB', $platAktif->nomor_plat);
        $this->assertSame('2031-05-10', $platAktif->tanggal_berlaku->format('Y-m-d'));
        $this->assertSame('Aktif', $platAktif->status);
    }

    public function test_transaksi_5_tahunan_dengan_nomor_plat_berbeda_memperbarui_plat_aktif_in_place_tanpa_duplikasi(): void
    {
        Storage::fake('public');
        $admin = $this->buatUserRole('Admin Aset');
        $kendaraan = $this->buatKendaraan('L 1111 AA', '2026-05-10');

        $jumlahPlatAwal = RiwayatPlat::where('id_kendaraan', $kendaraan->id_kendaraan)->count();
        $this->assertSame(1, $jumlahPlatAwal);

        $fileBukti = UploadedFile::fake()->create('bukti.pdf', 300, 'application/pdf');

        $response = $this->loginSebagai($admin)->postJson(route('kendaraan.pajak.store', $kendaraan), [
            'pajak_5_tahunan' => 1,
            'tahun' => 2026,
            'tanggal_bayar' => '2026-05-10',
            'tanggal_berakhir' => '2027-05-10',
            'tanggal_berakhir_stnk' => '2031-05-10',
            'nomor_plat_baru' => 'W 9999 CC', // Plat baru berubah
            'nominal' => 2000000,
            'bukti_pajak' => $fileBukti,
        ]);

        $response->assertOk();

        // Jumlah baris di riwayat_plat TETAP 1 (tidak membuat duplikasi)
        $this->assertSame(1, RiwayatPlat::where('id_kendaraan', $kendaraan->id_kendaraan)->count());

        // Nomor plat aktif berubah menjadi plat baru dengan tanggal berakhir STNK terbaru
        $platAktif = $kendaraan->fresh()->platAktif;
        $this->assertSame('W 9999 CC', $platAktif->nomor_plat);
        $this->assertSame('2031-05-10', $platAktif->tanggal_berlaku->format('Y-m-d'));
        $this->assertSame('Aktif', $platAktif->status);
    }

    // ==========================================
    // D. PENGUJIAN EDIT TRANSAKSI PAJAK
    // ==========================================

    public function test_edit_transaksi_aktif_berhasil_memperbarui_data_dan_sinkronisasi_plat(): void
    {
        Storage::fake('public');
        $admin = $this->buatUserRole('Admin Aset');
        $kendaraan = $this->buatKendaraan('L 1234 AA', '2031-05-10');

        $fileBuktiLama = UploadedFile::fake()->create('bukti_lama.pdf', 300, 'application/pdf');
        $pathLama = $fileBuktiLama->store('pajak-kendaraan', 'public');

        $pajakAktif = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => '5 Tahunan',
            'tahun' => 2026,
            'tanggal_bayar' => '2026-05-10',
            'tanggal_berakhir' => '2027-05-10',
            'tanggal_berakhir_stnk' => '2031-05-10',
            'nominal' => 2000000,
            'total_pajak' => 2000000,
            'pajak_5_tahunan' => true,
            'status' => 'Aktif',
            'bukti_pajak_path' => $pathLama,
            'bukti_pajak_nama' => 'bukti_lama.pdf',
        ]);

        Storage::disk('public')->assertExists($pathLama);

        $fileBuktiBaru = UploadedFile::fake()->create('bukti_baru.jpg', 400, 'image/jpeg');

        // Edit tanggal, nominal, plat, dan bukti
        $response = $this->loginSebagai($admin)->putJson(route('kendaraan.pajak.update', [$kendaraan, $pajakAktif]), [
            'tahun' => 2026,
            'tanggal_bayar' => '2026-05-12',
            'tanggal_berakhir' => '2027-05-15',
            'tanggal_berakhir_stnk' => '2031-05-15',
            'nomor_plat_baru' => 'L 1234 BB',
            'nominal' => 2250000,
            'bukti_pajak' => $fileBuktiBaru,
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        // Verifikasi data pajak terupdate
        $pajakUpdated = $pajakAktif->fresh();
        $this->assertSame('2026-05-12', $pajakUpdated->tanggal_bayar->format('Y-m-d'));
        $this->assertSame('2027-05-15', $pajakUpdated->tanggal_berakhir->format('Y-m-d'));
        $this->assertSame('2031-05-15', $pajakUpdated->tanggal_berakhir_stnk->format('Y-m-d'));
        $this->assertEquals(2250000, (float) $pajakUpdated->nominal);

        // Verifikasi file lama dihapus dan file baru tersimpan
        Storage::disk('public')->assertMissing($pathLama);
        Storage::disk('public')->assertExists($pajakUpdated->bukti_pajak_path);

        // Verifikasi plat aktif tersinkronisasi in-place
        $platAktif = $kendaraan->fresh()->platAktif;
        $this->assertSame('L 1234 BB', $platAktif->nomor_plat);
        $this->assertSame('2031-05-15', $platAktif->tanggal_berlaku->format('Y-m-d'));
    }

    public function test_edit_transaksi_tidak_aktif_ditolak_oleh_backend(): void
    {
        $admin = $this->buatUserRole('Admin Aset');
        $kendaraan = $this->buatKendaraan();

        $pajakTidakAktif = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'Tahunan',
            'tahun' => 2024,
            'tanggal_bayar' => '2024-05-10',
            'tanggal_berakhir' => '2025-05-10',
            'nominal' => 1000000,
            'total_pajak' => 1000000,
            'pajak_5_tahunan' => false,
            'status' => 'Tidak Aktif',
        ]);

        $response = $this->loginSebagai($admin)->putJson(route('kendaraan.pajak.update', [$kendaraan, $pajakTidakAktif]), [
            'tahun' => 2024,
            'tanggal_bayar' => '2024-05-10',
            'tanggal_berakhir' => '2025-05-20',
            'nominal' => 1100000,
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false, 'message' => 'Hanya transaksi dengan status Aktif yang dapat diedit.']);
    }

    // ==========================================
    // E. PENGUJIAN PENGHAPUSAN TRANSAKSI
    // ==========================================

    public function test_penghapusan_transaksi_pajak_ditolak_403_dan_tidak_menghapus_data(): void
    {
        Storage::fake('public');
        $admin = $this->buatUserRole('Admin Aset');
        $kendaraan = $this->buatKendaraan();

        $fileBukti = UploadedFile::fake()->create('bukti.pdf', 300, 'application/pdf');
        $path = $fileBukti->store('pajak-kendaraan', 'public');

        $pajak = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'Tahunan',
            'tahun' => 2026,
            'tanggal_bayar' => '2026-05-10',
            'tanggal_berakhir' => '2027-05-10',
            'nominal' => 1000000,
            'total_pajak' => 1000000,
            'pajak_5_tahunan' => false,
            'status' => 'Aktif',
            'bukti_pajak_path' => $path,
            'bukti_pajak_nama' => 'bukti.pdf',
        ]);

        $response = $this->loginSebagai($admin)->deleteJson(route('kendaraan.pajak.destroy', [$kendaraan, $pajak]));

        // Endpoint DELETE wajib ditolak HTTP 403 Forbidden
        $response->assertForbidden();

        // Data transaksi dan bukti fisik tetap utuh dan tersimpan
        $this->assertDatabaseHas('pajak_kendaraan', ['id_pajak' => $pajak->id_pajak]);
        Storage::disk('public')->assertExists($path);
    }

    // ==========================================
    // F. PENGUJIAN LOGIKA STATUS JATUH TEMPO
    // ==========================================

    public function test_kalkulasi_status_jatuh_tempo_pkb_dan_stnk(): void
    {
        $today = Carbon::today();
        $kendaraan = $this->buatKendaraan();

        // 1. Kondisi Belum Ada Data
        $stPkb0 = $kendaraan->statusPkb($today);
        $this->assertSame('Belum Ada Data', $stPkb0['status']);
        $this->assertSame('-', $stPkb0['label']);

        $stStnk0 = $kendaraan->statusStnk($today);
        $this->assertSame('Belum Ada Data', $stStnk0['status']);
        $this->assertSame('-', $stStnk0['label']);

        // 2. Kondisi Terlambat (jatuh tempo kemarin)
        PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'Tahunan',
            'tahun' => 2025,
            'tanggal_bayar' => $today->copy()->subYear()->toDateString(),
            'tanggal_berakhir' => $today->copy()->subDay()->toDateString(),
            'pajak_5_tahunan' => false,
            'status' => 'Aktif',
        ]);

        $stPkbTerlambat = $kendaraan->fresh()->statusPkb($today);
        $this->assertSame('Terlambat', $stPkbTerlambat['status']);
        $this->assertStringContainsString('(Terlambat)', $stPkbTerlambat['label']);

        // 3. Kondisi Segera Jatuh Tempo (10 hari lagi & Hari ini)
        PajakKendaraan::where('id_kendaraan', $kendaraan->id_kendaraan)->update(['status' => 'Tidak Aktif']);
        PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'Tahunan',
            'tahun' => 2026,
            'tanggal_bayar' => $today->toDateString(),
            'tanggal_berakhir' => $today->copy()->addDays(10)->toDateString(),
            'pajak_5_tahunan' => false,
            'status' => 'Aktif',
        ]);

        $stPkbSegera = $kendaraan->fresh()->statusPkb($today);
        $this->assertSame('Segera Jatuh Tempo', $stPkbSegera['status']);
        $this->assertStringContainsString('10 hari lagi', $stPkbSegera['label']);

        // 4. Kondisi Aman (> 30 hari, misal 60 hari ke depan)
        PajakKendaraan::where('id_kendaraan', $kendaraan->id_kendaraan)->update(['status' => 'Tidak Aktif']);
        PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'Tahunan',
            'tahun' => 2026,
            'tanggal_bayar' => $today->toDateString(),
            'tanggal_berakhir' => $today->copy()->addDays(60)->toDateString(),
            'pajak_5_tahunan' => false,
            'status' => 'Aktif',
        ]);

        $stPkbAman = $kendaraan->fresh()->statusPkb($today);
        $this->assertSame('Aman', $stPkbAman['status']);

        // 5. STNK 5 Tahunan dihitung terpisah
        PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => '5 Tahunan',
            'tahun' => 2026,
            'tanggal_bayar' => $today->toDateString(),
            'tanggal_berakhir' => $today->copy()->addDays(60)->toDateString(),
            'tanggal_berakhir_stnk' => $today->copy()->addYears(5)->toDateString(),
            'pajak_5_tahunan' => true,
            'status' => 'Aktif',
        ]);

        $stStnkAman = $kendaraan->fresh()->statusStnk($today);
        $this->assertSame('Aman', $stStnkAman['status']);
    }

    // ==========================================
    // G. PENGUJIAN VALIDASI DAN PENANGANAN ERROR 422
    // ==========================================

    public function test_validasi_gagal_mengembalikan_422_json_dengan_pesan_jelas(): void
    {
        $admin = $this->buatUserRole('Admin Aset');
        $kendaraan = $this->buatKendaraan();

        // Kirim tanpa tanggal_berakhir dan tanpa bukti_pajak
        $response = $this->loginSebagai($admin)->postJson(route('kendaraan.pajak.store', $kendaraan), [
            'pajak_5_tahunan' => 0,
            'tahun' => 2026,
            'tanggal_bayar' => '2026-05-10',
            'nominal' => 1000000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tanggal_berakhir', 'bukti_pajak']);
    }

    public function test_validasi_pajak_5_tahunan_mewajibkan_tanggal_stnk_dan_plat_baru(): void
    {
        $admin = $this->buatUserRole('Admin Aset');
        $kendaraan = $this->buatKendaraan();

        // 5 Tahunan tanpa tanggal_berakhir_stnk dan nomor_plat_baru
        $response = $this->loginSebagai($admin)->postJson(route('kendaraan.pajak.store', $kendaraan), [
            'pajak_5_tahunan' => 1,
            'tahun' => 2026,
            'tanggal_bayar' => '2026-05-10',
            'tanggal_berakhir' => '2027-05-10',
            'nominal' => 2000000,
            'bukti_pajak' => UploadedFile::fake()->create('bukti.pdf', 300, 'application/pdf'),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['tanggal_berakhir_stnk', 'nomor_plat_baru']);
    }

    // ==========================================
    // H. PENGUJIAN URUTAN, BEBAS WARNA JATUH TEMPO, DAN PAGINATION
    // ==========================================

    public function test_tabel_pajak_menampilkan_transaksi_aktif_paling_atas_dan_diikuti_tidak_aktif(): void
    {
        $admin = $this->buatUserRole('Admin Aset');
        $kendaraan = $this->buatKendaraan();

        $t1 = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB 2024 Lama',
            'tahun' => 2024,
            'tanggal_bayar' => '2024-01-10',
            'tanggal_berakhir' => '2025-01-10',
            'status' => 'Tidak Aktif',
            'nominal' => 1000000,
            'pajak_5_tahunan' => false,
        ]);

        $t2 = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB 2025 Lama',
            'tahun' => 2025,
            'tanggal_bayar' => '2025-01-10',
            'tanggal_berakhir' => '2026-01-10',
            'status' => 'Tidak Aktif',
            'nominal' => 1100000,
            'pajak_5_tahunan' => false,
        ]);

        $t3 = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB 2026 Aktif',
            'tahun' => 2026,
            'tanggal_bayar' => '2026-01-10',
            'tanggal_berakhir' => '2027-01-10',
            'status' => 'Aktif',
            'nominal' => 1200000,
            'pajak_5_tahunan' => false,
        ]);

        $response = $this->loginSebagai($admin)->get(route('kendaraan.show', $kendaraan));
        $response->assertOk();

        // Pastikan urutan dalam HTML: t3 (Aktif) muncul sebelum t2 (Tidak Aktif 2025), lalu t1 (Tidak Aktif 2024)
        $content = $response->getContent();
        $posT3 = strpos($content, 'PKB 2026 Aktif');
        $posT2 = strpos($content, 'PKB 2025 Lama');
        $posT1 = strpos($content, 'PKB 2024 Lama');

        $this->assertNotFalse($posT3);
        $this->assertNotFalse($posT2);
        $this->assertNotFalse($posT1);

        $this->assertTrue($posT3 < $posT2, 'Transaksi Aktif harus berada di atas transaksi Tidak Aktif.');
        $this->assertTrue($posT2 < $posT1, 'Transaksi Tidak Aktif terbaru harus berada di atas transaksi Tidak Aktif lama.');
    }

    public function test_tabel_pajak_tidak_menampilkan_warna_merah_pada_tanggal_terlambat(): void
    {
        $admin = $this->buatUserRole('Admin Aset');
        $kendaraan = $this->buatKendaraan();

        Carbon::setTestNow(Carbon::parse('2026-11-15'));

        PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tahun' => 2025,
            'tanggal_bayar' => '2025-01-10',
            'tanggal_berakhir' => '2026-01-10', // Terlambat > 10 bulan
            'status' => 'Aktif',
            'nominal' => 1000000,
            'pajak_5_tahunan' => false,
        ]);

        $response = $this->loginSebagai($admin)->get(route('kendaraan.show', $kendaraan));
        $response->assertOk();

        $content = $response->getContent();
        // Pada tabel pajak, tanggal berakhir diformat '10 Jan 2026'
        $this->assertStringContainsString('10 Jan 2026', $content);
        // Pastikan tidak ada badge merah pada baris tanggal tabel kelola pajak
        $this->assertStringNotContainsString('bg-red-100 text-red-700">10 Jan 2026</span>', $content);
    }

    public function test_halaman_index_kendaraan_tetap_menampilkan_badge_status_jatuh_tempo(): void
    {
        $admin = $this->buatUserRole('Admin Aset');
        $kendaraan = $this->buatKendaraan();

        Carbon::setTestNow(Carbon::parse('2026-11-15'));

        PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tahun' => 2025,
            'tanggal_bayar' => '2025-01-10',
            'tanggal_berakhir' => '2026-01-10', // Terlambat
            'status' => 'Aktif',
            'nominal' => 1000000,
            'pajak_5_tahunan' => false,
        ]);

        $response = $this->loginSebagai($admin)->get(route('kendaraan.index'));
        $response->assertOk();

        // Pada halaman index, badge status jatuh tempo Terlambat (bg-red-100 text-red-700) tetap muncul
        $response->assertSee('bg-red-100 text-red-700', false);
        $response->assertSee('(Terlambat)', false);
    }

    public function test_pagination_maksimal_5_transaksi_per_halaman_dan_navigasi_halaman(): void
    {
        $admin = $this->buatUserRole('Admin Aset');
        $kendaraan = $this->buatKendaraan();

        // Buat 7 transaksi pajak
        for ($i = 1; $i <= 7; $i++) {
            PajakKendaraan::create([
                'id_kendaraan' => $kendaraan->id_kendaraan,
                'jenis_pajak' => "PKB Ke-{$i}",
                'tahun' => 2020 + $i,
                'tanggal_bayar' => "202{$i}-02-01",
                'tanggal_berakhir' => "202" . ($i + 1) . "-02-01",
                'status' => $i === 7 ? 'Aktif' : 'Tidak Aktif',
                'nominal' => 1000000 + ($i * 50000),
                'pajak_5_tahunan' => false,
            ]);
        }

        // Halaman 1: harus memuat 5 transaksi
        $resPage1 = $this->loginSebagai($admin)->get(route('kendaraan.show', $kendaraan));
        $resPage1->assertOk();

        // Transaksi aktif Ke-7 harus ada di halaman 1
        $resPage1->assertSee('PKB Ke-7');
        // Transaksi ke-6, ke-5, ke-4, ke-3 ada di halaman 1
        $resPage1->assertSee('PKB Ke-6');
        $resPage1->assertSee('PKB Ke-5');
        $resPage1->assertSee('PKB Ke-4');
        $resPage1->assertSee('PKB Ke-3');
        // Transaksi ke-2 dan ke-1 TIDAK boleh ada di halaman 1
        $resPage1->assertDontSee('PKB Ke-2');
        $resPage1->assertDontSee('PKB Ke-1');

        // Pastikan navigasi pagination muncul
        $resPage1->assertSeeText('Menampilkan 1 - 5 dari 7 transaksi');
        $resPage1->assertSee('Next');
        $resPage1->assertSee('Previous');

        // Halaman 2: harus memuat 2 transaksi sisanya (Ke-2 dan Ke-1)
        $resPage2 = $this->loginSebagai($admin)->get(route('kendaraan.show', ['kendaraan' => $kendaraan, 'pajak_page' => 2]));
        $resPage2->assertOk();

        $resPage2->assertSee('PKB Ke-2');
        $resPage2->assertSee('PKB Ke-1');
        $resPage2->assertDontSee('PKB Ke-7');
        $resPage2->assertSeeText('Menampilkan 6 - 7 dari 7 transaksi');
    }

    public function test_tambah_transaksi_baru_otomatis_muncul_paling_atas_dan_menonaktifkan_sebelumnya(): void
    {
        $admin = $this->buatUserRole('Admin Aset');
        $kendaraan = $this->buatKendaraan();

        // 1. Buat transaksi lama berstatus Aktif
        $pajakLama = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB 2025 Awal',
            'tahun' => 2025,
            'tanggal_bayar' => '2025-02-01',
            'tanggal_berakhir' => '2026-02-01',
            'status' => 'Aktif',
            'nominal' => 1000000,
            'pajak_5_tahunan' => false,
        ]);

        // 2. Admin Aset menambahkan transaksi pajak baru 2026
        $resStore = $this->loginSebagai($admin)->postJson(route('kendaraan.pajak.store', $kendaraan), [
            'pajak_5_tahunan' => 0,
            'tahun' => 2026,
            'tanggal_bayar' => '2026-02-01',
            'tanggal_berakhir' => '2027-02-01',
            'nominal' => 1250000,
            'bukti_pajak' => UploadedFile::fake()->create('bukti_2026.pdf', 300, 'application/pdf'),
        ]);

        $resStore->assertOk();

        // 3. Cek halaman detail kendaraan
        $resShow = $this->loginSebagai($admin)->get(route('kendaraan.show', $kendaraan));
        $resShow->assertOk();

        // Transaksi lama sekarang berstatus Tidak Aktif di database
        $this->assertSame('Tidak Aktif', $pajakLama->fresh()->status);

        $content = $resShow->getContent();
        $posBaru = strpos($content, 'Rp 1.250.000');
        $posLama = strpos($content, 'Rp 1.000.000');

        $this->assertNotFalse($posBaru);
        $this->assertNotFalse($posLama);
        $this->assertTrue($posBaru < $posLama, 'Transaksi baru harus muncul di atas transaksi lama yang telah nonaktif.');
    }

    public function test_tombol_edit_hanya_muncul_pada_transaksi_aktif_di_tabel(): void
    {
        $admin = $this->buatUserRole('Admin Aset');
        $kendaraan = $this->buatKendaraan();

        $pAktif = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB Aktif',
            'tahun' => 2026,
            'tanggal_bayar' => '2026-03-01',
            'tanggal_berakhir' => '2027-03-01',
            'status' => 'Aktif',
            'nominal' => 1500000,
            'pajak_5_tahunan' => false,
        ]);

        $pTidakAktif = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB Riwayat',
            'tahun' => 2025,
            'tanggal_bayar' => '2025-03-01',
            'tanggal_berakhir' => '2026-03-01',
            'status' => 'Tidak Aktif',
            'nominal' => 1400000,
            'pajak_5_tahunan' => false,
        ]);

        $response = $this->loginSebagai($admin)->get(route('kendaraan.show', $kendaraan));
        $response->assertOk();

        // Tombol Edit dengan data-edit-pajak id aktif ada
        $response->assertSee("data-edit-pajak=\"{$pAktif->id_pajak}\"", false);
        // data-edit-pajak untuk id tidak aktif TIDAK ada
        $response->assertDontSee("data-edit-pajak=\"{$pTidakAktif->id_pajak}\"", false);
    }
}
