<?php

namespace Tests\Unit;

use App\Services\AsetPenempatan;
use PHPUnit\Framework\TestCase;

/**
 * Pengurutan KIB (nomor kartu barang) untuk pemilihan aset saat Pasang Aset.
 *
 * Aturan: semua segmen numerik dibanding sebagai ANGKA, bukan string, agar
 * `...0002` muncul sebelum `...000010`.
 */
class KibOrderingTest extends TestCase
{
    public function test_kib_diurutkan_secara_numerik(): void
    {
        $a = AsetPenempatan::kunciKib('11.01.35.00.020301.00000.00000.2025-1.3.2.10.02.03.003.000010');
        $b = AsetPenempatan::kunciKib('11.01.35.00.020301.00000.00000.2025-1.3.2.10.02.03.003.0002');

        $this->assertLessThan(0, $b <=> $a, '0002 harus mendahului 000010 secara numerik.');
    }

    public function test_kib_urut_ascending(): void
    {
        $input = [
            '11.01.35.00.020301.00000.00000.2025-1.3.2.10.02.03.003.000010',
            '11.01.35.00.020301.00000.00000.2025-1.3.2.10.02.03.003.0002',
            '11.01.35.00.020301.00000.00000.2025-1.3.2.10.02.03.003.000006',
        ];

        usort($input, fn ($x, $y) => AsetPenempatan::kunciKib($x) <=> AsetPenempatan::kunciKib($y));

        $this->assertSame(
            [
                '11.01.35.00.020301.00000.00000.2025-1.3.2.10.02.03.003.0002',
                '11.01.35.00.020301.00000.00000.2025-1.3.2.10.02.03.003.000006',
                '11.01.35.00.020301.00000.00000.2025-1.3.2.10.02.03.003.000010',
            ],
            $input
        );
    }

    public function test_non_kib_menghasilkan_kunci_kosong(): void
    {
        $this->assertSame([], AsetPenempatan::kunciKib(null));
        $this->assertSame([], AsetPenempatan::kunciKib(''));
        $this->assertSame([], AsetPenempatan::kunciKib('DUMMY BARANG'));
        $this->assertSame([], AsetPenempatan::kunciKib('ABC-XYZ'));
    }
}