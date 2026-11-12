<?php

namespace Tests\Feature;

use App\Http\Controllers\RkbmdController;
use App\Models\ArsipRkbmd;
use App\Models\Skpd;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Perintah `arsip:migrasi-privat` menyalin file arsip lama dari disk publik
 * ke disk privat dengan verifikasi SHA-256, tanpa menimpa dan tanpa mengubah
 * database.
 *
 * Seluruh file fixture dibuat di disk FAKE sehingga file arsip nyata tidak
 * tersentuh. Database MySQL nyata dipakai dalam transaksi yang di-rollback.
 */
class MigrasiArsipRkbmdTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabase(),
        ]);

        DB::beginTransaction();

        Storage::fake(RkbmdController::DISK_ARSIP);
        Storage::fake(RkbmdController::DISK_ARSIP_LAMA);
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

    private function skpd(): Skpd
    {
        return Skpd::firstOrCreate(['nama_skpd' => 'UPT P2BTP'], ['jenis_skpd' => 'UPT']);
    }

    private function arsip(string $filePath): ArsipRkbmd
    {
        return ArsipRkbmd::create([
            'id_skpd' => $this->skpd()->id_skpd,
            'tahun_anggaran' => 2026,
            'nama_dokumen' => 'SK Migrasi',
            'file_path' => $filePath,
        ]);
    }

    public function test_dry_run_tidak_menyalin_file_dan_tidak_mengubah_database(): void
    {
        $path = 'arsip-rkbmd/lama-dryrun.pdf';
        Storage::disk(RkbmdController::DISK_ARSIP_LAMA)->put($path, '%PDF-1.4 isi lama');
        $arsip = $this->arsip($path);

        $this->artisan('arsip:migrasi-privat', ['--id' => $arsip->id_arsip])
            ->expectsOutputToContain('DRY-RUN')
            ->assertSuccessful();

        Storage::disk(RkbmdController::DISK_ARSIP)->assertMissing($path);
        Storage::disk(RkbmdController::DISK_ARSIP_LAMA)->assertExists($path);
        $this->assertSame($path, $arsip->fresh()->file_path);
    }

    public function test_apply_menyalin_dengan_hash_identik_tanpa_mengubah_db(): void
    {
        $path = 'arsip-rkbmd/lama-copy.pdf';
        $isi = '%PDF-1.4 isi asli '.str_repeat('x', 1000);
        Storage::disk(RkbmdController::DISK_ARSIP_LAMA)->put($path, $isi);
        $arsip = $this->arsip($path);

        $this->artisan('arsip:migrasi-privat', ['--apply' => true, '--id' => $arsip->id_arsip])
            ->assertSuccessful();

        Storage::disk(RkbmdController::DISK_ARSIP)->assertExists($path);
        Storage::disk(RkbmdController::DISK_ARSIP_LAMA)->assertExists($path);

        $this->assertSame(
            hash('sha256', $isi),
            hash('sha256', (string) Storage::disk(RkbmdController::DISK_ARSIP)->get($path))
        );

        // file_path database TIDAK berubah.
        $this->assertSame($path, $arsip->fresh()->file_path);
    }

    public function test_apply_tidak_menimpa_file_privat_yang_sudah_ada(): void
    {
        $path = 'arsip-rkbmd/sudah-ada.pdf';
        Storage::disk(RkbmdController::DISK_ARSIP_LAMA)->put($path, '%PDF-1.4 versi publik');
        Storage::disk(RkbmdController::DISK_ARSIP)->put($path, '%PDF-1.4 versi privat asli');
        $arsip = $this->arsip($path);

        $this->artisan('arsip:migrasi-privat', ['--apply' => true, '--id' => $arsip->id_arsip])
            ->expectsOutputToContain('dilewati')
            ->assertSuccessful();

        $this->assertSame(
            '%PDF-1.4 versi privat asli',
            (string) Storage::disk(RkbmdController::DISK_ARSIP)->get($path)
        );
    }

    public function test_file_sumber_tidak_ada_dilaporkan_gagal(): void
    {
        $arsip = $this->arsip('arsip-rkbmd/tidak-ada.pdf');

        $this->artisan('arsip:migrasi-privat', ['--apply' => true, '--id' => $arsip->id_arsip])
            ->assertFailed();
    }

    public function test_verifikasi_gagal_bila_belum_dimigrasi(): void
    {
        $path = 'arsip-rkbmd/belum.pdf';
        Storage::disk(RkbmdController::DISK_ARSIP_LAMA)->put($path, '%PDF-1.4 isi');
        $arsip = $this->arsip($path);

        $this->artisan('arsip:verifikasi-privat', ['--id' => $arsip->id_arsip])
            ->assertFailed();
    }

    public function test_verifikasi_berhasil_dan_hash_cocok_setelah_salin(): void
    {
        $path = 'arsip-rkbmd/verifikasi.pdf';
        $isi = '%PDF-1.4 isi verifikasi';
        Storage::disk(RkbmdController::DISK_ARSIP_LAMA)->put($path, $isi);
        $arsip = $this->arsip($path);

        $this->artisan('arsip:migrasi-privat', ['--apply' => true, '--id' => $arsip->id_arsip])
            ->assertSuccessful();

        $this->artisan('arsip:verifikasi-privat', ['--id' => $arsip->id_arsip])
            ->expectsOutputToContain('OK')
            ->assertSuccessful();
    }

    public function test_setelah_salin_endpoint_unduh_memakai_file_privat(): void
    {
        $path = 'arsip-rkbmd/endpoint.pdf';
        $isi = '%PDF-1.4 konten endpoint';
        Storage::disk(RkbmdController::DISK_ARSIP_LAMA)->put($path, $isi);
        $arsip = $this->arsip($path);

        $role = \App\Models\Role::firstOrCreate(['nama_role' => 'Admin Aset']);
        $user = \App\Models\User::create([
            'id_role' => $role->id_role,
            'username' => 'admin_migrasi_'.$arsip->id_arsip,
            'password' => 'rahasia123',
            'status_user' => 'aktif',
        ]);

        $this->actingAs($user);

        // Sebelum salin: fallback disk lama tetap melayani.
        $this->get(route('rkbmd.arsip.download', $arsip))->assertOk();

        $this->artisan('arsip:migrasi-privat', ['--apply' => true, '--id' => $arsip->id_arsip])->assertSuccessful();

        // Setelah salin: file privat tersedia dan endpoint tetap jalan.
        Storage::disk(RkbmdController::DISK_ARSIP)->assertExists($path);
        $respons = $this->get(route('rkbmd.arsip.download', $arsip));
        $respons->assertOk();
        $this->assertSame($isi, $respons->streamedContent());
    }
}
