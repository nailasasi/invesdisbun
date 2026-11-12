<?php

namespace Tests\Support;

/**
 * Pembuat data URL gambar untuk pengujian bukti otorisasi.
 *
 * Gambar JPEG/PNG dibuat dengan GD (tersedia di lingkungan ini). Data URL
 * meniru kiriman kamera live (canvas.toDataURL) pada browser.
 */
trait MembuatFotoBukti
{
    /**
     * Data URL gambar JPEG/PNG yang sah, dibuat server-side untuk pengujian.
     */
    protected function dataUrlGambar(string $mime = 'image/jpeg', int $lebar = 640, int $tinggi = 480): string
    {
        $img = imagecreatetruecolor($lebar, $tinggi);
        $warna = imagecolorallocate($img, 20, 120, 200);
        imagefilledrectangle($img, 0, 0, $lebar, $tinggi, $warna);

        ob_start();
        if ($mime === 'image/png') {
            imagepng($img);
        } else {
            imagejpeg($img, null, 85);
        }
        $biner = (string) ob_get_clean();
        imagedestroy($img);

        return 'data:'.$mime.';base64,'.base64_encode($biner);
    }

    protected function dataUrlJpegValid(): string
    {
        return $this->dataUrlGambar('image/jpeg', 640, 480);
    }

    protected function dataUrlPngValid(): string
    {
        return $this->dataUrlGambar('image/png', 640, 480);
    }

    /** Data URL dengan header gambar, tetapi isinya data acak > 2 MB. */
    protected function dataUrlTerlaluBesar(): string
    {
        $biner = random_bytes((2 * 1024 * 1024) + 1024);

        return 'data:image/jpeg;base64,'.base64_encode($biner);
    }

    /** Data URL JPEG valid yang resolusi-nya di bawah ambang minimum. */
    protected function dataUrlResolusiKecil(): string
    {
        return $this->dataUrlGambar('image/jpeg', 40, 30);
    }

    /** Data URL dengan MIME non-gambar. */
    protected function dataUrlBukanGambar(): string
    {
        return 'data:text/plain;base64,'.base64_encode('ini bukan gambar');
    }

    /** Data URL berformat PNG tetapi isinya JPEG (header berbohong). */
    protected function dataUrlHeaderTidakCocok(): string
    {
        $jpeg = substr($this->dataUrlGambar('image/jpeg'), strlen('data:image/jpeg;base64,'));
        $biner = base64_decode($jpeg);

        return 'data:image/png;base64,'.base64_encode($biner);
    }

    /** Data URL "JPEG" yang isinya rusak/terpotong. */
    protected function dataUrlRusak(): string
    {
        return 'data:image/jpeg;base64,'.base64_encode("\xFF\xD8\xFF\x00 rusak");
    }
}
