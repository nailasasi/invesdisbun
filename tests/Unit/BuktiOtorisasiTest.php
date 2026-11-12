<?php

namespace Tests\Unit;

use App\Services\BuktiOtorisasi;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MembuatFotoBukti;
use Tests\TestCase;

/**
 * Tahap 3 — validasi & penyimpanan bukti otorisasi (foto kamera).
 *
 * Tidak menyentuh database. Foto dibuat via GD, disimpan ke disk privat
 * palsu (Storage::fake) sehingga tidak menulis file nyata.
 */
class BuktiOtorisasiTest extends TestCase
{
    use MembuatFotoBukti;

    private function service(): BuktiOtorisasi
    {
        return new BuktiOtorisasi;
    }

    // ------------------------------------------------------------------
    // DEKODE: validasi ISI (bukan ekstensi/MIME klien)
    // ------------------------------------------------------------------

    public function test_jpeg_valid_diterima(): void
    {
        [$info, $error] = $this->service()->dekode($this->dataUrlJpegValid());

        $this->assertNull($error);
        $this->assertNotNull($info);
        $this->assertSame('image/jpeg', $info['mime']);
        $this->assertSame(640, $info['lebar']);
        $this->assertSame(480, $info['tinggi']);
        $this->assertTrue(app(BuktiOtorisasi::class)->gdTersedia());
    }

    public function test_png_valid_diterima(): void
    {
        [$info, $error] = $this->service()->dekode($this->dataUrlPngValid());

        $this->assertNull($error);
        $this->assertSame('image/png', $info['mime']);
    }

    public function test_bukan_data_url_ditolak(): void
    {
        [, $error] = $this->service()->dekode('bukan-url-sama-sekali');

        $this->assertNotNull($error);
    }

    public function test_mime_non_gambar_ditolak(): void
    {
        [, $error] = $this->service()->dekode($this->dataUrlBukanGambar());

        $this->assertNotNull($error);
    }

    public function test_isi_rusak_ditolak(): void
    {
        [, $error] = $this->service()->dekode($this->dataUrlRusak());

        $this->assertNotNull($error);
    }

    public function test_header_tidak_cocok_dengan_isi_ditolak(): void
    {
        [, $error] = $this->service()->dekode($this->dataUrlHeaderTidakCocok());

        $this->assertNotNull($error);
    }

    public function test_ukuran_melebihi_batas_ditolak(): void
    {
        [, $error] = $this->service()->dekode($this->dataUrlTerlaluBesar());

        $this->assertNotNull($error);
        $this->assertStringContainsString('2 MB', (string) $error);
    }

    public function test_resolusi_terlalu_kecil_ditolak(): void
    {
        [, $error] = $this->service()->dekode($this->dataUrlResolusiKecil());

        $this->assertNotNull($error);
    }

    // ------------------------------------------------------------------
    // SIMPAN: disk privat + nama file dibuat server
    // ------------------------------------------------------------------

    public function test_simpan_menulis_ke_disk_privat_dengan_nama_server(): void
    {
        Storage::fake(BuktiOtorisasi::DISK);

        $path = $this->service()->simpan($this->dataUrlJpegValid());

        $this->assertStringStartsWith('bukti-otorisasi/', $path);
        $this->assertMatchesRegularExpression('#^bukti-otorisasi/\d{4}/\d{2}/[0-9a-f-]{36}\.jpg$#', $path);
        Storage::disk(BuktiOtorisasi::DISK)->assertExists($path);
    }

    public function test_simpan_png_berekstensi_png(): void
    {
        Storage::fake(BuktiOtorisasi::DISK);

        $path = $this->service()->simpan($this->dataUrlPngValid());

        $this->assertStringEndsWith('.png', $path);
        Storage::disk(BuktiOtorisasi::DISK)->assertExists($path);
    }

    public function test_simpan_menolak_data_tidak_valid(): void
    {
        Storage::fake(BuktiOtorisasi::DISK);

        $this->expectException(\RuntimeException::class);
        $this->service()->simpan($this->dataUrlBukanGambar());
    }

    public function test_hapus_menghapus_file(): void
    {
        Storage::fake(BuktiOtorisasi::DISK);

        $path = $this->service()->simpan($this->dataUrlJpegValid());
        $this->service()->hapus($path);

        Storage::disk(BuktiOtorisasi::DISK)->assertMissing($path);
    }

    public function test_hapus_null_aman(): void
    {
        Storage::fake(BuktiOtorisasi::DISK);

        $this->service()->hapus(null);
        $this->assertTrue(true);
    }
}
