<?php

namespace Tests\Unit;

use App\Models\PenempatanAset;
use App\Models\Ruangan;
use App\Services\AsetPenempatan;
use PHPUnit\Framework\TestCase;

/**
 * Aturan visibilitas SPPBI:
 *
 * SPPBI hanya boleh untuk aset dengan penempatan aktif yang SUDAH DIFINALISASI
 * (is_finalized = 1) dan ruangannya aktif. Penempatan NULL (Perlu Verifikasi)
 * atau 0 (Belum Difinalisasi) belum memenuhi syarat.
 *
 * Test ini murni in-memory (tanpa database).
 */
class SppbiKvisibilityTest extends TestCase
{
    private function penempatan(?int $isFinalized, ?string $statusRuangan = 'Aktif'): PenempatanAset
    {
        $penempatan = new PenempatanAset();
        $penempatan->setAttribute('is_finalized', $isFinalized);

        // Relasi ALWAYS di-set (null bila aset tidak punya ruangan) supaya
        // test ini tidak menyentuh database sama sekali.
        $penempatan->setRelation(
            'ruangan',
            $statusRuangan === null
                ? null
                : (new Ruangan())->forceFill(['status' => $statusRuangan])
        );

        return $penempatan;
    }

    public function test_tanpa_penempatan_aktif_sppbi_ditolak(): void
    {
        $this->assertFalse(AsetPenempatan::penempatanFinalValid(null, true));
    }

    public function test_belum_difinalisasi_sppbi_ditolak(): void
    {
        $this->assertFalse(AsetPenempatan::penempatanFinalValid($this->penempatan(0), true));
    }

    public function test_perlu_verifikasi_null_sppbi_ditolak(): void
    {
        $this->assertFalse(AsetPenempatan::penempatanFinalValid($this->penempatan(null), true));
    }

    public function test_sudah_difinalisasi_dengan_ruangan_aktif_sppbi_boleh(): void
    {
        $this->assertTrue(AsetPenempatan::penempatanFinalValid($this->penempatan(1), true));
    }

    public function test_sudah_difinalisasi_tanpa_ruangan_sppbi_ditolak(): void
    {
        $this->assertFalse(AsetPenempatan::penempatanFinalValid($this->penempatan(1, null), true));
    }

    public function test_ruangan_nonaktif_sppbi_ditolak(): void
    {
        $this->assertFalse(AsetPenempatan::penempatanFinalValid($this->penempatan(1, 'Nonaktif'), true));
    }

    public function test_kolom_finalisasi_belum_tersedia_semua_ditolak(): void
    {
        $this->assertFalse(AsetPenempatan::penempatanFinalValid($this->penempatan(1), false));
    }
}
