<?php

namespace Tests\Unit;

use App\Http\Controllers\TanahController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Verifikasi penanda baris contoh template import Tanah.
 *
 * Baris contoh pada template wajib dilewati dengan aman, namun rekaman
 * yang benar-benar valid tidak boleh ikut terlewat.
 */
class TanahImportContohTest extends TestCase
{
    private static function isBarisContoh(array $mapped): bool
    {
        $method = new ReflectionMethod(TanahController::class, 'isBarisContoh');
        $method->setAccessible(true);

        return (bool) $method->invoke(null, $mapped);
    }

    public function test_kib_bertanda_contoh_dilewati(): void
    {
        $this->assertTrue(self::isBarisContoh([
            'kib' => 'CONTOH',
            'nomor_sertifikat' => null,
        ]));
    }

    public function test_nomor_sertifikat_bertanda_contoh_dilewati(): void
    {
        $this->assertTrue(self::isBarisContoh([
            'kib' => '11.01.35.00',
            'nomor_sertifikat' => 'CONTOH',
        ]));
    }

    public function test_penanda_contoh_tidak_peka_huruf_besar_kecil(): void
    {
        $this->assertTrue(self::isBarisContoh([
            'kib' => 'contoh-01',
            'nomor_sertifikat' => null,
        ]));
    }

    public function test_baris_valid_tidak_dilewati(): void
    {
        $this->assertFalse(self::isBarisContoh([
            'kib' => '11.01.35.00.020301.00000.00000.1980-1.3.1.01.01.02.003.000001',
            'nomor_sertifikat' => 'HK.06.01/B/1980',
        ]));
    }

    public function test_deskripsi_memuat_contoh_tidak_membuat_baris_dilewati(): void
    {
        // Penanda TIDAK boleh bergantung pada deskripsi objek.
        $this->assertFalse(self::isBarisContoh([
            'kib' => '11.01.35.00',
            'nomor_sertifikat' => '12345',
            'deskripsi_objek' => 'Tanah contoh untuk percontohan',
        ]));
    }

    public function test_baris_kosong_tidak_dianggap_contoh(): void
    {
        $this->assertFalse(self::isBarisContoh([
            'kib' => null,
            'nomor_sertifikat' => null,
        ]));
    }
}
