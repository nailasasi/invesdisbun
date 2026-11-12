<?php

namespace App\Rules;

use App\Services\BuktiOtorisasi;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Memvalidasi bahwa nilai adalah foto kamera (data URL) yang sah:
 * JPEG/PNG, ukuran <= 2 MB, dan dimensi minimum wajar.
 *
 * Validasi berbasis ISI file (getimagesizefromstring), bukan ekstensi atau
 * MIME yang dikirim browser.
 */
class FotoKameraValid implements ValidationRule
{
    public function __construct(private readonly ?BuktiOtorisasi $bukti = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('Silakan ambil foto konfirmasi terlebih dahulu sebelum menyimpan.');

            return;
        }

        $service = $this->bukti ?? app(BuktiOtorisasi::class);
        [, $error] = $service->dekode($value);

        if ($error !== null) {
            $fail($error);
        }
    }
}
