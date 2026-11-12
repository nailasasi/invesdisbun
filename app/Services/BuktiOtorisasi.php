<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Penyimpanan bukti otorisasi (foto kamera).
 *
 * Foto dikirim dari browser sebagai data URL (base64) hasil canvas kamera.
 * Service ini:
 *   1. Memvalidasi ISI gambar (bukan sekadar ekstensi/MIME dari klien).
 *   2. Membatasi ukuran & dimensi minimum.
 *   3. Bila ekstensi GD tersedia, melakukan re-encode untuk membuang
 *      metadata (mis. EXIF) yang mungkin sensitif.
 *   4. Menyimpan pada disk PRIVAT `local` (storage/app/private), dengan
 *      nama file yang dibuat server.
 *
 * Tidak ada data dari klien (nama file, path, waktu) yang dipercaya.
 */
class BuktiOtorisasi
{
    /** Disk privat. Lihat config/filesystems.php => root storage/app/private. */
    public const DISK = 'local';

    /** Direktori dasar di dalam disk privat. */
    public const DIREKTORI = 'bukti-otorisasi';

    /** Batas ukuran file hasil decode. */
    public const MAKS_BYTE = 2 * 1024 * 1024; // 2 MB

    /** Dimensi minimum yang wajar untuk sebuah foto bukti. */
    public const MIN_LEBAR = 320;

    public const MIN_TINGGI = 240;

    /** MIME yang diizinkan. */
    public const MIME_DIIZINKAN = ['image/jpeg', 'image/png'];

    public function gdTersedia(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatefromstring');
    }

    /**
     * Validasi & decode data URL menjadi komponen gambar.
     *
     * @return array{0: ?array{data: string, mime: string, lebar: int, tinggi: int}, 1: ?string}
     *                                                                                           [info, null] bila valid; [null, pesanError] bila tidak.
     */
    public function dekode(string $dataUrl): array
    {
        if (! preg_match('#^data:(image/[a-zA-Z0-9.+-]+);base64,(.+)$#s', (string) $dataUrl, $m)) {
            return [null, 'Foto konfirmasi tidak valid. Silakan ambil foto ulang melalui kamera.'];
        }

        $headerMime = strtolower($m[1]);
        $biner = base64_decode($m[2], true);

        if ($biner === false || $biner === '') {
            return [null, 'Data foto rusak atau kosong. Silakan ambil foto ulang.'];
        }

        if (strlen($biner) > self::MAKS_BYTE) {
            return [null, 'Ukuran foto melebihi batas 2 MB. Silakan ambil foto ulang.'];
        }

        $ukur = @getimagesizefromstring($biner);
        if ($ukur === false) {
            return [null, 'Isi file foto tidak dapat dibaca sebagai gambar.'];
        }

        $mimeTerdeteksi = strtolower((string) ($ukur['mime'] ?? ''));
        if (! in_array($mimeTerdeteksi, self::MIME_DIIZINKAN, true)) {
            return [null, 'Foto harus berformat JPEG atau PNG.'];
        }

        // Header data URL tidak boleh berbohong soal jenis isi.
        if ($headerMime !== '' && $headerMime !== $mimeTerdeteksi
            && ! ($headerMime === 'image/jpg' && $mimeTerdeteksi === 'image/jpeg')) {
            return [null, 'Format foto tidak sesuai dengan isinya.'];
        }

        $lebar = (int) ($ukur[0] ?? 0);
        $tinggi = (int) ($ukur[1] ?? 0);
        if ($lebar < self::MIN_LEBAR || $tinggi < self::MIN_TINGGI) {
            return [null, 'Resolusi foto terlalu kecil. Minimal '.self::MIN_LEBAR.'x'.self::MIN_TINGGI.' piksel.'];
        }

        return [[
            'data' => $biner,
            'mime' => $mimeTerdeteksi,
            'lebar' => $lebar,
            'tinggi' => $tinggi,
        ], null];
    }

    /**
     * Simpan data URL ke disk privat. Mengembalikan path relatif.
     *
     * @throws \RuntimeException bila foto tidak valid atau gagal ditulis.
     */
    public function simpan(string $dataUrl): string
    {
        [$info, $error] = $this->dekode($dataUrl);
        if ($error !== null || $info === null) {
            throw new \RuntimeException($error ?? 'Foto konfirmasi tidak valid.');
        }

        $biner = $info['data'];
        $ext = $info['mime'] === 'image/png' ? 'png' : 'jpg';

        $diolah = $this->reencode($biner, $info['mime']);
        if ($diolah !== null) {
            $biner = $diolah;
        }

        $path = $this->pathBaru($ext);

        if (! Storage::disk(self::DISK)->put($path, $biner)) {
            throw new \RuntimeException('Foto bukti gagal disimpan.');
        }

        return $path;
    }

    /**
     * Hapus file bukti (kompensasi kegagalan / pembersihan). Aman untuk null.
     */
    public function hapus(?string $path): void
    {
        if ($path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    /**
     * Re-encode gambar via GD untuk membuang metadata. Mengembalikan null
     * bila GD tidak tersedia atau proses gagal (pemanggil memakai biner asli).
     */
    private function reencode(string $biner, string $mime): ?string
    {
        if (! $this->gdTersedia()) {
            return null;
        }

        $gambar = @imagecreatefromstring($biner);
        if ($gambar === false) {
            return null;
        }

        ob_start();
        if ($mime === 'image/png') {
            imagealphablending($gambar, false);
            imagesavealpha($gambar, true);
            imagepng($gambar);
        } else {
            imagejpeg($gambar, null, 85);
        }
        $hasil = ob_get_clean();
        imagedestroy($gambar);

        return ($hasil === false || $hasil === '') ? null : $hasil;
    }

    /**
     * Path unik dibuat server: bukti-otorisasi/YYYY/MM/<uuid>.<ext>.
     * Menggunakan waktu server (zona aplikasi Asia/Jakarta).
     */
    private function pathBaru(string $ext): string
    {
        return self::DIREKTORI.'/'.now()->format('Y').'/'.now()->format('m').'/'.Str::uuid().'.'.$ext;
    }
}
