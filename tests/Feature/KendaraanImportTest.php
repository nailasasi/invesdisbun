<?php

namespace Tests\Feature;

use App\Http\Controllers\KendaraanController;
use App\Models\Aset;
use App\Models\Kendaraan;
use App\Models\PemegangAset;
use App\Models\RiwayatPlat;
use App\Models\User;
use App\Models\UsulanPenghapusan;
use App\Services\MasaPakaiAset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class KendaraanImportTest extends TestCase
{
    private User $adminAset;

    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabase(),
        ]);

        DB::beginTransaction();
        Carbon::setTestNow(Carbon::parse('2026-11-01 10:00:00'));

        $this->adminAset = User::whereHas('role', fn ($r) => $r->where('nama_role', 'Admin Aset'))->first();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        Carbon::setTestNow();

        foreach ($this->tempFiles as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }
        $this->tempFiles = [];

        parent::tearDown();
    }

    private function namaDatabase(): string
    {
        return $this->namaDatabasePengujian();
    }

    /**
     * Helper untuk membuat file Excel UploadedFile dari baris data.
     */
    private function buatExcelFile(array $dataRows, ?array $customHeaders = null): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Import Kendaraan');

        // Petunjuk baris 1-7
        $sheet->setCellValue('A1', 'PETUNJUK PENGISIAN IMPORT KENDARAAN DINAS');
        $sheet->setCellValue('A2', '1. Kolom Wajib (*): No Kartu Barang, Nama Kendaraan, Nomor Polisi, No. Rangka, No. Mesin, Tanggal Pengadaan, Nilai Perolehan, Kondisi, Status Aset.');

        // Baris Header pada A9
        $headers = $customHeaders ?? KendaraanController::KOLOM_TEMPLATE;
        $sheet->fromArray($headers, null, 'A9');

        // Baris data mulai A10
        $rowNum = 10;
        foreach ($dataRows as $row) {
            $sheet->fromArray($row, null, "A{$rowNum}", true);
            $rowNum++;
        }

        $writer = new Xlsx($spreadsheet);
        $tempPath = tempnam(sys_get_temp_dir(), 'kend_import_test_') . '.xlsx';
        $writer->save($tempPath);
        $this->tempFiles[] = $tempPath;

        return new UploadedFile(
            $tempPath,
            'Template-Import-Kendaraan.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    /**
     * Baris valid default untuk testing (17 kolom sesuai template resmi).
     */
    private function rowValid(array $overrides = []): array
    {
        $id = uniqid();

        $default = [
            'KIB-KND-' . $id,               // 0. No Kartu Barang
            'Toyota Hilux 2.4 Double Cab',  // 1. Nama Kendaraan
            'Pick Up',                      // 2. Jenis Kendaraan
            'Toyota',                       // 3. Merk
            'Hilux',                        // 4. Tipe
            'B ' . rand(1000, 9999) . ' XYZ', // 5. Nomor Polisi
            'RNGK-' . $id,                  // 6. No. Rangka
            'MSN-' . $id,                   // 7. No. Mesin
            '15/01/2024',                   // 8. Tanggal Pengadaan
            '15/01/2034',                   // 9. Tanggal Habis Pakai
            350000000,                      // 10. Nilai Perolehan
            'Baik',                         // 11. Kondisi
            'aktif',                        // 12. Status Aset
            'Operasional Lapangan',         // 13. Keterangan
            '02/07/2027',                   // 14. Jatuh Tempo Pajak 1 Tahun
            '02/07/2029',                   // 15. Jatuh Tempo Pajak 5 Tahun
            null,                           // 16. Dokumentasi
        ];

        foreach ($overrides as $colIndex => $val) {
            $default[$colIndex] = $val;
        }

        return $default;
    }

    // ==========================================
    // A. PENGUJIAN TEMPLATE & STRUKTUR DASAR
    // ==========================================

    public function test_download_template_berhasil_dan_memiliki_17_kolom(): void
    {
        $this->actingAs($this->adminAset);

        $res = $this->get(route('kendaraan.template'));
        $res->assertOk();
        $this->assertStringContainsString('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $res->headers->get('content-type'));

        $stream = $res->streamedContent();
        $tempPath = tempnam(sys_get_temp_dir(), 'test_tpl_') . '.xlsx';
        file_put_contents($tempPath, $stream);

        $spreadsheet = IOFactory::load($tempPath);
        $sheet = $spreadsheet->getActiveSheet();

        $headerRow = $sheet->rangeToArray('A9:Q9', null, true, true, true)[9];
        $headers = array_values(array_filter($headerRow));

        $this->assertCount(17, $headers);
        $this->assertSame(KendaraanController::KOLOM_TEMPLATE, $headers);

        @unlink($tempPath);
    }

    public function test_download_template_memiliki_petunjuk_dan_contoh_data(): void
    {
        $this->actingAs($this->adminAset);

        $res = $this->get(route('kendaraan.template'));
        $stream = $res->streamedContent();
        $tempPath = tempnam(sys_get_temp_dir(), 'test_tpl_') . '.xlsx';
        file_put_contents($tempPath, $stream);

        $spreadsheet = IOFactory::load($tempPath);
        $sheet = $spreadsheet->getActiveSheet();

        $this->assertStringContainsString('PETUNJUK PENGISIAN', (string) $sheet->getCell('A1')->getValue());
        $this->assertStringContainsString('No Kartu Barang', (string) $sheet->getCell('A2')->getValue());

        $contoh = (string) $sheet->getCell('A10')->getValue();
        $this->assertStringContainsString('CONTOH', $contoh);

        $beforeCount = Kendaraan::count();
        $uploaded = new UploadedFile($tempPath, 'Template.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $resImport = $this->post(route('kendaraan.import'), [
            'file' => $uploaded,
            'mode' => 'skip',
        ]);

        $resImport->assertSessionHas('success');
        $this->assertSame($beforeCount, Kendaraan::count());

        @unlink($tempPath);
    }

    public function test_import_kendaraan_pemegang_selalu_null(): void
    {
        $this->actingAs($this->adminAset);

        $row = $this->rowValid();
        $file = $this->buatExcelFile([$row]);

        $res = $this->post(route('kendaraan.import'), [
            'file' => $file,
            'mode' => 'skip',
        ]);
        $res->assertSessionHas('success');

        $kendaraan = Kendaraan::where('nama_kendaraan', $row[1])->first();
        $this->assertNotNull($kendaraan);
        $this->assertNull($kendaraan->pemegang);
        $this->assertFalse(PemegangAset::where('id_aset', $kendaraan->id_aset)->exists());
    }

    // ==========================================
    // B. VALIDASI 10 FIELD WAJIB
    // ==========================================

    public function test_nomor_kartu_barang_kosong_ditolak(): void
    {
        $this->actingAs($this->adminAset);

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$this->rowValid([0 => null])]),
            'mode' => 'skip',
        ]);
        $res->assertSessionHas('error');
        $this->assertStringContainsString('Nomor Kartu Barang wajib diisi', session('error'));
    }

    public function test_nama_kendaraan_kosong_ditolak(): void
    {
        $this->actingAs($this->adminAset);

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$this->rowValid([1 => ''])]),
            'mode' => 'skip',
        ]);
        $res->assertSessionHas('error');
        $this->assertStringContainsString('Nama Kendaraan wajib diisi', session('error'));
    }

    public function test_nomor_polisi_kosong_ditolak(): void
    {
        $this->actingAs($this->adminAset);

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$this->rowValid([5 => ''])]),
            'mode' => 'skip',
        ]);
        $res->assertSessionHas('error');
        $this->assertStringContainsString('Nomor Polisi wajib diisi', session('error'));
    }

    public function test_nomor_rangka_kosong_ditolak(): void
    {
        $this->actingAs($this->adminAset);

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$this->rowValid([6 => ''])]),
            'mode' => 'skip',
        ]);
        $res->assertSessionHas('error');
        $this->assertStringContainsString('Nomor Rangka wajib diisi', session('error'));
    }

    // G.1: Nomor Mesin kosong ditolak
    public function test_nomor_mesin_kosong_ditolak(): void
    {
        $this->actingAs($this->adminAset);

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$this->rowValid([7 => ''])]),
            'mode' => 'skip',
        ]);
        $res->assertSessionHas('error');
        $this->assertStringContainsString('Nomor Mesin wajib diisi', session('error'));
    }

    public function test_tanggal_pengadaan_kosong_atau_tidak_valid_ditolak(): void
    {
        $this->actingAs($this->adminAset);

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$this->rowValid([8 => ''])]),
            'mode' => 'skip',
        ]);
        $res->assertSessionHas('error');
        $this->assertStringContainsString('Tanggal Pengadaan wajib diisi', session('error'));
    }

    public function test_tanggal_habis_pakai_opsional_bila_diisi_harus_lebih_besar_dari_tanggal_pengadaan(): void
    {
        $this->actingAs($this->adminAset);

        $rowSalah = $this->rowValid([
            8 => '15/01/2024',
            9 => '10/01/2024', // Habis pakai lebih kecil dari pengadaan
        ]);

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$rowSalah]),
            'mode' => 'skip',
        ]);
        $res->assertSessionHas('error');
        $this->assertStringContainsString('harus lebih besar dari Tanggal Pengadaan', session('error'));
    }

    public function test_nilai_perolehan_kosong_nol_atau_negatif_ditolak(): void
    {
        $this->actingAs($this->adminAset);

        $res1 = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$this->rowValid([10 => ''])]),
            'mode' => 'skip',
        ]);
        $res1->assertSessionHas('error');
        $this->assertStringContainsString('Nilai Perolehan wajib diisi', $res1->getSession()->get('error'));

        $res2 = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$this->rowValid([10 => 0])]),
            'mode' => 'skip',
        ]);
        $res2->assertSessionHas('error');
        $this->assertStringContainsString('Nilai Perolehan harus lebih besar dari Rp0', $res2->getSession()->get('error'));
    }

    public function test_nilai_perolehan_format_rupiah_berhasil_diparse(): void
    {
        $this->actingAs($this->adminAset);

        $row = $this->rowValid([10 => 'Rp 275.500.000']);
        $kartu = $row[0];

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$row]),
            'mode' => 'skip',
        ]);
        $res->assertSessionHas('success');

        $aset = Aset::where('nomor_kartu_barang', $kartu)->first();
        $this->assertNotNull($aset);
        $this->assertEquals(275500000.0, (float) $aset->nilai_perolehan);
    }

    // G.2: Kondisi kosong ditolak
    public function test_kondisi_kosong_ditolak(): void
    {
        $this->actingAs($this->adminAset);

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$this->rowValid([11 => ''])]),
            'mode' => 'skip',
        ]);
        $res->assertSessionHas('error');
        $this->assertStringContainsString('Kondisi wajib diisi', session('error'));
    }

    // G.3: Status Aset kosong ditolak
    public function test_status_aset_kosong_ditolak(): void
    {
        $this->actingAs($this->adminAset);

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$this->rowValid([12 => ''])]),
            'mode' => 'skip',
        ]);
        $res->assertSessionHas('error');
        $this->assertStringContainsString('Status Aset wajib diisi', session('error'));
    }

    // ==========================================
    // C. PENGUJIAN FIELD OPSIONAL (Boleh Kosong)
    // ==========================================

    // G.4: Jenis Kendaraan kosong tetap diterima
    public function test_jenis_kendaraan_kosong_tetap_diterima(): void
    {
        $this->actingAs($this->adminAset);

        $row = $this->rowValid([2 => null]); // Jenis kosong
        $kartu = $row[0];

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$row]),
            'mode' => 'skip',
        ]);
        $res->assertSessionHas('success');

        $kendaraan = Kendaraan::whereHas('aset', fn ($a) => $a->where('nomor_kartu_barang', $kartu))->first();
        $this->assertNotNull($kendaraan);
        $this->assertNull($kendaraan->jenis_kendaraan);
    }

    // G.5: Merk kosong tetap diterima
    public function test_merk_kosong_tetap_diterima(): void
    {
        $this->actingAs($this->adminAset);

        $row = $this->rowValid([3 => null]); // Merk kosong
        $kartu = $row[0];

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$row]),
            'mode' => 'skip',
        ]);
        $res->assertSessionHas('success');

        $kendaraan = Kendaraan::whereHas('aset', fn ($a) => $a->where('nomor_kartu_barang', $kartu))->first();
        $this->assertNotNull($kendaraan);
        $this->assertNull($kendaraan->merk);
    }

    // G.6: Tipe kosong tetap diterima
    public function test_tipe_kosong_tetap_diterima(): void
    {
        $this->actingAs($this->adminAset);

        $row = $this->rowValid([4 => null]); // Tipe kosong
        $kartu = $row[0];

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$row]),
            'mode' => 'skip',
        ]);
        $res->assertSessionHas('success');

        $kendaraan = Kendaraan::whereHas('aset', fn ($a) => $a->where('nomor_kartu_barang', $kartu))->first();
        $this->assertNotNull($kendaraan);
        $this->assertNull($kendaraan->tipe);
    }

    // ==========================================
    // D. PENGUJIAN STATUS ASET (HANYA AKTIF / NONAKTIF)
    // ==========================================

    // G.7: Status aktif diterima
    public function test_status_aktif_diterima(): void
    {
        $this->actingAs($this->adminAset);

        $row = $this->rowValid([12 => 'aktif']);
        $kartu = $row[0];

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$row]),
            'mode' => 'skip',
        ]);
        $res->assertSessionHas('success');

        $aset = Aset::where('nomor_kartu_barang', $kartu)->first();
        $this->assertSame('aktif', $aset->status_aset);
    }

    // G.8: Status nonaktif diterima
    public function test_status_nonaktif_diterima(): void
    {
        $this->actingAs($this->adminAset);

        $row = $this->rowValid([12 => 'nonaktif']);
        $kartu = $row[0];

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$row]),
            'mode' => 'skip',
        ]);
        $res->assertSessionHas('success');

        $aset = Aset::where('nomor_kartu_barang', $kartu)->first();
        $this->assertSame('nonaktif', $aset->status_aset);
    }

    // G.9: Status Dihapuskan ditolak
    public function test_status_dihapuskan_ditolak(): void
    {
        $this->actingAs($this->adminAset);

        $row = $this->rowValid([12 => 'Dihapuskan']);

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$row]),
            'mode' => 'skip',
        ]);
        $res->assertSessionHas('error');
        $this->assertStringContainsString('Status Aset', session('error'));
        $this->assertStringContainsString('tidak valid', session('error'));
    }

    // G.10: Status Mutasi ditolak
    public function test_status_mutasi_ditolak(): void
    {
        $this->actingAs($this->adminAset);

        $row = $this->rowValid([12 => 'Mutasi']);

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$row]),
            'mode' => 'skip',
        ]);
        $res->assertSessionHas('error');
        $this->assertStringContainsString('Status Aset', session('error'));
        $this->assertStringContainsString('tidak valid', session('error'));
    }

    // ==========================================
    // E. INTEGRASI RUSAK BERAT & USULAN PENGHAPUSAN
    // ==========================================

    // G.11: Kondisi Rusak Berat memicu mekanisme Usulan Penghapusan
    public function test_kondisi_rusak_berat_memicu_mekanisme_usulan_penghapusan(): void
    {
        $this->actingAs($this->adminAset);

        $row = $this->rowValid([11 => 'Rusak Berat']);
        $kartu = $row[0];

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$row]),
            'mode' => 'skip',
        ]);
        $res->assertSessionHas('success');

        $aset = Aset::where('nomor_kartu_barang', $kartu)->first();
        $this->assertNotNull($aset);

        $this->assertTrue(
            UsulanPenghapusan::where('id_aset', $aset->id_aset)
                ->where('status_usulan', 'diajukan')
                ->exists(),
            'Kendaraan Rusak Berat harus dibuatkan UsulanPenghapusan otomatis.'
        );

        $this->assertSame('diusulkan_hapus', $aset->fresh()->status_aset);
    }

    // G.12: Aset Rusak Berat tidak tampil pada daftar utama Kendaraan
    public function test_aset_rusak_berat_tidak_tampil_pada_daftar_utama_kendaraan(): void
    {
        $this->actingAs($this->adminAset);

        $row = $this->rowValid([11 => 'Rusak Berat']);
        $kartu = $row[0];

        $resImport = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$row]),
            'mode' => 'skip',
        ]);
        $resImport->assertSessionHas('success');

        $kendaraan = Kendaraan::whereHas('aset', fn ($a) => $a->where('nomor_kartu_barang', $kartu))->first();
        $this->assertNotNull($kendaraan);

        // Query ke halaman utama daftar kendaraan
        $resIndex = $this->get(route('kendaraan.index', ['search' => $kartu]));
        $resIndex->assertOk();

        $kendaraanList = $resIndex->getOriginalContent()->getData()['kendaraanList'];
        $ids = collect($kendaraanList->items())->pluck('id_kendaraan')->all();
        $this->assertNotContains($kendaraan->id_kendaraan, $ids, 'Kendaraan Rusak Berat tidak boleh muncul di daftar utama.');
    }

    // G.13: Usulan Penghapusan tidak dibuat ganda
    public function test_usulan_penghapusan_tidak_dibuat_ganda(): void
    {
        $this->actingAs($this->adminAset);

        $row = $this->rowValid([11 => 'Rusak Berat']);
        $kartu = $row[0];

        // Import pertama
        $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$row]),
            'mode' => 'skip',
        ])->assertSessionHas('success');

        $aset = Aset::where('nomor_kartu_barang', $kartu)->first();
        $countAwal = UsulanPenghapusan::where('id_aset', $aset->id_aset)->count();
        $this->assertSame(1, $countAwal);

        // Import kedua pada mode update dengan kondisi Rusak Berat lagi
        $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$row]),
            'mode' => 'update',
        ])->assertSessionHas('success');

        $countAkhir = UsulanPenghapusan::where('id_aset', $aset->id_aset)->count();
        $this->assertSame(1, $countAkhir, 'Usulan penghapusan tidak boleh dibuat ganda saat update.');
    }

    // ==========================================
    // F. MODE UPDATE & NOMOR KARTU BARANG SEBAGAI IDENTITAS UTAMA
    // ==========================================

    // G.14: Mode Update mencocokkan berdasarkan Nomor Kartu Barang
    public function test_mode_update_mencocokkan_berdasarkan_nomor_kartu_barang(): void
    {
        $this->actingAs($this->adminAset);

        $existing = Kendaraan::whereNotNull('nomor_rangka')->whereNotNull('nomor_mesin')->whereHas('aset')->first();
        $this->assertNotNull($existing);

        $kartu = $existing->aset->nomor_kartu_barang;
        $namaBaru = 'Kendaraan Terupdate KIB ' . uniqid();

        $row = $this->rowValid([
            0 => $kartu,
            1 => $namaBaru,
            6 => $existing->nomor_rangka,
            7 => $existing->nomor_mesin,
        ]);

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$row]),
            'mode' => 'update',
        ]);
        $res->assertSessionHas('success');

        $fresh = $existing->fresh();
        $this->assertSame($namaBaru, $fresh->nama_kendaraan);
    }

    // G.15: Nomor Kartu Barang berbeda diperlakukan sebagai kendaraan baru
    public function test_nomor_kartu_barang_berbeda_diperlakukan_sebagai_kendaraan_baru(): void
    {
        $this->actingAs($this->adminAset);

        $existing = Kendaraan::whereNotNull('nomor_rangka')->whereNotNull('nomor_mesin')->whereHas('aset')->first();
        $this->assertNotNull($existing);

        // KIB berbeda tetapi nomor rangka milik existing
        $row = $this->rowValid([
            0 => 'KIB-KND-BEDA-' . uniqid(),
            6 => $existing->nomor_rangka, // tabrakan rangka
            7 => 'MSN-BARU-' . uniqid(),
        ]);

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$row]),
            'mode' => 'update',
        ]);

        $res->assertSessionHas('error');
        $this->assertStringContainsString('Nomor Rangka', session('error'));
        $this->assertStringContainsString('sudah terdaftar pada kendaraan lain di sistem', session('error'));
    }

    // G.16: Nomor Polisi berbeda menghasilkan riwayat plat baru
    public function test_nomor_polisi_berbeda_menghasilkan_riwayat_plat_baru(): void
    {
        $this->actingAs($this->adminAset);

        // Buat kendaraan awal
        $rowAwal = $this->rowValid([5 => 'B 1111 OLD']);
        $kartu = $rowAwal[0];

        $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$rowAwal]),
            'mode' => 'skip',
        ])->assertSessionHas('success');

        $kendaraan = Kendaraan::whereHas('aset', fn ($a) => $a->where('nomor_kartu_barang', $kartu))->first();
        $platAwal = $kendaraan->platAktif;
        $this->assertSame('B 1111 OLD', $platAwal->nomor_plat);

        // Update dengan plat baru
        $rowBaru = $this->rowValid([
            0 => $kartu,
            5 => 'B 2222 NEW',
            6 => $rowAwal[6],
            7 => $rowAwal[7],
        ]);

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$rowBaru]),
            'mode' => 'update',
        ]);
        $res->assertSessionHas('success');

        // Plat lama nonaktif, plat baru aktif
        $this->assertSame('Tidak Aktif', $platAwal->fresh()->status);

        $platBaruAktif = $kendaraan->fresh()->platAktif;
        $this->assertNotNull($platBaruAktif);
        $this->assertSame('B 2222 NEW', $platBaruAktif->nomor_plat);
        $this->assertSame('Aktif', $platBaruAktif->status);

        // Total riwayat plat = 2
        $this->assertSame(2, RiwayatPlat::where('id_kendaraan', $kendaraan->id_kendaraan)->count());
    }

    // G.17: Nomor Polisi sama tidak menghasilkan riwayat baru yang tidak diperlukan
    public function test_nomor_polisi_sama_tidak_menghasilkan_riwayat_baru_yang_tidak_diperlukan(): void
    {
        $this->actingAs($this->adminAset);

        $rowAwal = $this->rowValid([5 => 'B 3333 SAM']);
        $kartu = $rowAwal[0];

        $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$rowAwal]),
            'mode' => 'skip',
        ])->assertSessionHas('success');

        $kendaraan = Kendaraan::whereHas('aset', fn ($a) => $a->where('nomor_kartu_barang', $kartu))->first();
        $countPlatSebelum = RiwayatPlat::where('id_kendaraan', $kendaraan->id_kendaraan)->count();
        $this->assertSame(1, $countPlatSebelum);

        // Update dengan nomor polisi yang sama persis
        $rowUpdate = $this->rowValid([
            0 => $kartu,
            1 => 'Nama Berganti ' . uniqid(),
            5 => 'B 3333 SAM',
            6 => $rowAwal[6],
            7 => $rowAwal[7],
        ]);

        $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$rowUpdate]),
            'mode' => 'update',
        ])->assertSessionHas('success');

        $countPlatSesudah = RiwayatPlat::where('id_kendaraan', $kendaraan->id_kendaraan)->count();
        $this->assertSame(1, $countPlatSesudah, 'Tidak boleh membuat riwayat plat baru jika nomor polisi sama.');
    }

    // G.18: Nomor Mesin yang sudah digunakan kendaraan lain menyebabkan Update gagal
    public function test_nomor_mesin_yang_sudah_digunakan_kendaraan_lain_menyebabkan_update_gagal(): void
    {
        $this->actingAs($this->adminAset);

        // Buat kendaraan A & kendaraan B
        $rowA = $this->rowValid([0 => 'KIB-VA-' . uniqid(), 6 => 'RNGK-VA-' . uniqid(), 7 => 'MSN-VA-' . uniqid()]);
        $rowB = $this->rowValid([0 => 'KIB-VB-' . uniqid(), 6 => 'RNGK-VB-' . uniqid(), 7 => 'MSN-VB-' . uniqid()]);

        $this->post(route('kendaraan.import'), ['file' => $this->buatExcelFile([$rowA]), 'mode' => 'skip'])->assertSessionHas('success');
        $this->post(route('kendaraan.import'), ['file' => $this->buatExcelFile([$rowB]), 'mode' => 'skip'])->assertSessionHas('success');

        $kendaraanA = Kendaraan::whereHas('aset', fn ($a) => $a->where('nomor_kartu_barang', $rowA[0]))->first();

        // Update kendaraan A menggunakan nomor mesin kendaraan B
        $rowAUpdate = $this->rowValid([
            0 => $rowA[0],
            6 => $rowA[6],
            7 => $rowB[7], // Konflik mesin dengan kendaraan B
        ]);

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$rowAUpdate]),
            'mode' => 'update',
        ]);

        $res->assertSessionHas('error');
        $this->assertStringContainsString('Konflik Nomor Mesin', session('error'));
        $this->assertStringContainsString('sudah digunakan oleh kendaraan lain', session('error'));

        // Pastikan mesin A tidak berubah
        $this->assertSame($rowA[7], $kendaraanA->fresh()->nomor_mesin);
    }

    // G.19: Konflik Nomor Rangka menyebabkan proses gagal
    public function test_konflik_nomor_rangka_menyebabkan_proses_gagal(): void
    {
        $this->actingAs($this->adminAset);

        // Buat kendaraan A & kendaraan B
        $rowA = $this->rowValid([0 => 'KIB-RGA-' . uniqid(), 6 => 'RNGK-RGA-' . uniqid(), 7 => 'MSN-RGA-' . uniqid()]);
        $rowB = $this->rowValid([0 => 'KIB-RGB-' . uniqid(), 6 => 'RNGK-RGB-' . uniqid(), 7 => 'MSN-RGB-' . uniqid()]);

        $this->post(route('kendaraan.import'), ['file' => $this->buatExcelFile([$rowA]), 'mode' => 'skip'])->assertSessionHas('success');
        $this->post(route('kendaraan.import'), ['file' => $this->buatExcelFile([$rowB]), 'mode' => 'skip'])->assertSessionHas('success');

        $kendaraanA = Kendaraan::whereHas('aset', fn ($a) => $a->where('nomor_kartu_barang', $rowA[0]))->first();

        // Update kendaraan A menggunakan nomor rangka kendaraan B
        $rowAUpdate = $this->rowValid([
            0 => $rowA[0],
            6 => $rowB[6], // Konflik rangka dengan kendaraan B
            7 => $rowA[7],
        ]);

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$rowAUpdate]),
            'mode' => 'update',
        ]);

        $res->assertSessionHas('error');
        $this->assertStringContainsString('Konflik Nomor Rangka', session('error'));
        $this->assertStringContainsString('sudah digunakan oleh kendaraan lain', session('error'));

        // Pastikan rangka A tidak berubah
        $this->assertSame($rowA[6], $kendaraanA->fresh()->nomor_rangka);
    }

    // G.20: Pemegang kendaraan lama tidak berubah saat Update
    public function test_pemegang_kendaraan_lama_tidak_berubah_saat_update(): void
    {
        $this->actingAs($this->adminAset);

        $rowAwal = $this->rowValid();
        $kartu = $rowAwal[0];

        $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$rowAwal]),
            'mode' => 'skip',
        ])->assertSessionHas('success');

        $kendaraan = Kendaraan::whereHas('aset', fn ($a) => $a->where('nomor_kartu_barang', $kartu))->first();

        // Set pemegang manual pada kendaraan lama
        $kendaraan->update(['pemegang' => 'Drs. H. Mulyadi, M.Si']);

        // Update kendaraan via import
        $rowUpdate = $this->rowValid([
            0 => $kartu,
            1 => 'Toyota Hilux Modif ' . uniqid(),
            6 => $rowAwal[6],
            7 => $rowAwal[7],
            10 => 400000000,
        ]);

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$rowUpdate]),
            'mode' => 'update',
        ]);
        $res->assertSessionHas('success');

        // Pastikan pemegang tetap 'Drs. H. Mulyadi, M.Si'
        $this->assertSame('Drs. H. Mulyadi, M.Si', $kendaraan->fresh()->pemegang);
    }

    // G.21: Tidak terjadi record aset atau kendaraan setengah jadi
    public function test_tidak_terjadi_record_aset_atau_kendaraan_setengah_jadi(): void
    {
        $this->actingAs($this->adminAset);

        $kartu = 'KIB-HALF-' . uniqid();
        $rangka = 'RNGK-HALF-' . uniqid();
        $mesin = 'MSN-HALF-' . uniqid();

        // Baris ini sengaja dibuat gagal karena nilai perolehan negatif
        $rowGagal = $this->rowValid([
            0 => $kartu,
            6 => $rangka,
            7 => $mesin,
            10 => -50000,
        ]);

        $beforeAset = Aset::count();
        $beforeKend = Kendaraan::count();

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$rowGagal]),
            'mode' => 'skip',
        ]);

        $res->assertSessionHas('error');
        $this->assertSame($beforeAset, Aset::count(), 'Aset tidak boleh tersimpan jika terjadi error.');
        $this->assertSame($beforeKend, Kendaraan::count(), 'Kendaraan tidak boleh tersimpan jika terjadi error.');
        $this->assertNull(Aset::where('nomor_kartu_barang', $kartu)->first());
        $this->assertNull(Kendaraan::where('nomor_rangka', $rangka)->first());
    }

    // G.22: Data tetap terbaca dengan benar di Monitoring Aset
    public function test_data_tetap_terbaca_dengan_benar_di_monitoring_aset(): void
    {
        $this->actingAs($this->adminAset);

        $kartu = 'KIB-MONITOR-' . uniqid();
        $row = $this->rowValid([
            0 => $kartu,
            8 => '01/01/2024',
            9 => '01/01/2034',
            10 => 300000000,
            11 => 'Baik',
            12 => 'aktif',
        ]);

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$row]),
            'mode' => 'skip',
        ]);
        $res->assertSessionHas('success');

        $aset = Aset::where('nomor_kartu_barang', $kartu)->first();
        $this->assertNotNull($aset);

        $masaPakai = app(MasaPakaiAset::class);
        $eval = $masaPakai->evaluasi($aset);

        $this->assertNotNull($eval['nilai_buku']);
        $this->assertNotNull($eval['sisa_hari']);
        $this->assertNotSame('nan', $eval['masa_status']);
        $this->assertSame('normal', $eval['masa_status']);

        $resMonitoring = $this->get(route('monitoring-aset.index', ['search' => $kartu]));
        $resMonitoring->assertOk();
        $resMonitoring->assertSee($kartu);
        $resMonitoring->assertSee('300.000.000');
    }

    public function test_import_jatuh_tempo_pajak_tersimpan_di_kendaraan_dan_tidak_membuat_record_pajak_kendaraan(): void
    {
        $this->actingAs($this->adminAset);

        $row = $this->rowValid([
            14 => '15/08/2027', // Jatuh Tempo Pajak 1 Tahun
            15 => '15/08/2029', // Jatuh Tempo Pajak 5 Tahun
        ]);
        $kartu = $row[0];

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$row]),
            'mode' => 'skip',
        ]);
        $res->assertSessionHas('success');

        $kendaraan = Kendaraan::whereHas('aset', fn ($a) => $a->where('nomor_kartu_barang', $kartu))->first();
        $this->assertNotNull($kendaraan);

        // Nilai tersimpan di kolom kendaraan
        $this->assertSame('2027-08-15', $kendaraan->jatuh_tempo_pkb?->format('Y-m-d'));
        $this->assertSame('2029-08-15', $kendaraan->jatuh_tempo_stnk?->format('Y-m-d'));

        // Status PKB dan STNK menampilkan tanggal dengan benar
        $stPkb = $kendaraan->statusPkb();
        $this->assertSame('2027-08-15', $stPkb['tanggal']?->format('Y-m-d'));
        $this->assertSame('Aman', $stPkb['status']);

        $stStnk = $kendaraan->statusStnk();
        $this->assertSame('2029-08-15', $stStnk['tanggal']?->format('Y-m-d'));
        $this->assertSame('Aman', $stStnk['status']);

        // TIDAK ADA record transaksi PajakKendaraan yang terbuat!
        $this->assertSame(0, \App\Models\PajakKendaraan::where('id_kendaraan', $kendaraan->id_kendaraan)->count());
    }

    // ==========================================
    // G. PENGUJIAN LAIN-LAIN
    // ==========================================

    public function test_nomor_kartu_barang_duplikat_antarbaris_ditolak(): void
    {
        $this->actingAs($this->adminAset);

        $kartuSama = 'KIB-GANDA-' . uniqid();
        $row1 = $this->rowValid([0 => $kartuSama]);
        $row2 = $this->rowValid([0 => $kartuSama]);

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$row1, $row2]),
            'mode' => 'skip',
        ]);

        $res->assertSessionHas('error');
        $this->assertStringContainsString('Duplikasi dalam file', session('error'));
        $this->assertStringContainsString('Nomor Kartu Barang', session('error'));
    }

    public function test_nomor_rangka_dan_mesin_duplikat_antarbaris_ditolak(): void
    {
        $this->actingAs($this->adminAset);

        $rangkaSama = 'RNGK-GANDA-' . uniqid();
        $row1 = $this->rowValid([6 => $rangkaSama]);
        $row2 = $this->rowValid([6 => $rangkaSama]);
        $resRangka = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$row1, $row2]),
            'mode' => 'skip',
        ]);
        $resRangka->assertSessionHas('error');
        $this->assertStringContainsString('Nomor Rangka', $resRangka->getSession()->get('error'));

        $mesinSama = 'MSN-GANDA-' . uniqid();
        $row3 = $this->rowValid([7 => $mesinSama]);
        $row4 = $this->rowValid([7 => $mesinSama]);
        $resMesin = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$row3, $row4]),
            'mode' => 'skip',
        ]);
        $resMesin->assertSessionHas('error');
        $this->assertStringContainsString('Nomor Mesin', $resMesin->getSession()->get('error'));
    }

    public function test_mode_lewati_berfungsi_dan_tidak_menimpa(): void
    {
        $this->actingAs($this->adminAset);

        $rowAwal = $this->rowValid();
        $kartu = $rowAwal[0];

        $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$rowAwal]),
            'mode' => 'skip',
        ])->assertSessionHas('success');

        $existing = Kendaraan::whereHas('aset', fn ($a) => $a->where('nomor_kartu_barang', $kartu))->first();
        $beforeNama = $existing->nama_kendaraan;
        $beforeCount = Kendaraan::count();

        // Import baris yang sama persis (kartu barang sama) pada mode skip
        $rowDuplikat = $this->rowValid([
            0 => $kartu,
            1 => 'Nama Berubah Harus Ditolak',
            6 => $rowAwal[6],
            7 => $rowAwal[7],
        ]);

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$rowDuplikat]),
            'mode' => 'skip',
        ]);

        $res->assertSessionHas('success');
        $this->assertStringContainsString('1 duplikat dilewati', session('success'));
        $this->assertSame($beforeCount, Kendaraan::count());
        $this->assertSame($beforeNama, $existing->fresh()->nama_kendaraan);
    }

    public function test_header_pada_baris_berbeda_tetap_dapat_dibaca(): void
    {
        $this->actingAs($this->adminAset);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Taruh header di baris 4
        $sheet->fromArray(KendaraanController::KOLOM_TEMPLATE, null, 'A4');

        $row = $this->rowValid();
        $kartu = $row[0];
        $sheet->fromArray($row, null, 'A5', true);

        $writer = new Xlsx($spreadsheet);
        $tempPath = tempnam(sys_get_temp_dir(), 'test_shift_') . '.xlsx';
        $writer->save($tempPath);

        $uploaded = new UploadedFile($tempPath, 'Shift.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $res = $this->post(route('kendaraan.import'), [
            'file' => $uploaded,
            'mode' => 'skip',
        ]);

        $res->assertSessionHas('success');
        $this->assertNotNull(Aset::where('nomor_kartu_barang', $kartu)->first());

        @unlink($tempPath);
    }

    public function test_mode_update_mempertahankan_tanggal_habis_pakai_jika_kolom_tidak_ada_atau_kosong(): void
    {
        $this->actingAs($this->adminAset);

        $rowAwal = $this->rowValid([
            8 => '15/01/2024',
            9 => '15/01/2034',
        ]);
        $kartu = $rowAwal[0];

        $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$rowAwal]),
            'mode' => 'skip',
        ])->assertSessionHas('success');

        $aset = Aset::where('nomor_kartu_barang', $kartu)->first();
        $this->assertSame('2034-01-15', $aset->tanggal_habis_pakai?->format('Y-m-d'));

        // 1. Update dengan kolom Tanggal Habis Pakai ada tetapi selnya kosong -> pertahankan nilai lama
        $rowKosong = $this->rowValid([
            0 => $kartu,
            6 => $rowAwal[6],
            7 => $rowAwal[7],
            8 => '15/01/2024',
            9 => null,
        ]);

        $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$rowKosong]),
            'mode' => 'update',
        ])->assertSessionHas('success');

        $this->assertSame('2034-01-15', $aset->fresh()->tanggal_habis_pakai?->format('Y-m-d'));

        // 2. Update tanpa kolom Tanggal Habis Pakai di header -> tetap pertahankan nilai lama
        $headersTanpaHabis = array_values(array_filter(
            KendaraanController::KOLOM_TEMPLATE,
            fn ($col) => $col !== 'Tanggal Habis Pakai'
        ));
        $rowTanpaKolom = $this->rowValid([
            0 => $kartu,
            6 => $rowAwal[6],
            7 => $rowAwal[7],
            8 => '15/01/2024',
        ]);
        array_splice($rowTanpaKolom, 9, 1);

        $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$rowTanpaKolom], $headersTanpaHabis),
            'mode' => 'update',
        ])->assertSessionHas('success');

        $this->assertSame('2034-01-15', $aset->fresh()->tanggal_habis_pakai?->format('Y-m-d'));
    }

    public function test_batas_panjang_karakter_jenis_merk_tipe_sesuai_form_tambah(): void
    {
        $this->actingAs($this->adminAset);

        $rowValidMax = $this->rowValid([
            2 => str_repeat('J', 150),
            3 => str_repeat('M', 100),
            4 => str_repeat('T', 100),
        ]);

        $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$rowValidMax]),
            'mode' => 'skip',
        ])->assertSessionHas('success');

        $rowTipeBerlebih = $this->rowValid([
            4 => str_repeat('T', 101),
        ]);

        $res = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$rowTipeBerlebih]),
            'mode' => 'skip',
        ]);
        $res->assertSessionHas('error');
        $this->assertStringContainsString('Tipe melebihi 100 karakter', session('error'));
    }

    /**
     * Helper untuk membuat file Excel UploadedFile dengan gambar tersisip per baris.
     *
     * @param  array<int, string>  $imagePathsByRow  [nomorBarisExcel => pathFileGambar]
     */
    private function buatExcelFileDenganGambar(array $dataRows, array $imagePathsByRow): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Import Kendaraan');

        $sheet->setCellValue('A1', 'PETUNJUK PENGISIAN IMPORT KENDARAAN DINAS');
        $sheet->fromArray(KendaraanController::KOLOM_TEMPLATE, null, 'A9');

        $rowNum = 10;
        foreach ($dataRows as $row) {
            $sheet->fromArray($row, null, "A{$rowNum}", true);
            $rowNum++;
        }

        foreach ($imagePathsByRow as $rowTarget => $imgPath) {
            $drawing = new Drawing();
            $drawing->setName('Foto Baris ' . $rowTarget);
            $drawing->setPath($imgPath);
            $drawing->setCoordinates("Q{$rowTarget}");
            $drawing->setWorksheet($sheet);
        }

        $writer = new Xlsx($spreadsheet);
        $tempPath = tempnam(sys_get_temp_dir(), 'kend_img_test_') . '.xlsx';
        $writer->save($tempPath);
        $this->tempFiles[] = $tempPath;

        return new UploadedFile(
            $tempPath,
            'Template-Import-Kendaraan-Gambar.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    private function buatFileSementaraBytes(string $ext, string $bytes): string
    {
        $path = tempnam(sys_get_temp_dir(), 'img_test_') . '.' . $ext;
        file_put_contents($path, $bytes);
        $this->tempFiles[] = $path;

        return $path;
    }

    public function test_import_gambar_valid_tersimpan_di_storage_dan_database(): void
    {
        Storage::fake('public');
        $this->actingAs($this->adminAset);

        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $imgPath = $this->buatFileSementaraBytes('png', $pngBytes);

        $row = $this->rowValid();
        $kartu = $row[0];
        $file = $this->buatExcelFileDenganGambar([$row], [10 => $imgPath]);

        $res = $this->post(route('kendaraan.import'), [
            'file' => $file,
            'mode' => 'skip',
        ]);
        $res->assertSessionHas('success');

        $kendaraan = Kendaraan::whereHas('aset', fn ($a) => $a->where('nomor_kartu_barang', $kartu))->first();
        $this->assertNotNull($kendaraan);
        $this->assertNotNull($kendaraan->foto);
        $this->assertStringStartsWith('kendaraan/', $kendaraan->foto);
        $this->assertStringEndsWith('.png', $kendaraan->foto);
        Storage::disk('public')->assertExists($kendaraan->foto);
    }

    public function test_import_gambar_tidak_valid_atau_melebihi_batas_ditolak_tanpa_orphan_file(): void
    {
        Storage::fake('public');
        $this->actingAs($this->adminAset);

        // 1. Ekstensi terlarang (.gif)
        $gifBytes = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        $gifPath = $this->buatFileSementaraBytes('gif', $gifBytes);
        $rowGif = $this->rowValid();

        $resGif = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFileDenganGambar([$rowGif], [10 => $gifPath]),
            'mode' => 'skip',
        ]);
        $resGif->assertSessionHas('error');
        $this->assertStringContainsString('Format foto tidak didukung', session('error'));
        $this->assertNull(Aset::where('nomor_kartu_barang', $rowGif[0])->first());
        $this->assertSame([], Storage::disk('public')->allFiles('kendaraan'));

        // 2. Bukan gambar valid (berkas .png berisi teks biasa)
        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $pngPath = $this->buatFileSementaraBytes('png', $pngBytes);
        $rowFakePng = $this->rowValid();
        $uploadedFakePng = $this->buatExcelFileDenganGambar([$rowFakePng], [10 => $pngPath]);

        $zip = new \ZipArchive();
        if ($zip->open($uploadedFakePng->getRealPath()) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);
                if (str_starts_with($entryName, 'xl/media/')) {
                    $zip->addFromString($entryName, 'ini-bukan-file-gambar-valid');
                }
            }
            $zip->close();
        }

        $resFakePng = $this->post(route('kendaraan.import'), [
            'file' => $uploadedFakePng,
            'mode' => 'skip',
        ]);
        $resFakePng->assertSessionHas('error');
        $this->assertStringContainsString('File foto tidak valid', session('error'));
        $this->assertNull(Aset::where('nomor_kartu_barang', $rowFakePng[0])->first());
        $this->assertSame([], Storage::disk('public')->allFiles('kendaraan'));

        // 3. Ukuran biner melebihi 2MB (2048 * 1024 bytes)
        $oversizedPng = $pngBytes . str_repeat("\0", (2048 * 1024) + 128);
        $oversizedPath = $this->buatFileSementaraBytes('png', $oversizedPng);
        $rowBesar = $this->rowValid();

        $resBesar = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFileDenganGambar([$rowBesar], [10 => $oversizedPath]),
            'mode' => 'skip',
        ]);
        $resBesar->assertSessionHas('error');
        $this->assertStringContainsString('Ukuran foto melebihi batas maksimal 2MB', session('error'));
        $this->assertNull(Aset::where('nomor_kartu_barang', $rowBesar[0])->first());
        $this->assertSame([], Storage::disk('public')->allFiles('kendaraan'));
    }

    public function test_baris_gagal_validasi_data_tidak_menyisakan_orphan_file_gambar(): void
    {
        Storage::fake('public');
        $this->actingAs($this->adminAset);

        $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $imgPath = $this->buatFileSementaraBytes('png', $pngBytes);

        // Baris gagal karena tanggal pengadaan tidak valid
        $rowTanggalSalah = $this->rowValid([8 => 'tanggal-tidak-valid']);
        $resTanggal = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFileDenganGambar([$rowTanggalSalah], [10 => $imgPath]),
            'mode' => 'skip',
        ]);
        $resTanggal->assertSessionHas('error');
        $this->assertSame([], Storage::disk('public')->allFiles('kendaraan'));

        // Baris gagal karena duplikasi Nomor Kartu Barang dalam file
        $kartuSama = 'KIB-DUP-IMG-' . uniqid();
        $row1 = $this->rowValid([0 => $kartuSama]);
        $row2 = $this->rowValid([0 => $kartuSama]);

        $resDup = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFileDenganGambar([$row1, $row2], [11 => $imgPath]),
            'mode' => 'skip',
        ]);
        $resDup->assertSessionHas('error');
        $this->assertSame([], Storage::disk('public')->allFiles('kendaraan'));
    }

    public function test_tanggal_pajak_tidak_valid_ditolak_sedangkan_kosong_diterima_null(): void
    {
        $this->actingAs($this->adminAset);

        // 1. Tanggal pajak 1 tahun format salah ditolak
        $rowPajak1Salah = $this->rowValid([14 => 'bukan-tanggal']);
        $res1 = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$rowPajak1Salah]),
            'mode' => 'skip',
        ]);
        $res1->assertSessionHas('error');
        $this->assertStringContainsString('Jatuh Tempo Pajak 1 Tahun', session('error'));
        $this->assertNull(Aset::where('nomor_kartu_barang', $rowPajak1Salah[0])->first());

        // 2. Tanggal pajak 5 tahun format salah ditolak
        $rowPajak5Salah = $this->rowValid([15 => '31/02/2029']);
        $res2 = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$rowPajak5Salah]),
            'mode' => 'skip',
        ]);
        $res2->assertSessionHas('error');
        $this->assertStringContainsString('Jatuh Tempo Pajak 5 Tahun', session('error'));
        $this->assertNull(Aset::where('nomor_kartu_barang', $rowPajak5Salah[0])->first());

        // 3. Sel pajak kosong (null / string spasi) tetap lolos bernilai null
        $rowPajakKosong = $this->rowValid([14 => null, 15 => '   ']);
        $res3 = $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$rowPajakKosong]),
            'mode' => 'skip',
        ]);
        $res3->assertSessionHas('success');

        $kendaraan = Kendaraan::whereHas('aset', fn ($a) => $a->where('nomor_kartu_barang', $rowPajakKosong[0]))->first();
        $this->assertNotNull($kendaraan);
        $this->assertNull($kendaraan->jatuh_tempo_pkb);
        $this->assertNull($kendaraan->jatuh_tempo_stnk);
    }

    public function test_mode_update_plat_nomor_normalisasi_spasi_dan_konsisten_dengan_form_edit(): void
    {
        $this->actingAs($this->adminAset);

        $rowAwal = $this->rowValid([
            5 => 'B 1111 OLD',
            15 => '02/07/2029',
        ]);
        $kartu = $rowAwal[0];

        $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$rowAwal]),
            'mode' => 'skip',
        ])->assertSessionHas('success');

        $kendaraan = Kendaraan::whereHas('aset', fn ($a) => $a->where('nomor_kartu_barang', $kartu))->first();
        $this->assertSame(1, RiwayatPlat::where('id_kendaraan', $kendaraan->id_kendaraan)->count());

        // 1. Update dengan spasi berlebih / huruf kecil ('  b   1111  old ') -> dianggap sama, tidak buat riwayat baru
        $rowSpasiSama = $this->rowValid([
            0 => $kartu,
            5 => '  b   1111  old ',
            6 => $rowAwal[6],
            7 => $rowAwal[7],
        ]);

        $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$rowSpasiSama]),
            'mode' => 'update',
        ])->assertSessionHas('success');

        $this->assertSame(1, RiwayatPlat::where('id_kendaraan', $kendaraan->id_kendaraan)->count());

        // 2. Update dengan plat baru berspasi ganda dan tanggal pajak 5 tahun kosong -> warisi tanggal_berlaku lama & ganti_plat = false
        $rowPlatBaru = $this->rowValid([
            0 => $kartu,
            5 => 'B   2222  NEW',
            6 => $rowAwal[6],
            7 => $rowAwal[7],
            15 => null,
        ]);

        $this->post(route('kendaraan.import'), [
            'file' => $this->buatExcelFile([$rowPlatBaru]),
            'mode' => 'update',
        ])->assertSessionHas('success');

        $platAktifBaru = $kendaraan->fresh()->platAktif;
        $this->assertNotNull($platAktifBaru);
        $this->assertSame('B 2222 NEW', $platAktifBaru->nomor_plat);
        $this->assertFalse((bool) $platAktifBaru->ganti_plat);
        $this->assertSame('2029-07-02', $platAktifBaru->tanggal_berlaku?->format('Y-m-d'));
        $this->assertSame(2, RiwayatPlat::where('id_kendaraan', $kendaraan->id_kendaraan)->count());
    }
}
