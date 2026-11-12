<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\Role;
use App\Models\Skpd;
use App\Models\User;
use App\Services\LaporanEksekutifService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class LaporanEksekutifExportTest extends TestCase
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

    private function userDenganRole(string $role): User
    {
        return User::whereHas('role', fn ($q) => $q->where('nama_role', $role))->firstOrFail();
    }

    private function adminUpt(string $namaSkpd): User
    {
        $skpd = Skpd::where('nama_skpd', $namaSkpd)->firstOrFail();
        $pegawai = Pegawai::create([
            'nip' => 'TST' . uniqid(),
            'nama_pegawai' => 'Admin ' . $namaSkpd . ' Test',
            'id_skpd' => $skpd->id_skpd,
        ]);

        return User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'id_role' => Role::where('nama_role', 'Admin UPT')->firstOrFail()->id_role,
            'username' => 'tst_upt_' . uniqid(),
            'password' => 'rahasia',
            'status_user' => 'aktif',
        ]);
    }

    public function test_guest_tidak_dapat_mengakses_ekspor_excel_dan_pdf(): void
    {
        $respExcel = $this->get('/laporan/export/excel');
        $respExcel->assertRedirect('/login');

        $respPdf = $this->get('/laporan/export/pdf');
        $respPdf->assertRedirect('/login');
    }

    public function test_admin_aset_dapat_mengunduh_excel_laporan_bulanan_berisi_6_sheet(): void
    {
        $this->actingAs($this->userDenganRole('Admin Aset'));

        $response = $this->get('/laporan/export/excel?jenis=bulanan&tahun=2026&bulan=11');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $contentDisposition = $response->headers->get('Content-Disposition');
        $this->assertNotNull($contentDisposition);
        $this->assertStringContainsString('Laporan_Eksekutif_BMD_November_2026', $contentDisposition);
        $this->assertStringContainsString('.xlsx', $contentDisposition);

        // Baca binary spreadsheet langsung dari output stream
        ob_start();
        $response->sendContent();
        $binary = ob_get_clean();

        $this->assertNotEmpty($binary);

        $tempFile = tempnam(sys_get_temp_dir(), 'inv_xls_');
        file_put_contents($tempFile, $binary);

        try {
            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load($tempFile);

            $this->assertEquals(6, $spreadsheet->getSheetCount(), 'Excel laporan eksekutif harus memiliki tepat 6 sheet.');

            $expectedSheets = [
                'Ringkasan Eksekutif',
                'Distribusi Aset',
                'Nilai dan Penyusutan',
                'Kondisi dan Risiko',
                'Lokasi dan Penempatan',
                'Aktivitas dan Pajak',
            ];

            $actualSheets = $spreadsheet->getSheetNames();
            $this->assertEquals($expectedSheets, $actualSheets);

            // Periksa Sheet 1 memiliki judul Pemprov Jatim dan periode
            $s1 = $spreadsheet->getSheetByName('Ringkasan Eksekutif');
            $this->assertStringContainsString('PEMERINTAH PROVINSI JAWA TIMUR', (string) $s1->getCell('A1')->getValue());
            $this->assertStringContainsString('November 2026', (string) $s1->getCell('B5')->getValue());
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    public function test_data_excel_identik_dengan_kalkulasi_laporan_eksekutif_service(): void
    {
        $this->actingAs($this->userDenganRole('Admin Aset'));

        $service = app(LaporanEksekutifService::class);
        $periode = $service->resolvePeriode('bulanan', 2026, 11);
        $k = $service->kalkulasi($periode['start_date'], $periode['end_date'], $periode['acuan'], null);

        $response = $this->get('/laporan/export/excel?jenis=bulanan&tahun=2026&bulan=11');
        $response->assertStatus(200);

        ob_start();
        $response->sendContent();
        $binary = ob_get_clean();

        $tempFile = tempnam(sys_get_temp_dir(), 'inv_xls_kpi_');
        file_put_contents($tempFile, $binary);

        try {
            $reader = IOFactory::createReader('Xlsx');
            $spreadsheet = $reader->load($tempFile);

            $s1 = $spreadsheet->getSheetByName('Ringkasan Eksekutif');

            // Baris data pertama (Row 11): Total Aset Bergerak (Barang & Kendaraan)
            // Kolom C adalah Jumlah Fisik, Kolom E adalah Nilai Finansial
            $totalFisikExcel = (int) $s1->getCell('C11')->getValue();
            $totalNilaiExcel = (float) $s1->getCell('E11')->getValue();

            $this->assertEquals($k['total_barang_dan_kendaraan'], $totalFisikExcel, 'Total aset bergerak di Excel harus identik dengan Service');
            $this->assertEquals($k['total_nilai_perolehan_aset'], $totalNilaiExcel, 'Total perolehan aset bergerak di Excel harus identik dengan Service');
        } finally {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    public function test_admin_aset_dapat_mengunduh_pdf_resmi_a4_landscape(): void
    {
        $this->actingAs($this->userDenganRole('Admin Aset'));

        $response = $this->get('/laporan/export/pdf?jenis=bulanan&tahun=2026&bulan=11');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');

        $contentDisposition = $response->headers->get('Content-Disposition');
        $this->assertNotNull($contentDisposition);
        $this->assertStringContainsString('Laporan_Eksekutif_BMD_November_2026', $contentDisposition);
        $this->assertStringContainsString('.pdf', $contentDisposition);

        $pdfContent = $response->getContent();
        $this->assertStringStartsWith('%PDF-', $pdfContent, 'Respons harus berupa dokumen binary PDF yang valid');
    }

    public function test_view_pdf_memuat_seluruh_seksi_wajib_dan_kop_resmi(): void
    {
        $this->actingAs($this->userDenganRole('Admin Aset'));

        $service = app(LaporanEksekutifService::class);
        $periode = $service->resolvePeriode('bulanan', 2026, 11);
        $k = $service->kalkulasi($periode['start_date'], $periode['end_date'], $periode['acuan'], null);
        $grafik = $service->kalkulasiGrafik($periode['start_date'], $periode['end_date'], $periode['acuan'], null, 'bulanan');

        $meta = [
            'nama_unit' => 'Seluruh Unit Kerja (Konsolidasi)',
            'is_admin_aset' => true,
            'unit_lokasi_id' => null,
            'jenis' => 'bulanan',
            'tahun' => 2026,
            'bulan' => 11,
        ];

        $html = view('laporan.pdf', [
            'periode' => $periode,
            'k' => $k,
            'grafik' => $grafik,
            'meta' => $meta,
            'logoJatim' => null,
            'logoInvensbun' => null,
            'waktuCetak' => '03 November 2026 12:00:00 WIB',
        ])->render();

        // 1. Kop Resmi
        $this->assertStringContainsString('PEMERINTAH PROVINSI JAWA TIMUR', $html);
        $this->assertStringContainsString('DINAS PERKEBUNAN', $html);
        $this->assertStringContainsString('Jalan Gayung Kebonsari No. 175', $html);

        // 2. Banner Metadata
        $this->assertStringContainsString('LAPORAN EKSEKUTIF PENGELOLAAN BARANG MILIK DAERAH (BMD)', $html);
        $this->assertStringContainsString('November 2026', $html);
        $this->assertStringContainsString('Seluruh Unit Kerja (Konsolidasi)', $html);

        // 3. Seksi Wajib
        $this->assertStringContainsString('I. RINGKASAN EKSEKUTIF', $html);
        $this->assertStringContainsString('II. NERACA SALDO ASET HISTORIS', $html);
        $this->assertStringContainsString('III. DISTRIBUSI KATEGORI ASET UTAMA', $html);
        $this->assertStringContainsString('IV. TREN PENGADAAN 5 TAHUN TERAKHIR', $html);
        $this->assertStringContainsString('V. REKAPITULASI VALUASI FINANSIAL', $html);
        $this->assertStringContainsString('VI. ANALISIS STATUS MASA MANFAAT', $html);
        $this->assertStringContainsString('VII. RINGKASAN KONDISI FISIK ASET', $html);
        $this->assertStringContainsString('VIII. PROGRES SENSUS FISIK SIKLUS 3-TAHUNAN', $html);
        $this->assertStringContainsString('IX. DISTRIBUSI LOKASI FISIK ASET', $html);
        $this->assertStringContainsString('X. AKTIVITAS PENGADAAN', $html);
        $this->assertStringContainsString('XI. REALISASI', $html);
        $this->assertStringContainsString('XII. METODOLOGI PERHITUNGAN', $html);

        // 4. Disclaimer Kondisi Fisik Master
        $this->assertStringContainsString('kondisi master data terkini saat laporan diunduh', $html);

        // 5. Strictly No Drilldown: Jangan memuat link individual aset
        $this->assertStringNotContainsString('/aset-barang/', $html);
        $this->assertStringNotContainsString('modal-penghapusan', $html);
    }

    public function test_ekspor_tahunan_berfungsi_dan_nama_file_menyesuaikan(): void
    {
        $this->actingAs($this->userDenganRole('Admin Aset'));

        $respExcel = $this->get('/laporan/export/excel?jenis=tahunan&tahun=2026');
        $respExcel->assertStatus(200);
        $this->assertStringContainsString('tahun_2026', strtolower((string) $respExcel->headers->get('Content-Disposition')));

        $respPdf = $this->get('/laporan/export/pdf?jenis=tahunan&tahun=2026');
        $respPdf->assertStatus(200);
        $this->assertStringContainsString('tahun_2026', strtolower((string) $respPdf->headers->get('Content-Disposition')));
    }

    public function test_pembatasan_hak_akses_admin_upt_pada_file_ekspor(): void
    {
        $uptUser = $this->adminUpt('UPT P2BTP');
        $this->actingAs($uptUser);

        // Ekspor Excel untuk Admin UPT
        $respExcel = $this->get('/laporan/export/excel?jenis=bulanan&tahun=2026&bulan=11');
        $respExcel->assertStatus(200);

        $cdExcel = strtolower((string) $respExcel->headers->get('Content-Disposition'));
        // Nama file tidak boleh berisi "seluruh_unit" jika user Admin UPT
        $this->assertStringNotContainsString('seluruh_unit_kerja', $cdExcel);
        $this->assertStringContainsString('upt_p2btp', $cdExcel);

        // Ekspor PDF untuk Admin UPT
        $respPdf = $this->get('/laporan/export/pdf?jenis=bulanan&tahun=2026&bulan=11');
        $respPdf->assertStatus(200);

        $cdPdf = strtolower((string) $respPdf->headers->get('Content-Disposition'));
        $this->assertStringNotContainsString('seluruh_unit_kerja', $cdPdf);
        $this->assertStringContainsString('upt_p2btp', $cdPdf);
    }

    public function test_halaman_index_memiliki_tombol_ekspor_dengan_parameter_aktif(): void
    {
        $this->actingAs($this->userDenganRole('Admin Aset'));

        $response = $this->get('/laporan?jenis=bulanan&tahun=2026&bulan=11&unit=semua');
        $response->assertStatus(200);

        // Periksa keberadaan tombol Ekspor Excel dan Ekspor PDF
        $response->assertSee('Ekspor Excel');
        $response->assertSee('Ekspor PDF');
        $response->assertSee(route('laporan.export.excel', ['jenis' => 'bulanan', 'tahun' => '2026', 'bulan' => '11', 'unit' => 'semua']));
        $response->assertSee(route('laporan.export.pdf', ['jenis' => 'bulanan', 'tahun' => '2026', 'bulan' => '11', 'unit' => 'semua']));
    }
}
