<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Kendaraan;
use App\Models\Lokasi;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\PenempatanAset;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\SensusAset;
use App\Models\Skpd;
use App\Models\Tanah;
use App\Models\User;
use App\Models\UsulanPenghapusan;
use App\Services\LaporanEksekutifService;
use App\Services\MasaPakaiAset;
use App\Services\PeriodeSensus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LaporanEksekutifTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabase(),
        ]);

        DB::beginTransaction();
        $this->actingAs($this->userDenganRole('Admin Aset'));
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

    private function userDenganRole(string $role): User
    {
        return User::whereHas('role', fn ($q) => $q->where('nama_role', $role))->firstOrFail();
    }

    private function adminUpt(string $namaSkpd): User
    {
        $skpd = Skpd::where('nama_skpd', $namaSkpd)->firstOrFail();
        $pegawai = Pegawai::create([
            'nip' => 'TST'.uniqid(),
            'nama_pegawai' => 'Admin '.$namaSkpd.' Test',
            'id_skpd' => $skpd->id_skpd,
        ]);

        return User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'id_role' => Role::where('nama_role', 'Admin UPT')->firstOrFail()->id_role,
            'username' => 'tst_upt_'.uniqid(),
            'password' => 'rahasia',
            'status_user' => 'aktif',
        ]);
    }

    private function pegawaiRole(string $namaSkpd): User
    {
        $skpd = Skpd::where('nama_skpd', $namaSkpd)->firstOrFail();
        $pegawai = Pegawai::create([
            'nip' => 'TST'.uniqid(),
            'nama_pegawai' => 'Staf '.$namaSkpd.' Test',
            'id_skpd' => $skpd->id_skpd,
        ]);

        return User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'id_role' => Role::where('nama_role', 'Pegawai')->firstOrFail()->id_role,
            'username' => 'tst_peg_'.uniqid(),
            'password' => 'rahasia',
            'status_user' => 'aktif',
        ]);
    }

    private function adminBidang(): User
    {
        $skpd = Skpd::where('nama_skpd', 'Bidang Perlindungan Perkebunan')->firstOrFail();
        $pegawai = Pegawai::create([
            'nip' => 'TST'.uniqid(),
            'nama_pegawai' => 'Admin Bidang Test',
            'id_skpd' => $skpd->id_skpd,
        ]);

        return User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'id_role' => Role::where('nama_role', 'Admin Bidang')->firstOrFail()->id_role,
            'username' => 'tst_bidang_'.uniqid(),
            'password' => 'rahasia',
            'status_user' => 'aktif',
        ]);
    }

    private function lokasiBySkpd(string $namaSkpd): Lokasi
    {
        $skpd = Skpd::where('nama_skpd', $namaSkpd)->firstOrFail();

        return Lokasi::where('id_skpd', $skpd->id_skpd)->firstOrFail();
    }

    /** 1. Test resolusi periode bulanan */
    public function test_resolusi_periode_bulanan(): void
    {
        $service = app(LaporanEksekutifService::class);
        $res = $service->resolvePeriode('bulanan', 2026, 10);

        $this->assertSame('bulanan', $res['jenis']);
        $this->assertSame(2026, $res['tahun']);
        $this->assertSame(10, $res['bulan']);
        $this->assertSame('2026-10-01', $res['start_date']->format('Y-m-d'));
        $this->assertSame('2026-10-31', $res['end_date']->format('Y-m-d'));
        $this->assertSame('2026-10-31 23:59:59', $res['acuan']->format('Y-m-d H:i:s'));
        $this->assertStringContainsString('Oktober 2026', $res['judul_periode']);
    }

    /** 2. Test resolusi periode tahunan dengan acuan 31 Desember */
    public function test_resolusi_periode_tahunan(): void
    {
        $service = app(LaporanEksekutifService::class);
        $res = $service->resolvePeriode('tahunan', 2025);

        $this->assertSame('tahunan', $res['jenis']);
        $this->assertSame(2025, $res['tahun']);
        $this->assertSame('2025-01-01', $res['start_date']->format('Y-m-d'));
        $this->assertSame('2025-12-31', $res['end_date']->format('Y-m-d'));
        $this->assertSame('2025-12-31 23:59:59', $res['acuan']->format('Y-m-d H:i:s'));
        $this->assertSame('Tahun Anggaran 2025', $res['judul_periode']);
    }

    /** 3. Aset pengadaan setelah tanggal acuan tidak masuk saldo */
    public function test_aset_pengadaan_setelah_tanggal_acuan_tidak_masuk_saldo(): void
    {
        $lokasi = Lokasi::firstOrFail();

        $asetMasaDepan = Aset::create([
            'id_lokasi' => $lokasi->id_lokasi,
            'nomor_kartu_barang' => 'TST-FUT-'.uniqid(),
            'tanggal_pengadaan' => '2026-11-15',
            'nilai_perolehan' => 10000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        $service = app(LaporanEksekutifService::class);
        $acuan = Carbon::parse('2026-10-31 23:59:59');

        $query = $service->asetQueryAt($acuan);
        $this->assertFalse($query->where('aset.id_aset', $asetMasaDepan->id_aset)->exists());
    }

    /** 4 & 6. Opsi A: Aset dibeli 2024 dan dihapuskan 2026 TETAP masuk laporan 2025 */
    public function test_aset_dihapuskan_setelah_acuan_tetap_masuk_saldo_historis(): void
    {
        $lokasi = Lokasi::firstOrFail();

        $aset = Aset::create([
            'id_lokasi' => $lokasi->id_lokasi,
            'nomor_kartu_barang' => 'TST-HIST-'.uniqid(),
            'tanggal_pengadaan' => '2024-03-01',
            'nilai_perolehan' => 20000000,
            'kondisi' => 'Rusak Berat',
            'status_aset' => 'dihapuskan',
            'is_kendaraan' => false,
        ]);

        UsulanPenghapusan::create([
            'id_aset' => $aset->id_aset,
            'tanggal_usulan' => '2026-05-10',
            'status_usulan' => 'disetujui',
            'no_sk' => 'SK/2026/001',
            'tanggal_sk' => '2026-07-20',
        ]);

        $service = app(LaporanEksekutifService::class);

        // Laporan per 31 Desember 2025: SK penghapusan belum berlaku (2026-07-20 > 2025-12-31)
        $acuan2025 = Carbon::parse('2025-12-31 23:59:59');
        $masuk2025 = $service->asetQueryAt($acuan2025)->where('aset.id_aset', $aset->id_aset)->exists();
        $this->assertTrue($masuk2025, 'Aset dihapuskan di 2026 harus tetap muncul pada laporan tahun 2025');

        // Laporan per 31 Desember 2026: SK penghapusan sudah berlaku (2026-07-20 <= 2026-12-31)
        $acuan2026 = Carbon::parse('2026-12-31 23:59:59');
        $masuk2026 = $service->asetQueryAt($acuan2026)->where('aset.id_aset', $aset->id_aset)->exists();
        $this->assertFalse($masuk2026, 'Aset harus keluar dari laporan setelah tanggal SK berlaku');
    }

    /** 5. Aset dengan SK penghapusan sebelum tanggal acuan tidak masuk saldo */
    public function test_aset_dengan_sk_sebelum_acuan_tidak_masuk_saldo(): void
    {
        $lokasi = Lokasi::firstOrFail();

        $aset = Aset::create([
            'id_lokasi' => $lokasi->id_lokasi,
            'nomor_kartu_barang' => 'TST-DEL-'.uniqid(),
            'tanggal_pengadaan' => '2023-01-01',
            'nilai_perolehan' => 15000000,
            'kondisi' => 'Rusak Berat',
            'status_aset' => 'dihapuskan',
            'is_kendaraan' => false,
        ]);

        UsulanPenghapusan::create([
            'id_aset' => $aset->id_aset,
            'tanggal_usulan' => '2025-02-01',
            'status_usulan' => 'disetujui',
            'no_sk' => 'SK/2025/099',
            'tanggal_sk' => '2025-04-15',
        ]);

        $service = app(LaporanEksekutifService::class);
        $acuan = Carbon::parse('2025-12-31 23:59:59');

        $masuk = $service->asetQueryAt($acuan)->where('aset.id_aset', $aset->id_aset)->exists();
        $this->assertFalse($masuk, 'Aset dengan SK sebelum tanggal acuan tidak boleh masuk saldo');
    }

    /** 6. Aset dengan usulan belum disetujui (diajukan) tidak dianggap sudah dihapuskan */
    public function test_usulan_belum_disetujui_tetap_masuk_saldo(): void
    {
        $lokasi = Lokasi::firstOrFail();

        $aset = Aset::create([
            'id_lokasi' => $lokasi->id_lokasi,
            'nomor_kartu_barang' => 'TST-PROP-'.uniqid(),
            'tanggal_pengadaan' => '2024-01-01',
            'nilai_perolehan' => 5000000,
            'kondisi' => 'Rusak Berat',
            'status_aset' => 'diusulkan_hapus',
            'is_kendaraan' => false,
        ]);

        UsulanPenghapusan::create([
            'id_aset' => $aset->id_aset,
            'tanggal_usulan' => '2025-06-01',
            'status_usulan' => 'diajukan', // Belum disetujui, belum ada SK
            'no_sk' => null,
            'tanggal_sk' => null,
        ]);

        $service = app(LaporanEksekutifService::class);
        $acuan = Carbon::parse('2025-12-31 23:59:59');

        $masuk = $service->asetQueryAt($acuan)->where('aset.id_aset', $aset->id_aset)->exists();
        $this->assertTrue($masuk, 'Aset dalam usulan yang belum disetujui masih sah aset dinas');
    }

    /** 7. Aset dengan beberapa riwayat usulan tidak terduplikasi */
    public function test_aset_dengan_beberapa_usulan_tidak_terduplikasi(): void
    {
        $lokasi = Lokasi::firstOrFail();

        $aset = Aset::create([
            'id_lokasi' => $lokasi->id_lokasi,
            'nomor_kartu_barang' => 'TST-MULT-'.uniqid(),
            'tanggal_pengadaan' => '2023-05-01',
            'nilai_perolehan' => 8000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        // Dua usulan di masa depan atau lampau untuk id_aset yang sama
        UsulanPenghapusan::create([
            'id_aset' => $aset->id_aset,
            'tanggal_usulan' => '2027-01-10',
            'status_usulan' => 'diajukan',
        ]);
        UsulanPenghapusan::create([
            'id_aset' => $aset->id_aset,
            'tanggal_usulan' => '2027-02-15',
            'status_usulan' => 'diajukan',
        ]);

        $service = app(LaporanEksekutifService::class);
        $acuan = Carbon::parse('2026-10-31 23:59:59');

        $records = $service->asetQueryAt($acuan)->where('aset.id_aset', $aset->id_aset)->get();
        $this->assertCount(1, $records, 'Aset dengan multiple usulan harus menghasilkan tepat 1 baris');
    }

    /** 8. Kendaraan tidak dihitung ganda */
    public function test_kendaraan_tidak_dihitung_ganda(): void
    {
        $lokasi = Lokasi::firstOrFail();

        $asetKendaraan = Aset::create([
            'id_lokasi' => $lokasi->id_lokasi,
            'nomor_kartu_barang' => 'TST-VEH-'.uniqid(),
            'tanggal_pengadaan' => '2025-01-10',
            'nilai_perolehan' => 150000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => true,
        ]);

        Kendaraan::create([
            'id_aset' => $asetKendaraan->id_aset,
            'nama_kendaraan' => 'Toyota Hilux Test',
            'jenis_kendaraan' => 'Mobil Dinas',
            'nomor_rangka' => 'MHF'.uniqid(),
            'nomor_mesin' => 'ENG'.uniqid(),
        ]);

        $service = app(LaporanEksekutifService::class);
        $res = $service->kalkulasi(
            Carbon::parse('2025-01-01'),
            Carbon::parse('2025-12-31'),
            Carbon::parse('2025-12-31 23:59:59')
        );

        $this->assertGreaterThanOrEqual(1, $res['total_kendaraan']);
        $this->assertSame(
            $res['total_barang'] + $res['total_kendaraan'],
            $res['total_barang_dan_kendaraan'],
            'Total aset fisik harus tepat sama dengan penjumlahan barang + kendaraan tanpa duplikasi'
        );
    }

    /** 9 & 10. Tanah dihitung sebagai bidang, bukan unit barang, dan tidak disusutkan */
    public function test_tanah_terpisah_dan_tidak_disusutkan(): void
    {
        $tanah = Tanah::create([
            'no_excel' => 'TNH-'.uniqid(),
            'kib' => 'A',
            'tanggal_perolehan' => '2020-05-15',
            'nilai_perolehan' => 500000000,
            'luas_tanah' => 2500.50,
            'alamat' => 'Kebun Percobaan Test',
        ]);

        // Login sebagai Admin Aset agar punya akses tanah
        $admin = $this->userDenganRole('Admin Aset');
        $this->actingAs($admin);

        $service = app(LaporanEksekutifService::class);
        $res = $service->kalkulasi(
            Carbon::parse('2025-01-01'),
            Carbon::parse('2025-12-31'),
            Carbon::parse('2025-12-31 23:59:59')
        );

        $this->assertTrue($res['bisa_akses_tanah']);
        $this->assertGreaterThanOrEqual(1, $res['total_bidang_tanah']);
        $this->assertGreaterThanOrEqual(2500.50, $res['total_luas_tanah']);
        $this->assertSame(
            $res['total_nilai_perolehan_tanah'],
            $res['total_nilai_buku_tanah'],
            'Nilai buku tanah harus tepat sama dengan nilai perolehan (penyusutan tanah = 0)'
        );
    }

    /** 11. Nilai buku dihitung sesuai tanggal acuan menggunakan MasaPakaiAset */
    public function test_nilai_buku_dihitung_sesuai_tanggal_acuan(): void
    {
        $lokasi = Lokasi::firstOrFail();

        // Aset dengan pengadaan 2020-01-01, habis 2025-01-01 (5 tahun = 60 bulan), nilai 50.000.000
        $aset = Aset::create([
            'id_lokasi' => $lokasi->id_lokasi,
            'nomor_kartu_barang' => 'TST-DEP-'.uniqid(),
            'tanggal_pengadaan' => '2020-01-01',
            'tanggal_habis_pakai' => '2025-01-01',
            'nilai_perolehan' => 50000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        $masaPakai = app(MasaPakaiAset::class);

        // Pada 2022-01-01 (2 tahun terlewati, sisa 3 tahun) -> nilai buku 30.000.000
        $eval2022 = $masaPakai->evaluasi($aset, Carbon::parse('2022-01-01'));
        $this->assertEquals(30000000.0, $eval2022['nilai_buku']);

        // Pada 2025-01-01 (habis pakai) -> nilai buku 0
        $eval2025 = $masaPakai->evaluasi($aset, Carbon::parse('2025-01-01'));
        $this->assertEquals(0.0, $eval2025['nilai_buku']);
        $this->assertSame('habis', $eval2025['masa_status']);
    }

    /** 12. Aset dengan ruangan dan pemegang sekaligus tidak dihitung dua kali */
    public function test_aset_ruangan_dan_pemegang_tidak_dihitung_dua_kali(): void
    {
        $lokasi = Lokasi::firstOrFail();
        $skpd = Skpd::firstOrFail();

        $ruangan = Ruangan::create([
            'nama_ruangan' => 'Ruang Uji Laporan',
            'id_lokasi' => $lokasi->id_lokasi,
            'id_skpd' => $skpd->id_skpd,
            'lantai' => 1,
        ]);

        $pegawai = Pegawai::create([
            'nip' => 'TST'.uniqid(),
            'nama_pegawai' => 'Pegawai Ganda Test',
            'id_skpd' => $skpd->id_skpd,
        ]);

        $aset = Aset::create([
            'id_lokasi' => $lokasi->id_lokasi,
            'nomor_kartu_barang' => 'TST-UNION-'.uniqid(),
            'tanggal_pengadaan' => '2025-01-01',
            'nilai_perolehan' => 10000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        PenempatanAset::create([
            'id_aset' => $aset->id_aset,
            'id_ruangan' => $ruangan->id_ruangan,
            'tanggal_mulai' => '2025-01-02',
            'status' => 'aktif',
        ]);

        PemegangAset::create([
            'id_aset' => $aset->id_aset,
            'id_pegawai' => $pegawai->id_pegawai,
            'tanggal_mulai' => '2025-01-02',
            'status' => 'aktif',
        ]);

        $admin = $this->userDenganRole('Admin Aset');
        $this->actingAs($admin);

        $service = app(LaporanEksekutifService::class);
        $res = $service->kalkulasi(
            Carbon::parse('2025-01-01'),
            Carbon::parse('2025-12-31'),
            Carbon::parse('2025-12-31 23:59:59')
        );

        $this->assertLessThanOrEqual(
            $res['total_barang_dan_kendaraan'],
            $res['memiliki_penempatan_pemegang_count'],
            'Jumlah aset berpenempatan/pemegang tidak boleh melebihi total aset'
        );
        $this->assertLessThanOrEqual(100.0, $res['persentase_penempatan_pemegang']);
    }

    /** 13. Hasil sensus tidak tercampur dengan kondisi aset */
    public function test_hasil_sensus_tidak_tercampur_kondisi_aset(): void
    {
        $lokasi = Lokasi::firstOrFail();

        // Aset kondisi master: Rusak Berat
        $aset = Aset::create([
            'id_lokasi' => $lokasi->id_lokasi,
            'nomor_kartu_barang' => 'TST-SENS-'.uniqid(),
            'tanggal_pengadaan' => '2025-02-01',
            'nilai_perolehan' => 7500000,
            'kondisi' => 'Rusak Berat',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        // Hasil sensus fisik lapangan: Baik
        SensusAset::create([
            'id_aset' => $aset->id_aset,
            'periode' => '2025–2028',
            'hasil_sensus' => 'Baik',
            'tanggal_sensus' => '2025-06-15',
            'catatan' => 'Fisik ditemukan dalam keadaan baik',
        ]);

        $admin = $this->userDenganRole('Admin Aset');
        $this->actingAs($admin);

        $service = app(LaporanEksekutifService::class);
        $res = $service->kalkulasi(
            Carbon::parse('2025-01-01'),
            Carbon::parse('2025-12-31'),
            Carbon::parse('2025-12-31 23:59:59')
        );

        // Kondisi master Rusak Berat terhitung pada indikator fisik
        $this->assertGreaterThanOrEqual(1, $res['kondisi_rusak_berat']);
        // Dan hasil sensus fisik tercatat di sensus_baik
        $this->assertGreaterThanOrEqual(1, $res['sensus_baik']);
    }

    /** 14. Admin Aset dapat mengakses laporan dan melihat seluruh unit */
    public function test_admin_aset_dapat_melihat_seluruh_unit(): void
    {
        $admin = $this->userDenganRole('Admin Aset');

        $response = $this->actingAs($admin)->get(route('laporan.index', [
            'jenis' => 'bulanan',
            'tahun' => 2026,
            'bulan' => 10,
        ]));

        $response->assertStatus(200);
        $response->assertViewIs('laporan.index');
        $response->assertSee('Laporan Eksekutif');
        $response->assertSee('Semua Unit (Konsolidasi)');
    }

    /** 15. Admin UPT hanya melihat cakupan lokasi fisiknya */
    public function test_admin_upt_terkunci_pada_cakupan_lokasinya(): void
    {
        $adminP2btp = $this->adminUpt('UPT P2BTP');
        $lokasiP2btp = $this->lokasiBySkpd('UPT P2BTP');
        $lokasiPsbp = $this->lokasiBySkpd('UPT PSBP');

        // Buat aset di P2BTP dan PSBP
        $asetP2btp = Aset::create([
            'id_lokasi' => $lokasiP2btp->id_lokasi,
            'nomor_kartu_barang' => 'P2BTP-'.uniqid(),
            'tanggal_pengadaan' => '2025-01-01',
            'nilai_perolehan' => 1000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        $asetPsbp = Aset::create([
            'id_lokasi' => $lokasiPsbp->id_lokasi,
            'nomor_kartu_barang' => 'PSBP-'.uniqid(),
            'tanggal_pengadaan' => '2025-01-01',
            'nilai_perolehan' => 1000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        // Login sebagai Admin P2BTP
        $response = $this->actingAs($adminP2btp)->get(route('laporan.index', [
            'jenis' => 'tahunan',
            'tahun' => 2025,
        ]));

        $response->assertStatus(200);
        // Pastikan dropdown pemilihan unit tidak muncul untuk role non-Admin Aset
        $response->assertDontSee('Semua Unit (Konsolidasi)');

        $service = app(LaporanEksekutifService::class);
        $query = $service->asetQueryAt(Carbon::parse('2025-12-31 23:59:59'));

        $this->assertTrue($query->where('aset.id_aset', $asetP2btp->id_aset)->exists());
        $this->assertFalse($query->where('aset.id_aset', $asetPsbp->id_aset)->exists());
    }

    /** 16. Admin Bidang hanya melihat lokasi Dinas Induk */
    public function test_admin_bidang_hanya_melihat_lokasi_dinas_induk(): void
    {
        $adminBidang = $this->adminBidang();
        $lokasiDinas = Lokasi::where('jenis_lokasi', 'Dinas')->firstOrFail();
        $lokasiUpt = Lokasi::where('jenis_lokasi', 'UPT')->firstOrFail();

        $asetDinas = Aset::create([
            'id_lokasi' => $lokasiDinas->id_lokasi,
            'nomor_kartu_barang' => 'DINAS-'.uniqid(),
            'tanggal_pengadaan' => '2025-01-01',
            'nilai_perolehan' => 1000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        $asetUpt = Aset::create([
            'id_lokasi' => $lokasiUpt->id_lokasi,
            'nomor_kartu_barang' => 'UPT-'.uniqid(),
            'tanggal_pengadaan' => '2025-01-01',
            'nilai_perolehan' => 1000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        $this->actingAs($adminBidang);
        $service = app(LaporanEksekutifService::class);
        $query = $service->asetQueryAt(Carbon::parse('2025-12-31 23:59:59'));

        $this->assertTrue($query->where('aset.id_aset', $asetDinas->id_aset)->exists());
        $this->assertFalse($query->where('aset.id_aset', $asetUpt->id_aset)->exists());
    }

    /** 17. Pegawai mengikuti aturan penugasannya */
    public function test_pegawai_mengikuti_aturan_penugasan(): void
    {
        $pegawaiP2btp = $this->pegawaiRole('UPT P2BTP');
        $lokasiP2btp = $this->lokasiBySkpd('UPT P2BTP');
        $lokasiPsbp = $this->lokasiBySkpd('UPT PSBP');

        $asetP2btp = Aset::create([
            'id_lokasi' => $lokasiP2btp->id_lokasi,
            'nomor_kartu_barang' => 'PEG-P2BTP-'.uniqid(),
            'tanggal_pengadaan' => '2025-01-01',
            'nilai_perolehan' => 1000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        $asetPsbp = Aset::create([
            'id_lokasi' => $lokasiPsbp->id_lokasi,
            'nomor_kartu_barang' => 'PEG-PSBP-'.uniqid(),
            'tanggal_pengadaan' => '2025-01-01',
            'nilai_perolehan' => 1000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        $this->actingAs($pegawaiP2btp);
        $service = app(LaporanEksekutifService::class);
        $query = $service->asetQueryAt(Carbon::parse('2025-12-31 23:59:59'));

        $this->assertTrue($query->where('aset.id_aset', $asetP2btp->id_aset)->exists());
        $this->assertFalse($query->where('aset.id_aset', $asetPsbp->id_aset)->exists());
    }

    /** 18. Kompatibilitas rute lama laporan.bulanan tetap mengarah ke modul laporan */
    public function test_route_laporan_bulanan_lama_tetap_berfungsi(): void
    {
        $admin = $this->userDenganRole('Admin Aset');

        $response = $this->actingAs($admin)->get(route('laporan.bulanan'));

        $response->assertStatus(200);
        $response->assertViewIs('laporan.index');
    }

    /** 19. Tamu tanpa login ditolak ke halaman login dan tidak ada endpoint ekspor bypass di Phase 1 */
    public function test_tamu_ditolak_dan_tidak_ada_endpoint_ekspor_bypass(): void
    {
        auth()->logout();

        $response = $this->get(route('laporan.index'));
        $response->assertRedirect(route('login'));

        $this->assertFalse(\Illuminate\Support\Facades\Route::has('laporan.export'));
    }

    // =========================================================================
    // PENGUJIAN PHASE 2 — VISUALISASI GRAFIK LAPORAN EKSEKUTIF
    // =========================================================================

    /** 20. Skenario 1: Akurasi data grafik kondisi aset (Master Aset vs Sensus) */
    public function test_skenario_1_akurasi_grafik_kondisi_aset(): void
    {
        $admin = $this->userDenganRole('Admin Aset');
        $this->actingAs($admin);

        $lokasi = Lokasi::firstOrFail();

        // Buat aset master dengan kondisi Baik, tetapi memiliki catatan sensus Rusak Berat
        $aset1 = Aset::create([
            'id_lokasi' => $lokasi->id_lokasi,
            'nomor_kartu_barang' => 'GRF-KND-1-'.uniqid(),
            'tanggal_pengadaan' => '2026-01-01',
            'nilai_perolehan' => 1000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        SensusAset::create([
            'id_aset' => $aset1->id_aset,
            'periode' => '2025-2027',
            'tahun_sensus' => 2026,
            'tanggal_sensus' => '2026-06-01',
            'hasil_sensus' => 'Rusak Berat',
            'id_user' => $admin->id_user,
        ]);

        $service = app(LaporanEksekutifService::class);
        $acuan = Carbon::parse('2026-12-31 23:59:59');
        $start = Carbon::parse('2026-01-01');
        $end = Carbon::parse('2026-12-31');

        $grafik = $service->kalkulasiGrafik($start, $end, $acuan);

        // Sumber kondisi grafik WAJIB dari master aset (Baik), BUKAN dari sensus (Rusak Berat)
        $this->assertContains('Baik', $grafik['kondisi']['labels']);
        $this->assertContains('Rusak Ringan', $grafik['kondisi']['labels']);
        $this->assertContains('Rusak Berat', $grafik['kondisi']['labels']);
        $this->assertGreaterThanOrEqual(1, $grafik['kondisi']['data'][0]); // Baik >= 1
        $this->assertGreaterThan(0, $grafik['kondisi']['total']);
        $this->assertEquals(100.0, round(array_sum($grafik['kondisi']['persen']), 0));
    }

    /** 21. Skenario 2: Distribusi kategori tanpa duplikasi (Barang vs Kendaraan) */
    public function test_skenario_2_distribusi_kategori_tanpa_duplikasi(): void
    {
        $admin = $this->userDenganRole('Admin Aset');
        $this->actingAs($admin);
        $lokasi = Lokasi::firstOrFail();

        $asetKendaraan = Aset::create([
            'id_lokasi' => $lokasi->id_lokasi,
            'nomor_kartu_barang' => 'GRF-KNDR-'.uniqid(),
            'tanggal_pengadaan' => '2026-01-01',
            'nilai_perolehan' => 25000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => true,
        ]);

        $service = app(LaporanEksekutifService::class);
        $acuan = Carbon::parse('2026-12-31 23:59:59');
        $start = Carbon::parse('2026-01-01');
        $end = Carbon::parse('2026-12-31');

        $grafik = $service->kalkulasiGrafik($start, $end, $acuan);

        $this->assertContains('Kendaraan Bermotor', $grafik['kategori']['labels']);
        $idxKendaraan = array_search('Kendaraan Bermotor', $grafik['kategori']['labels'], true);
        $this->assertNotFalse($idxKendaraan);
        $this->assertGreaterThanOrEqual(1, $grafik['kategori']['data'][$idxKendaraan]);

        // Pastikan kategori semu "Peralatan & Mesin Lainnya" tidak muncul
        $this->assertNotContains('Peralatan & Mesin Lainnya', $grafik['kategori']['labels']);
        $this->assertContains('Peralatan dan Mesin', $grafik['kategori']['labels']);

        // Verifikasi total kategori sama dengan jumlah penjumlahan elemen grafik
        $this->assertSame(array_sum($grafik['kategori']['data']), $grafik['kategori']['total']);

        $kalkulasi = $service->kalkulasi($start, $end, $acuan);
        $expectedTotal = $kalkulasi['total_barang_dan_kendaraan'] + ($kalkulasi['bisa_akses_tanah'] ? $kalkulasi['total_bidang_tanah'] : 0);
        $this->assertSame($expectedTotal, $grafik['kategori']['total']);
    }

    /** 21B. Pengujian Khusus Grafik B: Tiga kelompok resmi konsisten dengan neraca */
    public function test_grafik_b_tiga_kelompok_resmi(): void
    {
        $admin = $this->userDenganRole('Admin Aset');
        $this->actingAs($admin);

        $service = app(LaporanEksekutifService::class);
        $acuan = Carbon::parse('2026-12-31 23:59:59');
        $start = Carbon::parse('2026-01-01');
        $end = Carbon::parse('2026-12-31');

        $grafik = $service->kalkulasiGrafik($start, $end, $acuan);

        // 1. Grafik B memiliki kelompok resmi
        $this->assertContains('Peralatan dan Mesin', $grafik['kategori']['labels']);
        $this->assertContains('Kendaraan Bermotor', $grafik['kategori']['labels']);
        $this->assertContains('Tanah (KIB A)*', $grafik['kategori']['labels']);

        // 2. Tidak ada kategori semu
        $this->assertNotContains('Peralatan & Mesin Lainnya', $grafik['kategori']['labels']);
        $this->assertNotContains('Peralatan dan Mesin Lainnya', $grafik['kategori']['labels']);

        // 3. Total footer sama dengan penjumlahan kategori yang ditampilkan
        $this->assertSame(array_sum($grafik['kategori']['data']), $grafik['kategori']['total']);

        // 4. Verifikasi untuk role tanpa akses Tanah (Admin UPT PSBP)
        $adminPsbp = $this->adminUpt('UPT PSBP');
        $this->actingAs($adminPsbp);
        $servicePsbp = app(LaporanEksekutifService::class);
        $grafikPsbp = $servicePsbp->kalkulasiGrafik($start, $end, $acuan);

        $this->assertNotContains('Tanah (KIB A)*', $grafikPsbp['kategori']['labels']);
        $this->assertSame(array_sum($grafikPsbp['kategori']['data']), $grafikPsbp['kategori']['total']);
    }

    /** 22. Skenario 3: Perbandingan nilai perolehan dan nilai buku (Penyusutan per t_acuan) */
    public function test_skenario_3_perbandingan_nilai_perolehan_dan_nilai_buku(): void
    {
        $admin = $this->userDenganRole('Admin Aset');
        $this->actingAs($admin);

        $service = app(LaporanEksekutifService::class);
        $acuan = Carbon::parse('2026-12-31 23:59:59');
        $start = Carbon::parse('2026-01-01');
        $end = Carbon::parse('2026-12-31');

        $grafik = $service->kalkulasiGrafik($start, $end, $acuan);

        // Grafik C HANYA memiliki 2 kategori: Barang Inventaris dan Kendaraan Bermotor
        $this->assertSame(['Barang Inventaris', 'Kendaraan Bermotor'], $grafik['nilai']['labels']);
        $this->assertCount(2, $grafik['nilai']['nilai_perolehan']);
        $this->assertCount(2, $grafik['nilai']['nilai_buku']);

        // Tanah (KIB A) dipastikan dieksklusikan dari Grafik C
        $this->assertNotContains('Tanah (KIB A)*', $grafik['nilai']['labels']);

        // Nilai perolehan total harus selalu >= nilai buku total (karena penyusutan akumulasi >= 0)
        $this->assertGreaterThanOrEqual($grafik['nilai']['total_buku'], $grafik['nilai']['total_perolehan']);

        // Nilai perolehan dan nilai buku pada Grafik C konsisten dengan kalkulasi aset Monitoring Aset (Barang + Kendaraan)
        $kalkulasi = $service->kalkulasi($start, $end, $acuan);
        $this->assertEquals($kalkulasi['total_nilai_perolehan_aset'], $grafik['nilai']['total_perolehan']);
        $this->assertEquals($kalkulasi['total_nilai_buku_aset'], $grafik['nilai']['total_buku']);
    }

    /** 23. Skenario 4: Tren pengadaan mengabaikan aset dengan tanggal kosong */
    public function test_skenario_4_tren_pengadaan_mengabaikan_tanggal_kosong(): void
    {
        $admin = $this->userDenganRole('Admin Aset');
        $this->actingAs($admin);
        $lokasi = Lokasi::firstOrFail();

        // Aset dengan tanggal null tidak boleh masuk asetQueryAt maupun grafik tren
        $asetNull = Aset::create([
            'id_lokasi' => $lokasi->id_lokasi,
            'nomor_kartu_barang' => 'GRF-NULL-'.uniqid(),
            'tanggal_pengadaan' => null,
            'nilai_perolehan' => 5000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        $service = app(LaporanEksekutifService::class);
        $acuan = Carbon::parse('2026-12-31 23:59:59');
        $start = Carbon::parse('2026-01-01');
        $end = Carbon::parse('2026-12-31');

        $grafik = $service->kalkulasiGrafik($start, $end, $acuan);

        // Seluruh label tren harus berupa string tahun numerik yang valid (mis. '2022', '2026')
        foreach ($grafik['tren_pengadaan']['labels'] as $labelTahun) {
            $this->assertTrue(is_numeric($labelTahun));
            $this->assertGreaterThanOrEqual(1990, (int) $labelTahun);
        }

        // Jumlah item tren tidak boleh menghitung aset bernilai tanggal_pengadaan null
        $queryAt = $service->asetQueryAt($acuan);
        $this->assertFalse($queryAt->where('aset.id_aset', $asetNull->id_aset)->exists());
    }

    /** 24. Skenario 5: Kondisi aset per unit kerja mematuhi AsetScope dan eksklusi Tanah */
    public function test_skenario_5_kondisi_aset_per_unit_kerja(): void
    {
        $admin = $this->userDenganRole('Admin Aset');
        $this->actingAs($admin);

        $service = app(LaporanEksekutifService::class);
        $acuan = Carbon::parse('2026-12-31 23:59:59');
        $start = Carbon::parse('2026-01-01');
        $end = Carbon::parse('2026-12-31');

        $grafik = $service->kalkulasiGrafik($start, $end, $acuan);

        $this->assertIsArray($grafik['kondisi_unit']['labels']);
        $this->assertCount(count($grafik['kondisi_unit']['labels']), $grafik['kondisi_unit']['baik']);
        $this->assertCount(count($grafik['kondisi_unit']['labels']), $grafik['kondisi_unit']['rusak_ringan']);
        $this->assertCount(count($grafik['kondisi_unit']['labels']), $grafik['kondisi_unit']['rusak_berat']);

        // Tanah TIDAK boleh muncul sebagai unit kerja
        foreach ($grafik['kondisi_unit']['labels'] as $unitLabel) {
            $this->assertStringNotContainsStringIgnoringCase('Tanah', $unitLabel);
            $this->assertStringNotContainsStringIgnoringCase('KIB A', $unitLabel);
        }
    }

    /** 24B. Skenario 5B: Pengelompokan murni Lokasi Fisik, Barang vs Kendaraan, dan Ringkasan Penempatan */
    public function test_skenario_5b_kondisi_aset_berdasarkan_lokasi_fisik_dan_ringkasan_penempatan(): void
    {
        $admin = $this->userDenganRole('Admin Aset');
        $this->actingAs($admin);

        $service = app(LaporanEksekutifService::class);
        $acuan = Carbon::parse('2026-12-31 23:59:59');
        $start = Carbon::parse('2026-01-01');
        $end = Carbon::parse('2026-12-31');

        $grafik = $service->kalkulasiGrafik($start, $end, $acuan);
        $kondisiUnit = $grafik['kondisi_unit'];

        // 1. Sumbu X hanya berisi Lokasi Fisik resmi dan 'Belum Ditetapkan Lokasi'
        $this->assertContains('Kantor Dinas Perkebunan Provinsi Jawa Timur', $kondisiUnit['labels']);
        $this->assertContains('Kantor UPT P2BTP', $kondisiUnit['labels']);
        $this->assertContains('Kantor UPT PSBP', $kondisiUnit['labels']);
        $this->assertContains('Belum Ditetapkan Lokasi', $kondisiUnit['labels']);

        // 2. Tidak mencampurkan SKPD ke dalam label grafik
        $this->assertNotContains('Sekretariat', $kondisiUnit['labels']);
        $this->assertNotContains('Bidang Perlindungan Perkebunan', $kondisiUnit['labels']);
        $this->assertNotContains('Bidang Produksi Tanaman Tahunan', $kondisiUnit['labels']);
        $this->assertNotContains('Bidang Pengelolahan dan Pemasaran Hasil', $kondisiUnit['labels']);
        $this->assertNotContains('UPT P2BTP', $kondisiUnit['labels']);
        $this->assertNotContains('UPT PSBP', $kondisiUnit['labels']);

        // 3. Terdapat rincian kelompok Barang Inventaris dan Kendaraan Bermotor
        $this->assertArrayHasKey('barang_baik', $kondisiUnit);
        $this->assertArrayHasKey('barang_rusak_ringan', $kondisiUnit);
        $this->assertArrayHasKey('barang_rusak_berat', $kondisiUnit);
        $this->assertArrayHasKey('kendaraan_baik', $kondisiUnit);
        $this->assertArrayHasKey('kendaraan_rusak_ringan', $kondisiUnit);
        $this->assertArrayHasKey('kendaraan_rusak_berat', $kondisiUnit);

        // 4. Informasi ringkasan penempatan & pemegang tersedia dan konsisten secara matematis
        $this->assertArrayHasKey('ringkasan', $kondisiUnit);
        $ringkasan = $kondisiUnit['ringkasan'];
        $total = $ringkasan['total'];

        $this->assertSame($total['barang_count'] + $total['kendaraan_count'], $total['total_aset']);
        $this->assertSame($total['dengan_pemegang'] + $total['tanpa_pemegang'], $total['total_aset']);
        $this->assertSame($total['dengan_ruangan'] + $total['tanpa_ruangan'], $total['total_aset']);

        // 5. Total aset pada ringkasan sama dengan total seluruh kondisi baik + rusak ringan + rusak berat + belum diisi
        $totalKondisi = array_sum($kondisiUnit['baik']) + array_sum($kondisiUnit['rusak_ringan']) + array_sum($kondisiUnit['rusak_berat']) + array_sum($kondisiUnit['belum_diisi']);
        $this->assertSame($total['total_aset'], $totalKondisi);

        // 6. View Laporan menyajikan judul baru dan ringkasan penempatan
        $response = $this->get(route('laporan.index'));
        $response->assertStatus(200);
        $response->assertSee('E. Kondisi Aset Berdasarkan Lokasi', false);
        $response->assertSee('Distribusi kondisi Barang Inventaris dan Kendaraan Bermotor berdasarkan lokasi fisik aset.', false);
        $response->assertSee('Ringkasan Penempatan & Pemegang per Lokasi', false);
    }

    /** 24C. Prioritas Lokasi Kendaraan: Master Lokasi -> Fallback Ruangan Pemegang Aktif -> Belum Ditetapkan Lokasi */
    public function test_skenario_5c_prioritas_lokasi_kendaraan_master_fallback_ruangan_dan_tanpa_duplikasi(): void
    {
        $admin = $this->userDenganRole('Admin Aset');
        $this->actingAs($admin);

        $lokDinas = Lokasi::where('nama_lokasi', 'like', '%Dinas%')->firstOrFail();
        $lokP2btp = Lokasi::where('nama_lokasi', 'like', '%P2BTP%')->firstOrFail();
        $skpd = Skpd::firstOrFail();

        // Siapkan ruangan di UPT P2BTP dan ruangan tanpa penetapan lokasi
        $ruangP2btp = Ruangan::create([
            'nama_ruangan' => 'Ruang Uji P2BTP ' . uniqid(),
            'id_lokasi' => $lokP2btp->id_lokasi,
            'id_skpd' => $skpd->id_skpd,
            'lantai' => 1,
        ]);

        $ruangTanpaLokasi = Ruangan::create([
            'nama_ruangan' => 'Ruang Tanpa Lokasi ' . uniqid(),
            'id_lokasi' => null,
            'id_skpd' => $skpd->id_skpd,
            'lantai' => 1,
        ]);

        // Siapkan pegawai
        $pegP2btp = Pegawai::create([
            'nip' => 'TST' . uniqid(),
            'nama_pegawai' => 'Pegawai P2BTP ' . uniqid(),
            'id_skpd' => $skpd->id_skpd,
            'id_ruangan' => $ruangP2btp->id_ruangan,
        ]);

        $pegTanpaLokasi = Pegawai::create([
            'nip' => 'TST' . uniqid(),
            'nama_pegawai' => 'Pegawai Tanpa Lok ' . uniqid(),
            'id_skpd' => $skpd->id_skpd,
            'id_ruangan' => $ruangTanpaLokasi->id_ruangan,
        ]);

        // 1. Kendaraan dengan master lokasi terisi (Dinas), pemegang memiliki ruangan di P2BTP
        //    Prioritas 1: Tetap dihitung di Kantor Dinas
        $aset1 = Aset::create([
            'id_lokasi' => $lokDinas->id_lokasi,
            'nomor_kartu_barang' => 'TST-V1-' . uniqid(),
            'tanggal_pengadaan' => '2025-05-01',
            'nilai_perolehan' => 100000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => true,
        ]);
        Kendaraan::create(['id_aset' => $aset1->id_aset, 'nama_kendaraan' => 'Kendaraan Master Prioritas']);
        PemegangAset::create(['id_aset' => $aset1->id_aset, 'id_pegawai' => $pegP2btp->id_pegawai, 'status' => 'aktif']);

        // 2. Kendaraan dengan master NULL, tetapi pemegang aktif memiliki ruangan di P2BTP
        //    Prioritas 2: Fallback ke lokasi ruangan pemegang (UPT P2BTP)
        $aset2 = Aset::create([
            'id_lokasi' => null,
            'nomor_kartu_barang' => 'TST-V2-' . uniqid(),
            'tanggal_pengadaan' => '2025-05-01',
            'nilai_perolehan' => 100000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => true,
        ]);
        Kendaraan::create(['id_aset' => $aset2->id_aset, 'nama_kendaraan' => 'Kendaraan Fallback Ruangan']);
        PemegangAset::create(['id_aset' => $aset2->id_aset, 'id_pegawai' => $pegP2btp->id_pegawai, 'status' => 'aktif']);

        // 3. Kendaraan tanpa pemegang aktif, master lokasi NULL
        //    Prioritas 3: Masuk ke 'Belum Ditetapkan Lokasi'
        $aset3 = Aset::create([
            'id_lokasi' => null,
            'nomor_kartu_barang' => 'TST-V3-' . uniqid(),
            'tanggal_pengadaan' => '2025-05-01',
            'nilai_perolehan' => 100000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => true,
        ]);
        Kendaraan::create(['id_aset' => $aset3->id_aset, 'nama_kendaraan' => 'Kendaraan Tanpa Pemegang']);

        // 4. Kendaraan dengan pemegang aktif tetapi ruangannya tanpa lokasi fisik (id_lokasi null)
        //    Prioritas 3: Masuk ke 'Belum Ditetapkan Lokasi'
        $aset4 = Aset::create([
            'id_lokasi' => null,
            'nomor_kartu_barang' => 'TST-V4-' . uniqid(),
            'tanggal_pengadaan' => '2025-05-01',
            'nilai_perolehan' => 100000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => true,
        ]);
        Kendaraan::create(['id_aset' => $aset4->id_aset, 'nama_kendaraan' => 'Kendaraan Ruang Tanpa Lokasi']);
        PemegangAset::create(['id_aset' => $aset4->id_aset, 'id_pegawai' => $pegTanpaLokasi->id_pegawai, 'status' => 'aktif']);

        // 5. Kendaraan dengan beberapa riwayat pemegang (1 tidak aktif, 1 aktif)
        //    Tidak boleh terjadi duplikasi kendaraan
        $aset5 = Aset::create([
            'id_lokasi' => null,
            'nomor_kartu_barang' => 'TST-V5-' . uniqid(),
            'tanggal_pengadaan' => '2025-05-01',
            'nilai_perolehan' => 100000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => true,
        ]);
        Kendaraan::create(['id_aset' => $aset5->id_aset, 'nama_kendaraan' => 'Kendaraan Riwayat Ganda']);
        PemegangAset::create(['id_aset' => $aset5->id_aset, 'id_pegawai' => $pegTanpaLokasi->id_pegawai, 'status' => 'tidak aktif']);
        PemegangAset::create(['id_aset' => $aset5->id_aset, 'id_pegawai' => $pegP2btp->id_pegawai, 'status' => 'aktif']);

        // 6. Barang Inventaris dengan master NULL dan pemegang di P2BTP
        //    Perilaku Barang TIDAK BERUBAH: tetap masuk 'Belum Ditetapkan Lokasi' (tidak boleh fallback ruangan)
        $aset6 = Aset::create([
            'id_lokasi' => null,
            'nomor_kartu_barang' => 'TST-B6-' . uniqid(),
            'tanggal_pengadaan' => '2025-05-01',
            'nilai_perolehan' => 10000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);
        PemegangAset::create(['id_aset' => $aset6->id_aset, 'id_pegawai' => $pegP2btp->id_pegawai, 'status' => 'aktif']);

        $service = app(LaporanEksekutifService::class);
        $acuan = Carbon::parse('2025-12-31 23:59:59');
        $start = Carbon::parse('2025-01-01');
        $end = Carbon::parse('2025-12-31');

        $grafik = $service->kalkulasiGrafik($start, $end, $acuan);
        $kondisiUnit = $grafik['kondisi_unit'];
        $ringkasan = $kondisiUnit['ringkasan'];
        $byLokasi = collect($ringkasan['per_lokasi'])->keyBy('lokasi');

        // Verifikasi 1: aset1 berada di Dinas (karena master lokasi terisi)
        $this->assertGreaterThanOrEqual(1, $byLokasi[$lokDinas->nama_lokasi]['kendaraan_count']);

        // Verifikasi 2 & 5: aset2 dan aset5 berada di P2BTP via fallback ruangan pemegang (tepat 2 unit, tanpa duplikasi)
        $this->assertSame(2, $byLokasi[$lokP2btp->nama_lokasi]['kendaraan_count']);

        // Verifikasi 3 & 4: aset3 dan aset4 berada di 'Belum Ditetapkan Lokasi' (tepat 2 unit)
        $this->assertSame(2, $byLokasi['Belum Ditetapkan Lokasi']['kendaraan_count']);

        // Verifikasi 6: Barang Inventaris (aset6) tetap berada di 'Belum Ditetapkan Lokasi'
        $this->assertGreaterThanOrEqual(1, $byLokasi['Belum Ditetapkan Lokasi']['barang_count']);

        // Verifikasi 7: Integritas matematis seluruh ringkasan tetap konsisten
        $total = $ringkasan['total'];
        $this->assertSame($total['barang_count'] + $total['kendaraan_count'], $total['total_aset']);
        $this->assertSame($total['dengan_pemegang'] + $total['tanpa_pemegang'], $total['total_aset']);
        $this->assertSame($total['dengan_ruangan'] + $total['tanpa_ruangan'], $total['total_aset']);

        $totalKondisi = array_sum($kondisiUnit['baik']) + array_sum($kondisiUnit['rusak_ringan']) + array_sum($kondisiUnit['rusak_berat']) + array_sum($kondisiUnit['belum_diisi']);
        $this->assertSame($total['total_aset'], $totalKondisi);

        // Verifikasi 8: Hak akses AsetScope tetap berfungsi normal
        $adminPsbp = $this->adminUpt('UPT PSBP');
        $this->actingAs($adminPsbp);
        $servicePsbp = app(LaporanEksekutifService::class);
        $grafikPsbp = $servicePsbp->kalkulasiGrafik($start, $end, $acuan);
        foreach ($grafikPsbp['kondisi_unit']['labels'] as $lbl) {
            $this->assertStringContainsStringIgnoringCase('PSBP', $lbl);
            $this->assertStringNotContainsStringIgnoringCase('P2BTP', $lbl);
        }
    }

    /** 25. Skenario 6: Konsistensi filter periode (Bulanan vs Tahunan) */
    public function test_skenario_6_konsistensi_filter_periode_bulanan_vs_tahunan(): void
    {
        $admin = $this->userDenganRole('Admin Aset');
        $this->actingAs($admin);

        $service = app(LaporanEksekutifService::class);

        // Filter Bulanan
        $resBulan = $service->resolvePeriode('bulanan', 2026, 10);
        $grafikBulan = $service->kalkulasiGrafik(
            $resBulan['start_date'],
            $resBulan['end_date'],
            $resBulan['acuan'],
            null,
            'bulanan'
        );

        $this->assertCount(4, $grafikBulan['aktivitas']['labels']); // 4 interval mingguan
        $this->assertStringContainsString('Tgl 1 - 7', $grafikBulan['aktivitas']['labels'][0]);

        // Filter Tahunan
        $resTahun = $service->resolvePeriode('tahunan', 2026);
        $grafikTahun = $service->kalkulasiGrafik(
            $resTahun['start_date'],
            $resTahun['end_date'],
            $resTahun['acuan'],
            null,
            'tahunan'
        );

        $this->assertCount(12, $grafikTahun['aktivitas']['labels']); // 12 bulan
        $this->assertSame('Jan', $grafikTahun['aktivitas']['labels'][0]);
        $this->assertSame('Des', $grafikTahun['aktivitas']['labels'][11]);
    }

    /** 26. Skenario 7: Pembatasan akses grafik berdasarkan role (AsetScope & TanahScope) */
    public function test_skenario_7_pembatasan_akses_berdasarkan_role(): void
    {
        // Admin UPT PSBP: tidak memiliki hak akses Tanah dan hanya melihat lokasi UPT PSBP
        $adminPsbp = $this->adminUpt('UPT PSBP');
        $this->actingAs($adminPsbp);

        $service = app(LaporanEksekutifService::class);
        $acuan = Carbon::parse('2026-12-31 23:59:59');
        $start = Carbon::parse('2026-01-01');
        $end = Carbon::parse('2026-12-31');

        $grafik = $service->kalkulasiGrafik($start, $end, $acuan);

        // 1. Admin UPT PSBP tidak boleh memiliki data Tanah pada grafik kategori atau nilai
        $this->assertNotContains('Tanah (KIB A)*', $grafik['kategori']['labels']);
        $this->assertNotContains('Tanah (KIB A)*', $grafik['nilai']['labels']);

        // 2. Unit kerja yang muncul pada grafik unit HANYA unit kerja yang diizinkan untuk UPT tersebut
        foreach ($grafik['kondisi_unit']['labels'] as $unitLabel) {
            $this->assertStringContainsStringIgnoringCase('PSBP', $unitLabel);
            $this->assertStringNotContainsStringIgnoringCase('P2BTP', $unitLabel);
        }
    }

    /** 27. Skenario 8: Penanganan kondisi ketika data kosong (Zero Division Safe) */
    public function test_skenario_8_kondisi_ketika_data_kosong(): void
    {
        $admin = $this->userDenganRole('Admin Aset');
        $this->actingAs($admin);

        $service = app(LaporanEksekutifService::class);
        // Periode tahun 1990 yang belum ada data aset
        $res = $service->resolvePeriode('tahunan', 1990);
        $grafik = $service->kalkulasiGrafik($res['start_date'], $res['end_date'], $res['acuan'], null, 'tahunan');

        $this->assertSame(0, $grafik['kondisi']['total']);
        $this->assertSame([0.0, 0.0, 0.0], $grafik['kondisi']['persen']);
        $this->assertSame(0, $grafik['aktivitas']['total_pengadaan']);
        $this->assertSame(0, $grafik['aktivitas']['total_mutasi']);
        $this->assertIsArray($grafik['tren_pengadaan']['labels']);
    }

    /** 28. Skenario 9: Pemisahan data Tanah dari perhitungan aset bergerak */
    public function test_skenario_9_pemisahan_data_tanah(): void
    {
        $admin = $this->userDenganRole('Admin Aset');
        $this->actingAs($admin);

        Tanah::create([
            'no_excel' => 'TNH-TEST-'.uniqid(),
            'kib' => 'A',
            'tanggal_perolehan' => '2020-01-01',
            'nilai_perolehan' => 150000000,
            'deskripsi_objek' => 'Tanah Kebun Percobaan',
            'luas_tanah' => 5000,
            'alamat' => 'Jl. Kebun Uji Coba',
            'kondisi' => 'Baik',
        ]);

        $service = app(LaporanEksekutifService::class);
        $acuan = Carbon::parse('2026-12-31 23:59:59');
        $start = Carbon::parse('2026-01-01');
        $end = Carbon::parse('2026-12-31');

        $grafik = $service->kalkulasiGrafik($start, $end, $acuan);

        // Tanah terdaftar terpisah pada Grafik B (Distribusi Kategori)
        $this->assertContains('Tanah (KIB A)*', $grafik['kategori']['labels']);

        // Tanah TIDAK dimasukkan ke Grafik C (Nilai Perolehan vs Nilai Buku) karena berada di luar cakupan nilai buku Monitoring Aset
        $this->assertNotContains('Tanah (KIB A)*', $grafik['nilai']['labels']);
        $this->assertSame(['Barang Inventaris', 'Kendaraan Bermotor'], $grafik['nilai']['labels']);
    }

    /** 29. Skenario 10: Grafik aktivitas membandingkan pengadaan dan mutasi riil */
    public function test_skenario_10_grafik_aktivitas_pengadaan_vs_mutasi(): void
    {
        $admin = $this->userDenganRole('Admin Aset');
        $this->actingAs($admin);
        $lokasi = Lokasi::firstOrFail();

        // Buat aset baru yang diadakan pada Oktober 2026 (Minggu ke-2: Tgl 10)
        $asetBaru = Aset::create([
            'id_lokasi' => $lokasi->id_lokasi,
            'nomor_kartu_barang' => 'GRF-AKT-'.uniqid(),
            'tanggal_pengadaan' => '2026-10-10',
            'nilai_perolehan' => 2000000,
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);

        // Buat mutasi pada Oktober 2026 (Minggu ke-3: Tgl 18)
        $mutasi = \App\Models\MutasiAset::create([
            'tanggal_mutasi' => '2026-10-18',
            'jenis_mutasi' => 'ruangan',
            'status_mutasi' => 'selesai',
            'id_user_penginput' => $admin->id_user,
        ]);

        \App\Models\DetailMutasiAset::create([
            'id_mutasi' => $mutasi->id_mutasi,
            'id_aset' => $asetBaru->id_aset,
        ]);

        $service = app(LaporanEksekutifService::class);
        $res = $service->resolvePeriode('bulanan', 2026, 10);
        $grafik = $service->kalkulasiGrafik($res['start_date'], $res['end_date'], $res['acuan'], null, 'bulanan');

        $this->assertGreaterThanOrEqual(1, $grafik['aktivitas']['total_pengadaan']);
        $this->assertGreaterThanOrEqual(1, $grafik['aktivitas']['total_mutasi']);

        // Verifikasi rincian interval: Tgl 8-14 ada pengadaan, Tgl 15-21 ada mutasi
        $this->assertGreaterThanOrEqual(1, $grafik['aktivitas']['pengadaan'][1]); // Interval Tgl 8 - 14
        $this->assertGreaterThanOrEqual(1, $grafik['aktivitas']['mutasi'][2]); // Interval Tgl 15 - 21
    }

    /** 30. Controller integrasi: view laporan menerima data visualisasi grafik lengkap */
    public function test_controller_menyediakan_data_grafik_lengkap_ke_view(): void
    {
        $admin = $this->userDenganRole('Admin Aset');

        $response = $this->actingAs($admin)->get(route('laporan.index', [
            'jenis' => 'bulanan',
            'tahun' => 2026,
            'bulan' => 10,
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('grafik');

        /** @var array $grafik */
        $grafik = $response->viewData('grafik');

        $this->assertArrayHasKey('kondisi', $grafik);
        $this->assertArrayHasKey('kategori', $grafik);
        $this->assertArrayHasKey('nilai', $grafik);
        $this->assertArrayHasKey('tren_pengadaan', $grafik);
        $this->assertArrayHasKey('kondisi_unit', $grafik);
        $this->assertArrayHasKey('aktivitas', $grafik);

        // Pastikan view mengandung canvas elemen dan teks penanda tanpa drilldown
        $response->assertSee('id="chartKondisi"', false);
        $response->assertSee('id="chartKategori"', false);
        $response->assertSee('id="chartNilai"', false);
        $response->assertSee('id="chartTrenPengadaan"', false);
        $response->assertSee('id="chartKondisiUnit"', false);
        $response->assertSee('id="chartAktivitas"', false);
        $response->assertSee('Visual Informasi &bull; Tanpa Drilldown', false);

        // Verifikasi proporsi visual murni (tanpa minBarLength) dan kehadiran plugin label angka aktual
        $response->assertDontSee('minBarLength', false);
        $response->assertSee('topStackLabelsPlugin', false);

        // Verifikasi konfigurasi grouped stacked bar Grafik E (Legenda 2 dimensi dan mode hover groupedStack)
        $response->assertSee('Kelompok Batang:', false);
        $response->assertSee('Batang Kiri: Barang Inventaris', false);
        $response->assertSee('Batang Kanan: Kendaraan Bermotor', false);
        $response->assertSee('Warna Kondisi:', false);
        $response->assertSee('groupedStack', false);
        $response->assertSee("'Jenis Aset: ' + jenisAset", false);
        $response->assertSee("'Kondisi ' + kondisi + ': ' + ctx.raw + ' unit'", false);
    }

    /** 31. Perbaikan Transparansi: View menyajikan narasi kondisi fisik terkini dan catatan metodologi jujur */
    public function test_transparansi_narasi_kondisi_aset_dan_catatan_metodologi(): void
    {
        $admin = $this->userDenganRole('Admin Aset');

        $response = $this->actingAs($admin)->get(route('laporan.index'));

        $response->assertStatus(200);

        // Judul & Subtitle Grafik A dan E
        $response->assertSee('A. Distribusi Kondisi Aset', false);
        $response->assertSee('Kondisi Fisik Terkini pada Populasi Saldo Aset Periode Laporan', false);
        $response->assertSee('E. Kondisi Aset Berdasarkan Lokasi', false);
        $response->assertSee('Distribusi kondisi Barang Inventaris dan Kendaraan Bermotor berdasarkan lokasi fisik aset.', false);

        // Catatan Metodologi Transparansi Data
        $response->assertSee('Catatan Metodologi:', false);
        $response->assertSee('Grafik menyajikan kondisi fisik terkini berdasarkan data master aset dari populasi saldo aset pada periode laporan. Sistem belum mencatat histori perubahan kondisi fisik aset, sehingga kondisi yang ditampilkan tidak merepresentasikan kondisi fisik pada tanggal laporan historis.', false);
    }

    /** 32. Ketahanan Pemuatan: Script mengutamakan file lokal, fallback CDN, dan pesan informatif */
    public function test_pemuatan_chartjs_lokal_dan_pesan_fallback(): void
    {
        $admin = $this->userDenganRole('Admin Aset');

        $response = $this->actingAs($admin)->get(route('laporan.index'));

        $response->assertStatus(200);

        // 1. Memuat file lokal via asset()
        $response->assertSee(asset('vendor/chartjs/chart.umd.min.js'), false);

        // 2. Mengandung penanganan fallback CDN
        $response->assertSee('cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', false);

        // 3. Mengandung template pesan fallback kegagalan
        $response->assertSee('Visualisasi grafik tidak dapat dimuat.', false);
        $response->assertSee('Silakan periksa ketersediaan pustaka grafik atau hubungi administrator sistem.', false);

        // 4. Pastikan file fisik lokal benar-benar ada di filesystem public
        $this->assertFileExists(public_path('vendor/chartjs/chart.umd.min.js'));
        $this->assertGreaterThan(50000, filesize(public_path('vendor/chartjs/chart.umd.min.js')));
    }

    /** 33. Verifikasi interaksi hover groupedStack dan penyederhanaan legenda 3 warna pada Grafik E */
    public function test_grafik_e_interaksi_hover_grouped_stack_dan_legenda_tiga_warna(): void
    {
        $admin = $this->userDenganRole('Admin Aset');
        $this->actingAs($admin);

        $service = app(LaporanEksekutifService::class);
        $resNov = $service->resolvePeriode('bulanan', 2026, 11);
        $grafikNov = $service->kalkulasiGrafik(
            $resNov['start_date'],
            $resNov['end_date'],
            $resNov['acuan'],
            null,
            'bulanan'
        );

        $unitNov = $grafikNov['kondisi_unit'];
        $idxDinas = array_search('Kantor Dinas Perkebunan Provinsi Jawa Timur', $unitNov['labels'], true);
        $this->assertNotFalse($idxDinas);

        // Verifikasi data Kantor Dinas periode November 2026:
        // Barang Inventaris: 99 unit (atau 100 jika ada aset uji di transaction)
        $totalBarangDinas = $unitNov['barang_baik'][$idxDinas] + $unitNov['barang_rusak_ringan'][$idxDinas] + $unitNov['barang_rusak_berat'][$idxDinas];
        $this->assertGreaterThanOrEqual(99, $totalBarangDinas);

        // Kendaraan Bermotor: 2 unit (1 Baik, 1 Rusak Berat)
        $this->assertSame(1, $unitNov['kendaraan_baik'][$idxDinas]);
        $this->assertSame(0, $unitNov['kendaraan_rusak_ringan'][$idxDinas]);
        $this->assertSame(1, $unitNov['kendaraan_rusak_berat'][$idxDinas]);
        $totalKendaraanDinas = $unitNov['kendaraan_baik'][$idxDinas] + $unitNov['kendaraan_rusak_ringan'][$idxDinas] + $unitNov['kendaraan_rusak_berat'][$idxDinas];
        $this->assertSame(2, $totalKendaraanDinas);

        // Verifikasi view rendering
        $response = $this->get(route('laporan.index', ['periode' => 'bulanan', 'tahun' => 2026, 'bulan' => 11]));
        $response->assertStatus(200);

        // 1. Mode interaksi kustom groupedStack dengan intersect: false
        $response->assertSee('Chart.Interaction.modes.groupedStack', false);
        $response->assertSee("mode: 'groupedStack'", false);
        $response->assertSee('intersect: false', false);

        // 2. Format tooltip: Lokasi, Jenis Aset, Kondisi, Jumlah aktual, dan Total
        $response->assertSee("'Lokasi: ' + items[0].label", false);
        $response->assertSee("'Jenis Aset: ' + jenisAset", false);
        $response->assertSee("'Kondisi ' + kondisi + ': ' + ctx.raw + ' unit'", false);
        $response->assertSee("'Total: ' + total + ' unit'", false);

        // 3. Legenda warna kondisi HANYA memiliki 3 kategori (Baik, Rusak Ringan, Rusak Berat)
        $response->assertSee('Kelompok Batang:', false);
        $response->assertSee('Batang Kiri: Barang Inventaris', false);
        $response->assertSee('Batang Kanan: Kendaraan Bermotor', false);
        $response->assertSee('Warna Kondisi:', false);

        $content = $response->getContent();
        // Pastikan Belum Diisi TIDAK muncul di bagian legenda warna
        $legendaPos = strpos($content, 'Warna Kondisi:');
        $canvasPos = strpos($content, 'id="chartKondisiUnit"');
        $legendaSection = substr($content, $legendaPos, $canvasPos - $legendaPos);
        $this->assertStringNotContainsString('Belum Diisi', $legendaSection);
    }
}
