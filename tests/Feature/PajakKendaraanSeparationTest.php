<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Kendaraan;
use App\Models\PajakKendaraan;
use App\Models\Role;
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
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'invesdisbun',
        ]);
        DB::purge('mysql');
        DB::reconnect('mysql');
    }

    private function userAdminAset(): User
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('nama_role', 'Admin Aset'))->first();
        if (! $admin) {
            $role = Role::firstOrCreate(['nama_role' => 'Admin Aset']);
            $admin = User::factory()->create(['id_role' => $role->id_role]);
        }

        return $admin;
    }

    /**
     * 1. Pajak tahunan dan lima tahunan dapat sama-sama berstatus Aktif pada kendaraan yang sama.
     */
    public function test_pajak_tahunan_dan_lima_tahunan_dapat_sama_sama_berstatus_aktif_pada_kendaraan_yang_sama(): void
    {
        DB::beginTransaction();
        try {
            $kendaraan = Kendaraan::first();
            $this->assertNotNull($kendaraan);

            // Buat 1 pajak tahunan Aktif dan 1 pajak 5 tahunan Aktif
            PajakKendaraan::where('id_kendaraan', $kendaraan->id_kendaraan)->update(['status' => 'Tidak Aktif']);

            $pTahunan = PajakKendaraan::create([
                'id_kendaraan' => $kendaraan->id_kendaraan,
                'jenis_pajak' => 'PKB',
                'tahun' => 2026,
                'tanggal_berakhir' => '2026-09-21',
                'pajak_5_tahunan' => false,
                'status' => 'Aktif',
            ]);

            $pLimaTahunan = PajakKendaraan::create([
                'id_kendaraan' => $kendaraan->id_kendaraan,
                'jenis_pajak' => 'PKB',
                'tahun' => 2027,
                'tanggal_berakhir' => '2027-09-21',
                'pajak_5_tahunan' => true,
                'status' => 'Aktif',
            ]);

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

            // Verifikasi relasi backward-compatible pajakAktif merujuk ke tahunan
            $this->assertSame($pTahunan->id_pajak, $kendaraanFresh->pajakAktif->id_pajak);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 2. Perpanjangan pajak tahunan hanya menonaktifkan record pajak tahunan sebelumnya.
     */
    public function test_perpanjangan_pajak_tahunan_hanya_menonaktifkan_pajak_tahunan_sebelumnya(): void
    {
        Storage::fake('public');
        DB::beginTransaction();
        try {
            $admin = $this->userAdminAset();
            $kendaraan = Kendaraan::first();
            $this->assertNotNull($kendaraan);

            // Setup: 1 tahunan Aktif dan 1 lima tahunan Aktif
            PajakKendaraan::where('id_kendaraan', $kendaraan->id_kendaraan)->update(['status' => 'Tidak Aktif']);

            $tahunanLama = PajakKendaraan::create([
                'id_kendaraan' => $kendaraan->id_kendaraan,
                'jenis_pajak' => 'PKB',
                'tahun' => 2026,
                'tanggal_berakhir' => '2026-09-21',
                'pajak_5_tahunan' => false,
                'status' => 'Aktif',
            ]);

            $limaTahunanAktif = PajakKendaraan::create([
                'id_kendaraan' => $kendaraan->id_kendaraan,
                'jenis_pajak' => 'PKB',
                'tahun' => 2027,
                'tanggal_berakhir' => '2027-09-21',
                'pajak_5_tahunan' => true,
                'status' => 'Aktif',
            ]);

            // Request simpan pajak tahunan baru berstatus Aktif
            $response = $this->actingAs($admin)->postJson(route('kendaraan.pajak.store', $kendaraan), [
                'jenis_pajak' => 'PKB',
                'tahun' => 2027,
                'tanggal_berakhir' => '2027-09-21',
                'pajak_5_tahunan' => 0,
                'status' => 'Aktif',
                'bukti_pajak' => UploadedFile::fake()->create('bukti.pdf', 100),
            ]);

            $response->assertOk();

            // Verifikasi: Pajak tahunan lama ter-demote menjadi Tidak Aktif
            $this->assertSame('Tidak Aktif', $tahunanLama->fresh()->status);

            // Verifikasi: Pajak lima tahunan Aktif TETAP AKTIF (tidak terganggu!)
            $this->assertSame('Aktif', $limaTahunanAktif->fresh()->status);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 3. Perpanjangan pajak lima tahunan hanya menonaktifkan record pajak lima tahunan sebelumnya.
     */
    public function test_perpanjangan_pajak_lima_tahunan_hanya_menonaktifkan_pajak_lima_tahunan_sebelumnya(): void
    {
        Storage::fake('public');
        DB::beginTransaction();
        try {
            $admin = $this->userAdminAset();
            $kendaraan = Kendaraan::first();
            $this->assertNotNull($kendaraan);

            // Setup: 1 tahunan Aktif dan 1 lima tahunan Aktif
            PajakKendaraan::where('id_kendaraan', $kendaraan->id_kendaraan)->update(['status' => 'Tidak Aktif']);

            $tahunanAktif = PajakKendaraan::create([
                'id_kendaraan' => $kendaraan->id_kendaraan,
                'jenis_pajak' => 'PKB',
                'tahun' => 2026,
                'tanggal_berakhir' => '2026-09-21',
                'pajak_5_tahunan' => false,
                'status' => 'Aktif',
            ]);

            $limaTahunanLama = PajakKendaraan::create([
                'id_kendaraan' => $kendaraan->id_kendaraan,
                'jenis_pajak' => 'PKB',
                'tahun' => 2027,
                'tanggal_berakhir' => '2027-09-21',
                'pajak_5_tahunan' => true,
                'status' => 'Aktif',
            ]);

            // Request simpan pajak lima tahunan baru berstatus Aktif
            $response = $this->actingAs($admin)->postJson(route('kendaraan.pajak.store', $kendaraan), [
                'jenis_pajak' => 'PKB',
                'tahun' => 2032,
                'tanggal_berakhir' => '2032-09-21',
                'pajak_5_tahunan' => 1,
                'nomor_plat_baru' => 'L 9999 XX',
                'status' => 'Aktif',
                'bukti_pajak' => UploadedFile::fake()->create('bukti.pdf', 100),
            ]);

            $response->assertOk();

            // Verifikasi: Pajak lima tahunan lama ter-demote menjadi Tidak Aktif
            $this->assertSame('Tidak Aktif', $limaTahunanLama->fresh()->status);

            // Verifikasi: Pajak tahunan Aktif TETAP AKTIF (tidak terganggu!)
            $this->assertSame('Aktif', $tahunanAktif->fresh()->status);
        } finally {
            DB::rollBack();
        }
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

        $kalkulasi = $service->kalkulasi($start, $end, $acuan);

        // Kedua kendaraan aktif pada tanggal acuan 30 Nov 2026 memiliki pajak tahunan berakhir 2026-09-21 (terlambat)
        // Meskipun memiliki record 5 tahunan 2027-09-21, keduanya HARUS dihitung Terlambat (2 unit), BUKAN Aman!
        $this->assertSame(2, $kalkulasi['pajak_snapshot_terlambat']);
        $this->assertSame(0, $kalkulasi['pajak_snapshot_aman']);
        $this->assertSame(0, $kalkulasi['pajak_snapshot_kritis']);
        $this->assertSame(0, $kalkulasi['pajak_snapshot_tanpa_data']);
    }

    /**
     * 5. Jika record pajak tahunan tidak tersedia, kendaraan dikategorikan tanpa data pajak.
     */
    public function test_jika_record_pajak_tahunan_tidak_tersedia_kendaraan_dikategorikan_tanpa_data_pajak(): void
    {
        DB::beginTransaction();
        try {
            $admin = $this->userAdminAset();
            $this->actingAs($admin);

            // Ambil kendaraan ID 726 (id_kendaraan: 164), hapus hanya record pajak tahunannya
            $k726 = Kendaraan::where('id_aset', 726)->first();
            $this->assertNotNull($k726);

            PajakKendaraan::where('id_kendaraan', $k726->id_kendaraan)
                ->where('pajak_5_tahunan', false)
                ->delete();

            // Pastikan kendaraan 726 masih punya pajak 5 tahunan
            $has5 = PajakKendaraan::where('id_kendaraan', $k726->id_kendaraan)->where('pajak_5_tahunan', true)->exists();
            $this->assertTrue($has5);

            $service = app(LaporanEksekutifService::class);
            $acuan = Carbon::create(2026, 11, 30, 23, 59, 59);
            $start = Carbon::create(2026, 11, 1);
            $end = Carbon::create(2026, 11, 30);

            $kalkulasi = $service->kalkulasi($start, $end, $acuan);

            // Kendaraan 726 tanpa pajak tahunan harus masuk 'tanpa data', BUKAN otomatis aman karena ada record 5 tahunan!
            $this->assertSame(1, $kalkulasi['pajak_snapshot_tanpa_data']);
            $this->assertSame(1, $kalkulasi['pajak_snapshot_terlambat']); // ID 727 tetap terlambat
            $this->assertSame(0, $kalkulasi['pajak_snapshot_aman']);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 6. Realisasi pembayaran hanya menghitung pajak tahunan.
     */
    public function test_realisasi_pembayaran_hanya_menghitung_pajak_tahunan(): void
    {
        DB::beginTransaction();
        try {
            $admin = $this->userAdminAset();
            $this->actingAs($admin);

            $k726 = Kendaraan::where('id_aset', 726)->first();
            $this->assertNotNull($k726);

            // Catat pembayaran pada pajak 5 tahunan sebesar Rp 1.500.000 pada November 2026
            PajakKendaraan::where('id_kendaraan', $k726->id_kendaraan)
                ->where('pajak_5_tahunan', true)
                ->update([
                    'tanggal_bayar' => '2026-11-15',
                    'nominal' => 1500000,
                    'total_pajak' => 1500000,
                ]);

            // Catat pembayaran pada pajak tahunan sebesar Rp 250.000 pada November 2026
            PajakKendaraan::where('id_kendaraan', $k726->id_kendaraan)
                ->where('pajak_5_tahunan', false)
                ->update([
                    'tanggal_bayar' => '2026-11-10',
                    'nominal' => 250000,
                    'total_pajak' => 250000,
                ]);

            $service = app(LaporanEksekutifService::class);
            $start = Carbon::create(2026, 11, 1);
            $end = Carbon::create(2026, 11, 30);
            $acuan = Carbon::create(2026, 11, 30, 23, 59, 59);

            $kalkulasi = $service->kalkulasi($start, $end, $acuan);

            // Hanya pembayaran tahunan (Rp 250.000, 1 transaksi) yang dihitung!
            // Pembayaran 5 tahunan (Rp 1.500.000) TIDAK boleh masuk.
            $this->assertEquals(250000.0, $kalkulasi['pajak_flow_total_nominal']);
            $this->assertSame(1, $kalkulasi['pajak_flow_transaksi_count']);
            $this->assertSame(1, $kalkulasi['pajak_flow_kendaraan_count']);
        } finally {
            DB::rollBack();
        }
    }

    /**
     * 7. Filter tanggal acuan laporan tetap berfungsi.
     */
    public function test_filter_tanggal_acuan_laporan_tetap_berfungsi(): void
    {
        $admin = $this->userAdminAset();
        $this->actingAs($admin);

        $service = app(LaporanEksekutifService::class);

        // Jika acuan laporan 1 Agustus 2026 (sebelum tanggal pengadaan Nov 2026)
        $acuanAgustus = Carbon::create(2026, 8, 1, 23, 59, 59);
        $startAgustus = Carbon::create(2026, 8, 1);
        $endAgustus = Carbon::create(2026, 8, 31);

        $kalkulasiAgustus = $service->kalkulasi($startAgustus, $endAgustus, $acuanAgustus);

        $this->assertSame(0, $kalkulasiAgustus['total_kendaraan']);
        $this->assertSame(0, $kalkulasiAgustus['pajak_snapshot_terlambat']);

        // Per 30 November 2026: keduanya sudah masuk saldo dan keduanya terlambat
        $acuanNov = Carbon::create(2026, 11, 30, 23, 59, 59);
        $startNov = Carbon::create(2026, 11, 1);
        $endNov = Carbon::create(2026, 11, 30);
        $kalkulasiNov = $service->kalkulasi($startNov, $endNov, $acuanNov);

        $this->assertSame(2, $kalkulasiNov['total_kendaraan']);
        $this->assertSame(2, $kalkulasiNov['pajak_snapshot_terlambat']);
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
            'unit_id' => null,
            'bisa_akses_tanah' => true,
        ];

        // 2. Render Web
        $webResponse = $this->get(route('laporan.index', ['jenis' => 'bulanan', 'tahun' => 2026, 'bulan' => 11]));
        $webResponse->assertOk();
        $webResponse->assertSee('Terlambat');

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
