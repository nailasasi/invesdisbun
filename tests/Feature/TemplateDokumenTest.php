<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\TemplateDokumen;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplateDokumenTest extends TestCase
{
    protected array $filesDibuatSementara = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabase(),
        ]);

        DB::beginTransaction();

        $this->pastikanBerkasTemplateTersedia();
    }

    protected function tearDown(): void
    {
        DB::rollBack();

        foreach ($this->filesDibuatSementara as $path) {
            if (file_exists($path)) {
                @unlink($path);
            }
        }

        parent::tearDown();
    }

    private function pastikanBerkasTemplateTersedia(): void
    {
        $sampleDocx = storage_path('app/private/templates/kir_20261119_154822_d125383b.docx');

        foreach (TemplateDokumen::all() as $tpl) {
            if ($tpl->file_path && ! $tpl->fileExists()) {
                $targetPath = Storage::disk(TemplateDokumen::DISK)->path($tpl->file_path);
                $dir = dirname($targetPath);
                if (! is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                }

                if ($tpl->tipe_berkas === 'excel') {
                    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                    $sheet = $spreadsheet->getActiveSheet();
                    $sheet->setTitle('Template');
                    for ($r = 1; $r <= 25; $r++) {
                        for ($c = 1; $c <= 10; $c++) {
                            $sheet->setCellValue([$c, $r], "Dummy cell data {$c}-{$r} for testing template export");
                        }
                    }
                    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                    $writer->save($targetPath);
                    $this->filesDibuatSementara[] = $targetPath;
                } elseif (file_exists($sampleDocx)) {
                    copy($sampleDocx, $targetPath);
                    $this->filesDibuatSementara[] = $targetPath;
                }
            }
        }
    }

    private function namaDatabase(): string
    {
        return $this->namaDatabasePengujian();
    }

    private function userDenganRole(string $namaRole): User
    {
        return $this->ambilAtauBuatUserRole($namaRole);
    }

    // =========================================================================
    // 1. PENGUJIAN PENGHAPUSAN TEMPLATE & ROLLBACK MIGRATION
    // =========================================================================

    public function test_record_sk_penghapusan_dan_rekap_aset_sudah_dihapus_dari_database(): void
    {
        $this->assertDatabaseMissing('template_dokumen', ['kode_template' => 'sk_penghapusan']);
        $this->assertDatabaseMissing('template_dokumen', ['kode_template' => 'rekap_aset']);

        $expectedKodes = [
            'sppbi',
            'label',
            'kir',
            'bast_barang',
            'sppkd',
            'bast_kendaraan',
            'surat_izin_kendaraan',
        ];

        foreach ($expectedKodes as $kode) {
            $this->assertDatabaseHas('template_dokumen', ['kode_template' => $kode]);
        }
    }

    public function test_rollback_migration_dapat_memulihkan_kedua_record_dengan_data_aslinya(): void
    {
        $migration = require database_path('migrations/2026_11_14_000001_remove_unused_template_dokumen.php');

        // 1. Rollback migration memanggil down()
        $migration->down();

        // 2. Pastikan kedua record pulih di database
        $this->assertDatabaseHas('template_dokumen', [
            'id_template' => 2,
            'kode_template' => 'sk_penghapusan',
            'nama_template' => 'SK Penghapusan Aset',
            'tipe_berkas' => 'word',
            'status' => 'Belum Diunggah',
        ]);

        $this->assertDatabaseHas('template_dokumen', [
            'id_template' => 3,
            'kode_template' => 'rekap_aset',
            'nama_template' => 'Rekap Aset Barang',
            'tipe_berkas' => 'excel',
            'status' => 'Belum Diunggah',
        ]);

        // 3. Jalankan kembali up() agar state database tetap bersih
        $migration->up();

        $this->assertDatabaseMissing('template_dokumen', ['kode_template' => 'sk_penghapusan']);
        $this->assertDatabaseMissing('template_dokumen', ['kode_template' => 'rekap_aset']);
    }

    // =========================================================================
    // 2. PENGUJIAN HAK AKSES PER ROLE
    // =========================================================================

    public function test_admin_aset_diizinkan_mengakses_dan_mengelola_template_dokumen(): void
    {
        $admin = $this->userDenganRole('Admin Aset');
        $this->actingAs($admin);

        $template = TemplateDokumen::firstOrFail();

        // 1. Buka index
        $response = $this->get(route('template-dokumen.index'));
        $response->assertStatus(200);
        $response->assertSee('Template Dokumen');
        $response->assertSee($template->nama_template);

        // Pastikan card yang dihapus tidak ada di tampilan
        $response->assertDontSee('SK Penghapusan Aset');
        $response->assertDontSee('Rekap Aset Barang');

        // 2. Download template resmi
        $resDownload = $this->get(route('template-dokumen.download', $template->id_template));
        $resDownload->assertStatus(200);
    }

    public function test_role_non_admin_aset_ditolak_dengan_http_403_pada_seluruh_endpoint(): void
    {
        $template = TemplateDokumen::firstOrFail();
        $dummyFile = UploadedFile::fake()->create('dummy.docx', 100);

        $nonAdminRoles = ['Admin UPT', 'Admin Bidang', 'Pegawai'];

        foreach ($nonAdminRoles as $roleName) {
            $user = $this->userDenganRole($roleName);
            $this->actingAs($user);

            // Index
            $this->get(route('template-dokumen.index'))->assertStatus(403);

            // Update
            $this->post(route('template-dokumen.update', $template->id_template), [
                'file_template' => $dummyFile,
            ])->assertStatus(403);

            // Download
            $this->get(route('template-dokumen.download', $template->id_template))->assertStatus(403);

            // Destroy
            $this->delete(route('template-dokumen.destroy', $template->id_template))->assertStatus(403);
        }
    }

    public function test_tamu_tanpa_login_dialihkan_ke_login_dengan_http_302(): void
    {
        auth()->logout();
        $template = TemplateDokumen::firstOrFail();

        $this->get(route('template-dokumen.index'))->assertStatus(302)->assertRedirect(route('login'));
        $this->post(route('template-dokumen.update', $template->id_template))->assertStatus(302)->assertRedirect(route('login'));
        $this->get(route('template-dokumen.download', $template->id_template))->assertStatus(302)->assertRedirect(route('login'));
        $this->delete(route('template-dokumen.destroy', $template->id_template))->assertStatus(302)->assertRedirect(route('login'));
    }

    // =========================================================================
    // 3. PENGUJIAN VALIDASI UPLOAD KETAT SESUAI TIPE BERKAS
    // =========================================================================

    public function test_validasi_upload_mengikuti_tipe_berkas_masing_masing_template(): void
    {
        $admin = $this->userDenganRole('Admin Aset');
        $this->actingAs($admin);

        $tplWord = TemplateDokumen::where('tipe_berkas', 'word')->firstOrFail();
        $tplExcel = TemplateDokumen::where('tipe_berkas', 'excel')->firstOrFail();

        $oldWordFile = $tplWord->file_path;
        $oldExcelFile = $tplExcel->file_path;

        // 1. XLSX ditolak pada template Word
        $fileXlsx = UploadedFile::fake()->create('salah.xlsx', 100);
        $res1 = $this->post(route('template-dokumen.update', $tplWord->id_template), [
            'file_template' => $fileXlsx,
        ]);
        $res1->assertSessionHasErrors('file_template');
        $this->assertSame($oldWordFile, $tplWord->fresh()->file_path, 'File lama tidak boleh berubah saat validasi gagal.');

        // 2. DOCX ditolak pada template Excel
        $fileDocx = UploadedFile::fake()->create('salah.docx', 100);
        $res2 = $this->post(route('template-dokumen.update', $tplExcel->id_template), [
            'file_template' => $fileDocx,
        ]);
        $res2->assertSessionHasErrors('file_template');
        $this->assertSame($oldExcelFile, $tplExcel->fresh()->file_path, 'File lama tidak boleh berubah saat validasi gagal.');

        // 3. File lebih dari 10 MB ditolak
        $fileBesar = UploadedFile::fake()->create('besar.docx', 10241); // 10.001 KB
        $res3 = $this->post(route('template-dokumen.update', $tplWord->id_template), [
            'file_template' => $fileBesar,
        ]);
        $res3->assertSessionHasErrors('file_template');
    }

    // =========================================================================
    // 4. PENGUJIAN PENGGANTIAN FILE & INTEGRITAS FILE LAMA
    // =========================================================================

    public function test_penggantian_file_berhasil_dan_membersihkan_file_lama(): void
    {
        Storage::fake(TemplateDokumen::DISK);
        Storage::fake('public');

        $admin = $this->userDenganRole('Admin Aset');
        $this->actingAs($admin);

        // Buat template dummy untuk uji upload & ganti file tanpa merusak template asli
        $testTpl = TemplateDokumen::create([
            'kode_template' => 'test_mock_word_' . uniqid(),
            'nama_template' => 'Template Mock Word',
            'tipe_berkas' => 'word',
            'status' => 'Belum Diunggah',
        ]);

        try {
            // 1. Upload pertama (menggunakan file dengan konten biner terisi)
            $file1 = UploadedFile::fake()->createWithContent('versi_1.docx', "PK\x03\x04" . str_repeat('A', 1024));
            $this->post(route('template-dokumen.update', $testTpl->id_template), [
                'file_template' => $file1,
            ])->assertRedirect()->assertSessionHas('success');

            $testTpl->refresh();
            $path1 = $testTpl->file_path;
            $this->assertNotNull($path1);
            $this->assertTrue(Storage::disk(TemplateDokumen::DISK)->exists($path1));
            $this->assertSame('Siap Digunakan', $testTpl->status);

            // 2. Ganti dengan file kedua
            $file2 = UploadedFile::fake()->createWithContent('versi_2.docx', "PK\x03\x04" . str_repeat('B', 2048));
            $this->post(route('template-dokumen.update', $testTpl->id_template), [
                'file_template' => $file2,
            ])->assertRedirect()->assertSessionHas('success');

            $testTpl->refresh();
            $path2 = $testTpl->file_path;
            $this->assertNotSame($path1, $path2);
            $this->assertTrue(Storage::disk(TemplateDokumen::DISK)->exists($path2));
            $this->assertFalse(Storage::disk(TemplateDokumen::DISK)->exists($path1), 'File lama harus dihapus setelah file baru terpasang.');

            // 3. Hapus file via destroy
            $this->delete(route('template-dokumen.destroy', $testTpl->id_template))
                ->assertRedirect()->assertSessionHas('success');

            $testTpl->refresh();
            $this->assertNull($testTpl->file_path);
            $this->assertSame('Belum Diunggah', $testTpl->status);
            $this->assertFalse(Storage::disk(TemplateDokumen::DISK)->exists($path2));
        } finally {
            if ($testTpl->file_path && Storage::disk(TemplateDokumen::DISK)->exists($testTpl->file_path)) {
                Storage::disk(TemplateDokumen::DISK)->delete($testTpl->file_path);
            }
            $testTpl->delete();
        }
    }

    // =========================================================================
    // 5. PENGUJIAN DOWNLOAD & PENCEGAHAN URL PUBLIK
    // =========================================================================

    public function test_file_template_tidak_dapat_diakses_melalui_url_storage_publik(): void
    {
        $template = TemplateDokumen::firstOrFail();

        // 1. Pastikan file fisik TIDAK ADA di disk public
        $this->assertFalse(
            Storage::disk('public')->exists($template->file_path),
            'File template tidak boleh berada di disk public.'
        );

        // 2. Pastikan file fisik ADA di disk privat templates
        $this->assertTrue(
            Storage::disk(TemplateDokumen::DISK)->exists($template->file_path),
            'File template harus berada di disk privat templates.'
        );

        // 3. Pastikan symlink public/storage/templates tidak menyimpan file fisik tersebut
        $publicFilePath = public_path('storage/' . $template->file_path);
        $this->assertFileDoesNotExist(
            $publicFilePath,
            'File template tidak boleh dapat diakses melalui web server public path.'
        );
    }

    // =========================================================================
    // 6. REGRESSION TEST SELURUH TUJUH TEMPLATE AKTIF
    // =========================================================================

    public function test_seluruh_tujuh_template_aktif_dapat_dibaca_dan_memiliki_file_valid(): void
    {
        $kodes = [
            'sppbi',
            'label',
            'kir',
            'bast_barang',
            'sppkd',
            'bast_kendaraan',
            'surat_izin_kendaraan',
        ];

        foreach ($kodes as $kode) {
            $tpl = TemplateDokumen::where('kode_template', $kode)->first();
            $this->assertNotNull($tpl, "Template {$kode} harus terdaftar di database.");
            $this->assertSame('Siap Digunakan', $tpl->status, "Status template {$kode} harus Siap Digunakan.");
            $this->assertTrue($tpl->fileExists(), "File fisik template {$kode} harus ada di storage privat.");
            $this->assertNotNull($tpl->realPath(), "realPath() template {$kode} tidak boleh null.");
            $this->assertFileExists($tpl->realPath(), "Path absolut file template {$kode} harus valid.");
            $this->assertGreaterThan(5000, filesize($tpl->realPath()), "Ukuran file template {$kode} harus lebih dari 5 KB.");
        }
    }
}
