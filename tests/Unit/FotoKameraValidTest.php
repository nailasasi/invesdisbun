<?php

namespace Tests\Unit;

use App\Rules\FotoKameraValid;
use App\Services\BuktiOtorisasi;
use Illuminate\Support\Facades\Validator;
use Tests\Support\MembuatFotoBukti;
use Tests\TestCase;

/**
 * Tahap 3 — aturan validasi foto kamera (dipakai pada 5 aktivitas:
 * tambah/edit tanah, tambah/edit/hapus retribusi).
 */
class FotoKameraValidTest extends TestCase
{
    use MembuatFotoBukti;

    private function validasi(mixed $nilai): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make(
            ['foto_bukti_otentikasi' => $nilai],
            ['foto_bukti_otentikasi' => ['required', 'string', new FotoKameraValid(new BuktiOtorisasi)]]
        );
    }

    public function test_foto_kosong_wajib_diambil(): void
    {
        // Hanya rule ini (tanpa `required`/`string`) agar pesan dari
        // FotoKameraValid yang diuji. Pada controller, pesan `required`
        // juga dioverride menjadi kalimat yang sama.
        $validator = Validator::make(
            ['foto_bukti_otentikasi' => null],
            ['foto_bukti_otentikasi' => [new FotoKameraValid(new BuktiOtorisasi)]]
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Silakan ambil foto konfirmasi terlebih dahulu sebelum menyimpan.',
            $validator->errors()->first('foto_bukti_otentikasi')
        );
    }

    public function test_foto_jpeg_valid_lolos(): void
    {
        $this->assertFalse($this->validasi($this->dataUrlJpegValid())->fails());
    }

    public function test_foto_bukan_gambar_ditolak(): void
    {
        $this->assertTrue($this->validasi($this->dataUrlBukanGambar())->fails());
    }

    public function test_foto_rusak_ditolak(): void
    {
        $this->assertTrue($this->validasi($this->dataUrlRusak())->fails());
    }
}
