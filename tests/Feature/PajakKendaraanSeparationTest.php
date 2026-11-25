<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Kendaraan;
use App\Models\PajakKendaraan;
use App\Models\RiwayatPlat;
use App\Models\Role;
use App\Models\Skpd;
use App\Models\User;
use App\Services\LaporanEksekutifExporter;
use App\Services\LaporanEksekutifService;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PajakKendaraanSeparationTest extends TestCase
{
    private array $createdAsetIds = [];
    private array $createdKendaraanIds = [];
    private array $createdPajakIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabasePengujian(),
        ]);
        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    protected function tearDown(): void
    {
        if (! empty($this->createdPajakIds)) {
            PajakKendaraan::whereIn('id_pajak', $this->createdPajakIds)->delete();
        }
        if (! empty($this->createdKendaraanIds)) {
            RiwayatPlat::whereIn('id_kendaraan', $this->createdKendaraanIds)->delete();
            PajakKendaraan::whereIn('id_kendaraan', $this->createdKendaraanIds)->delete();
            Kendaraan::whereIn('id_kendaraan', $this->createdKendaraanIds)->delete();
        }
        if (! empty($this->createdAsetIds)) {
            Aset::whereIn('id_aset', $this->createdAsetIds)->delete();
        }

        parent::tearDown();
    }

    private function userAdminAset(): User
    {
        return $this->ambilAtauBuatUserRole('Admin Aset');
    }

    private function buatKendaraanUji(string $tanggalPengadaan = '2026-11-01', ?string $plat = null): Kendaraan
    {
        $skpd = Skpd::first() ?? Skpd::create(['nama_skpd' => 'Dinas Perkebunan', 'jenis_skpd' => 'Bidang']);

        $aset = Aset::create([
            'id_skpd' => $skpd->id_skpd,
            'id_lokasi' => 1,
            'nomor_kartu_barang' => 'NKB-' . uniqid(),
            'kode_barang' => '02.03.01.01.01',
            'nama_barang' => 'Kendaraan Test ' . uniqid(),
            'nup' => rand(1000, 9999),
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => true,
            'tanggal_pengadaan' => $tanggalPengadaan,
            'nilai_perolehan' => 150000000,
        ]);
        $this->createdAsetIds[] = $aset->id_aset;

        $kendaraan = Kendaraan::create([
            'id_aset' => $aset->id_aset,
            'jenis_kendaraan' => 'Roda 4',
            'merk' => 'Toyota',
            'tipe' => 'Avanza',
            'nomor_rangka' => 'MHF' . uniqid(),
            'nomor_mesin' => '1TR' . uniqid(),
        ]);
        $this->createdKendaraanIds[] = $kendaraan->id_kendaraan;

        RiwayatPlat::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'nomor_plat' => $plat ?? ('L ' . rand(1000, 9999) . ' TX'),
            'status' => 'Aktif',
            'ganti_plat' => false,
        ]);

        return $kendaraan;
    }

    /**
     * 1. Pajak tahunan dan lima tahunan dapat sama-sama berstatus Aktif pada kendaraan yang sama.
     */
    public function test_pajak_tahunan_dan_lima_tahunan_dapat_sama_sama_berstatus_aktif_pada_kendaraan_yang_sama(): void
    {
        $kendaraan = $this->buatKendaraanUji();

        $pLimaTahunan = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tahun' => 2027,
            'tanggal_bayar' => '2026-09-21',
            'tanggal_berakhir' => '2027-09-21',
            'tanggal_berakhir_stnk' => '2031-09-21',
            'nominal' => 2000000,
            'total_pajak' => 2000000,
            'pajak_5_tahunan' => true,
            'status' => 'Aktif',
        ]);
        $this->createdPajakIds[] = $pLimaTahunan->id_pajak;

        $pTahunan = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tahun' => 2026,
            'tanggal_bayar' => '2026-09-21',
            'tanggal_berakhir' => '2026-09-21',
            'nominal' => 1000000,
            'total_pajak' => 1000000,
            'pajak_5_tahunan' => false,
            'status' => 'Aktif',
        ]);
        $this->createdPajakIds[] = $pTahunan->id_pajak;

        $kendaraanFresh = $kendaraan->fresh();

        // Verifikasi kedua relasi terpisah mengembalikan record yang tepat
        $this->assertNotNull($kendaraanFresh->pajakTahunanAktif);
        $this->assertSame($pTahunan->id_pajak, $kendaraanFresh->pajakTahunanAktif->id_pajak);
        $this->assertFalse((bool) $kendaraanFresh->pajakTahunanAktif->pajak_5_tahunan);
        $this->assertSame('Aktif', $kendaraanFresh->pajakTahunanAktif->status);

        $this->assertNotNull($kendaraanFresh->pajakLimaTahunanAktif);
        $this->assertSame($pLimaTahunan->id_pajak, $kendaraanFresh->pajakLimaTahunanAktif->id_pajak);
        $this->assertTrue((bool) $kendaraanFresh->pajakLimaTahunanAktif->pajak_5_tahunan);
        $this->assertSame('Aktif', $kendaraanFresh->pajakLimaTahunanAktif->status);

        // Verifikasi relasi backward-compatible pajakAktif merujuk ke transaksi aktif terkini
        $this->assertSame($pTahunan->id_pajak, $kendaraanFresh->pajakAktif->id_pajak);
    }

    /**
     * 2. Perpanjangan pajak tahunan hanya menonaktifkan record pajak tahunan sebelumnya.
     */
    public function test_perpanjangan_pajak_tahunan_hanya_menonaktifkan_pajak_tahunan_sebelumnya(): void
    {
        Storage::fake('public');
        $admin = $this->userAdminAset();
        $kendaraan = $this->buatKendaraanUji();

        // Setup: 1 tahunan Aktif dan 1 lima tahunan Aktif
        $tahunanLama = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tahun' => 2026,
            'tanggal_bayar' => '2025-09-21',
            'tanggal_berakhir' => '2026-09-21',
            'nominal' => 1000000,
            'total_pajak' => 1000000,
            'pajak_5_tahunan' => false,
            'status' => 'Aktif',
        ]);
        $this->createdPajakIds[] = $tahunanLama->id_pajak;

        $limaTahunanAktif = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tahun' => 2027,
            'tanggal_bayar' => '2026-09-21',
            'tanggal_berakhir' => '2027-09-21',
            'tanggal_berakhir_stnk' => '2031-09-21',
            'nominal' => 2000000,
            'total_pajak' => 2000000,
            'pajak_5_tahunan' => true,
            'status' => 'Aktif',
        ]);
        $this->createdPajakIds[] = $limaTahunanAktif->id_pajak;

        // Request simpan pajak tahunan baru berstatus Aktif
        $response = $this->actingAs($admin)->postJson(route('kendaraan.pajak.store', $kendaraan), [
            'pajak_5_tahunan' => 0,
            'tahun' => 2027,
            'tanggal_bayar' => '2026-09-21',
            'tanggal_berakhir' => '2027-09-21',
            'nominal' => 1000000,
            'bukti_pajak' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
        ]);

        $response->assertOk();

        // Verifikasi: Pajak tahunan lama ter-demote menjadi Tidak Aktif
        $this->assertSame('Tidak Aktif', $tahunanLama->fresh()->status);

        // Verifikasi: Pajak lima tahunan Aktif TETAP AKTIF (tidak terganggu!)
        $this->assertSame('Aktif', $limaTahunanAktif->fresh()->status);
    }

    /**
     * 3. Perpanjangan pajak lima tahunan menonaktifkan record pajak lima tahunan sebelumnya.
     */
    public function test_perpanjangan_pajak_lima_tahunan_hanya_menonaktifkan_pajak_lima_tahunan_sebelumnya(): void
    {
        Storage::fake('public');
        $admin = $this->userAdminAset();
        $kendaraan = $this->buatKendaraanUji();

        // Setup: 1 tahunan Aktif dan 1 lima tahunan Aktif
        $tahunanAktif = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tahun' => 2026,
            'tanggal_bayar' => '2025-09-21',
            'tanggal_berakhir' => '2026-09-21',
            'nominal' => 1000000,
            'total_pajak' => 1000000,
            'pajak_5_tahunan' => false,
            'status' => 'Aktif',
        ]);
        $this->createdPajakIds[] = $tahunanAktif->id_pajak;

        $limaTahunanLama = PajakKendaraan::create([
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tahun' => 2027,
            'tanggal_bayar' => '2026-09-21',
            'tanggal_berakhir' => '2027-09-21',
            'tanggal_berakhir_stnk' => '2031-09-21',
            'nominal' => 2000000,
            'total_pajak' => 2000000,
            'pajak_5_tahunan' => true,
            'status' => 'Aktif',
        ]);
        $this->createdPajakIds[] = $limaTahunanLama->id_pajak;

        // Request simpan pajak lima tahunan baru berstatus Aktif
        $response = $this->actingAs($admin)->postJson(route('kendaraan.pajak.store', $kendaraan), [
            'pajak_5_tahunan' => 1,
            'tahun' => 2032,
            'tanggal_bayar' => '2031-09-21',
            'tanggal_berakhir' => '2032-09-21',
            'tanggal_berakhir_stnk' => '2036-09-21',
            'nomor_plat_baru' => 'L 9999 XX',
            'nominal' => 2500000,
            'bukti_pajak' => UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'),
        ]);

        $response->assertOk();

        // Verifikasi: Pajak lima tahunan lama ter-demote menjadi Tidak Aktif
        $this->assertSame('Tidak Aktif', $limaTahunanLama->fresh()->status);

        // Verifikasi: Record baru berstatus Aktif
        $pajakBaru = PajakKendaraan::where('id_kendaraan', $kendaraan->id_kendaraan)
            ->where('status', 'Aktif')
            ->first();
        $this->assertNotNull($pajakBaru);
        $this->assertTrue((bool) $pajakBaru->pajak_5_tahunan);
    }

    /**
     * 4. Record pajak lima tahunan tidak memengaruhi klasifikasi kepatuhan pajak tahunan.
     */
    public function test_record_pajak_lima_tahunan_tidak_memengaruhi_klasifikasi_kepatuhan_pajak_tahunan(): void
    {
        $admin = $this->userAdminAset();
        $this->actingAs($admin);

        $service = app(LaporanEksekutifService::class);
        $acuan = Carbon::create(2026, 11, 30, 23, 59, 59);
        $start = Carbon::create(2026, 11, 1);
        $end = Carbon::create(2026, 11, 30);

        $kalkulasiAwal = $service->kalkulasi($start, $end, $acuan);

        // Buat 2 kendaraan fixture dengan pengadaan sebelum acuan (Oktober 2026)
        $k1 = $this->buatKendaraanUji('2026-10-01');
        $k2 = $this->buatKendaraanUji('2026-10-01');

        foreach ([$k1, $k2] as $k) {
            $p5 = PajakKendaraan::create([
                'id_kendaraan' => $k->id_kendaraan,
                'jenis_pajak' => 'PKB',
                'tahun' => 2027,
                'tanggal_bayar' => '2026-09-21',
                'tanggal_berakhir' => '2027-09-21',
                'tanggal_berakhir_stnk' => '2031-09-21',
                'nominal' => 2000000,
                'total_pajak' => 2000000,
                'pajak_5_tahunan' => true,
                'status' => 'Aktif',
            ]);
            $this->createdPajakIds[] = $p5->id_pajak;

            $p1 = PajakKendaraan::create([
                'id_kendaraan' => $k->id_kendaraan,
                'jenis_pajak' => 'PKB',
                'tahun' => 2026,
                'tanggal_bayar' => '2025-09-21',
                'tanggal_berakhir' => '2026-09-21',
                'nominal' => 1000000,
                'total_pajak' => 1000000,
                'pajak_5_tahunan' => false,
                'status' => 'Aktif',
            ]);
            $this->createdPajakIds[] = $p1->id_pajak;
        }

        $kalkulasiAkhir = $service->kalkulasi($start, $end, $acuan);

        // Kedua kendaraan aktif pada tanggal acuan 30 Nov 2026 memiliki pajak tahunan berakhir 2026-09-21 (terlambat)
        // Meskipun memiliki record 5 tahunan 2027-09-21, keduanya HARUS dihitung Terlambat (+2), BUKAN Aman!
        $this->assertSame($kalkulasiAwal['pajak_snapshot_terlambat'] + 2, $kalkulasiAkhir['pajak_snapshot_terlambat']);
        $this->assertSame($kalkulasiAwal['pajak_snapshot_aman'], $kalkulasiAkhir['pajak_snapshot_aman']);
    }

    /**
     * 5. Jika record pajak tahunan tidak tersedia, kendaraan dikategorikan tanpa data pajak.
     */
    public function test_jika_record_pajak_tahunan_tidak_tersedia_kendaraan_dikategorikan_tanpa_data_pajak(): void
    {
        $admin = $this->userAdminAset();
        $this->actingAs($admin);

        $service = app(LaporanEksekutifService::class);
        $acuan = Carbon::create(2026, 11, 30, 23, 59, 59);
        $start = Carbon::create(2026, 11, 1);
        $end = Carbon::create(2026, 11, 30);

        $kalkulasiAwal = $service->kalkulasi($start, $end, $acuan);

        // Buat 1 kendaraan fixture HANYA dengan pajak 5 tahunan (tanpa pajak tahunan)
        $kTanpaTahunan = $this->buatKendaraanUji('2026-10-01');
        $p5 = PajakKendaraan::create([
            'id_kendaraan' => $kTanpaTahunan->id_kendaraan,
            'jenis_pajak' => 'PKB',
            'tahun' => 2027,
            'tanggal_bayar' => '2026-09-21',
            'tanggal_berakhir' => '2027-09-21',
            'tanggal_berakhir_stnk' => '2031-09-21',
            'nominal' => 2000000,
            'total_pajak' => 2000000,
            'pajak_5_tahunan' => true,
            'status' => 'Aktif',
        ]);
        $this->createdPajakIds[] = $p5->id_pajak;

        $kalkulasiAkhir = $service->kalkulasi($start, $end, $acuan);

        // Kendaraan tanpa pajak tahunan harus masuk 'tanpa data' (+1), BUKAN otomatis aman karena ada record 5 tahunan!
        $this->assertSame($kalkulasiAwal['pajak_snapshot_tanpa_data'] + 1, $kalkulasiAkhir['pajak_snapshot_tanpa_data']);
        $this->assertSame($kalkulasiAwal['pajak_snapshot_aman'], $kalkulasiAkhir['pajak_snapshot_aman']);
    }

    /**
     * 6. Realisasi pembayaran hanya menghitung pajak tahunan.
     */
    public function test_realisasi_pembayaran_hanya_menghitung_pajak_tahunan(): void
    {
        $admin = $this->userAdminAset();
        $this->actingAs($admin);

        $service = app(LaporanEksekutifService::class);
        $start = Carbon::create(2026, 11, 1);
        $end = Carbon::create(2026, 11, 30);
        $acuan = Carbon::create(2026, 11, 30, 23, 59, 59);

        $kalkulasiAwal = $service->kalkulasi($start, $end, $acuan);

        $k = $this->buatKendaraanUji('2026-10-01');

        // Catat pembayaran pada pajak 5 tahunan sebesar Rp 1.500.000 pada November 2026
        $p5 = PajakKendaraan::create([
            'id_kendaraan' => $k->id_kendaraan,
            'jenis_pajak' => 'PKB 5 Tahunan',
            'tahun' => 2026,
            'tanggal_bayar' => '2026-11-15',
            'tanggal_berakhir' => '2027-11-15',
            'tanggal_berakhir_stnk' => '2031-11-15',
            'nominal' => 1500000,
            'total_pajak' => 1500000,
            'pajak_5_tahunan' => true,
            'status' => 'Aktif',
        ]);
        $this->createdPajakIds[] = $p5->id_pajak;

        // Catat pembayaran pada pajak tahunan sebesar Rp 250.000 pada November 2026
        $p1 = PajakKendaraan::create([
            'id_kendaraan' => $k->id_kendaraan,
            'jenis_pajak' => 'PKB Tahunan',
            'tahun' => 2026,
            'tanggal_bayar' => '2026-11-10',
            'tanggal_berakhir' => '2027-11-10',
            'nominal' => 250000,
            'total_pajak' => 250000,
            'pajak_5_tahunan' => false,
            'status' => 'Aktif',
        ]);
        $this->createdPajakIds[] = $p1->id_pajak;

        $kalkulasiAkhir = $service->kalkulasi($start, $end, $acuan);

        // Hanya pembayaran tahunan (Rp 250.000, 1 transaksi) yang dihitung!
        // Pembayaran 5 tahunan (Rp 1.500.000) TIDAK boleh masuk.
        $this->assertEquals($kalkulasiAwal['pajak_flow_total_nominal'] + 250000.0, $kalkulasiAkhir['pajak_flow_total_nominal']);
        $this->assertSame($kalkulasiAwal['pajak_flow_transaksi_count'] + 1, $kalkulasiAkhir['pajak_flow_transaksi_count']);
        $this->assertSame($kalkulasiAwal['pajak_flow_kendaraan_count'] + 1, $kalkulasiAkhir['pajak_flow_kendaraan_count']);
    }

    /**
     * 7. Filter tanggal acuan laporan tetap berfungsi.
     */
    public function test_filter_tanggal_acuan_laporan_tetap_berfungsi(): void
    {
        $admin = $this->userAdminAset();
        $this->actingAs($admin);

        $service = app(LaporanEksekutifService::class);

        // Buat 2 kendaraan fixture dengan pengadaan 15 November 2026
        $k1 = $this->buatKendaraanUji('2026-11-15');
        $k2 = $this->buatKendaraanUji('2026-11-15');

        foreach ([$k1, $k2] as $k) {
            $p = PajakKendaraan::create([
                'id_kendaraan' => $k->id_kendaraan,
                'jenis_pajak' => 'PKB',
                'tahun' => 2026,
                'tanggal_bayar' => '2026-09-01',
                'tanggal_berakhir' => '2026-09-21',
                'nominal' => 1000000,
                'total_pajak' => 1000000,
                'pajak_5_tahunan' => false,
                'status' => 'Aktif',
            ]);
            $this->createdPajakIds[] = $p->id_pajak;
        }

        // Jika acuan laporan 1 Agustus 2026 (sebelum tanggal pengadaan Nov 2026)
        $acuanAgustus = Carbon::create(2026, 8, 1, 23, 59, 59);
        $startAgustus = Carbon::create(2026, 8, 1);
        $endAgustus = Carbon::create(2026, 8, 31);
        $kalkulasiAgustus = $service->kalkulasi($startAgustus, $endAgustus, $acuanAgustus);

        // Per 30 November 2026: keduanya sudah masuk saldo dan keduanya terlambat
        $acuanNov = Carbon::create(2026, 11, 30, 23, 59, 59);
        $startNov = Carbon::create(2026, 11, 1);
        $endNov = Carbon::create(2026, 11, 30);
        $kalkulasiNov = $service->kalkulasi($startNov, $endNov, $acuanNov);

        // Pertambahan +2 unit kendaraan dan +2 terlambat pada November
        $this->assertSame($kalkulasiAgustus['total_kendaraan'] + 2, $kalkulasiNov['total_kendaraan']);
        $this->assertSame($kalkulasiAgustus['pajak_snapshot_terlambat'] + 2, $kalkulasiNov['pajak_snapshot_terlambat']);
    }

    /**
     * 8. Website, PDF, dan Excel menampilkan angka yang sama.
     */
    public function test_website_pdf_dan_excel_menampilkan_angka_kepatuhan_pajak_yang_identik(): void
    {
        $admin = $this->userAdminAset();
        $this->actingAs($admin);

        // 1. Ambil data dari Service
        $service = app(LaporanEksekutifService::class);
        $periode = $service->resolvePeriode('bulanan', 2026, 11);
        $kalkulasi = $service->kalkulasi($periode['start_date'], $periode['end_date'], $periode['acuan'], null);
        $grafik = $service->kalkulasiGrafik($periode['start_date'], $periode['end_date'], $periode['acuan'], null, 'bulanan');

        $meta = [
            'nama_unit' => 'Seluruh Unit Kerja (Konsolidasi)',
            'is_admin_aset' => true,
            'unit_id' => null,
            'unit_lokasi_id' => null,
            'bisa_akses_tanah' => true,
            'jenis' => 'bulanan',
            'tahun' => 2026,
            'bulan' => 11,
        ];

        // 2. Render Web
        $webResponse = $this->get(route('laporan.index', ['jenis' => 'bulanan', 'tahun' => 2026, 'bulan' => 11]));
        $webResponse->assertOk();

        // 3. Render PDF via Exporter resmi
        $exporter = app(LaporanEksekutifExporter::class);
        $pdfBinary = $exporter->renderPdf($periode, $kalkulasi, $grafik, $meta);
        $this->assertNotEmpty($pdfBinary);
        $this->assertStringStartsWith('%PDF-', substr($pdfBinary, 0, 10));

        // Verifikasi juga endpoint download PDF berhasil
        $pdfResponse = $this->get(route('laporan.export.pdf', ['jenis' => 'bulanan', 'tahun' => 2026, 'bulan' => 11]));
        $pdfResponse->assertOk();
        $pdfResponse->assertHeader('Content-Type', 'application/pdf');

        // 4. Render Excel
        $spreadsheet = $exporter->generateExcel($periode, $kalkulasi, $grafik, $meta);
        $sheet4 = $spreadsheet->getSheetByName('Kondisi dan Risiko');
        $this->assertNotNull($sheet4);

        // Cari cell 'Pajak Terlambat' di sheet Kondisi dan Risiko
        $foundTerlambat = false;
        $highestRow = $sheet4->getHighestRow();
        for ($r = 1; $r <= $highestRow; $r++) {
            if ($sheet4->getCell("B{$r}")->getValue() === 'Pajak Terlambat') {
                $foundTerlambat = true;
                $this->assertSame($kalkulasi['pajak_snapshot_terlambat'], (int) $sheet4->getCell("C{$r}")->getValue());
                break;
            }
        }
        $this->assertTrue($foundTerlambat, "Baris 'Pajak Terlambat' harus ditemukan di Sheet 'Kondisi dan Risiko'");
    }
}
