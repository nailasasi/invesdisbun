<?php

namespace Tests\Unit;

use App\Models\PenempatanAset;
use App\Services\AsetPenempatan;
use PHPUnit\Framework\TestCase;

/**
 * Aturan UX tabel Aset Barang + penjaga backend:
 *
 *   1. Mutasi hanya tersedia bila aset punya pemegang aktif ATAU penempatan
 *      aktif. Tanpa keduanya, aset belum pernah ditempatkan -> gunakan
 *      Penempatan Awal (tombol +).
 *   2. Ikon Edit Penempatan & Finalisasi hanya untuk penempatan aktif dengan
 *      is_finalized = 0 (Belum Difinalisasi). Status 1 (sudah final) dan
 *      NULL (Perlu Verifikasi) tidak boleh diedit/difinalisasi.
 *
 * Test ini murni in-memory (tanpa database).
 */
class AsetBarangUxTest extends TestCase
{
    private function penempatan(?int $isFinalized): PenempatanAset
    {
        $penempatan = new PenempatanAset();
        $penempatan->setAttribute('is_finalized', $isFinalized);

        return $penempatan;
    }

    // ------------------------------------------------------------------
    // Aturan Mutasi
    // ------------------------------------------------------------------

    public function test_mutasi_ditolak_tanpa_pemegang_dan_tanpa_penempatan(): void
    {
        $this->assertFalse(AsetPenempatan::bolehMutasi(false, false));
    }

    public function test_mutasi_ditolak_saat_dipanggil_dengan_null(): void
    {
        $this->assertFalse(AsetPenempatan::bolehMutasi(null, null));
    }

    public function test_mutasi_boleh_ada_pemegang(): void
    {
        $this->assertTrue(AsetPenempatan::bolehMutasi(true, false));
    }

    public function test_mutasi_boleh_ada_penempatan(): void
    {
        $this->assertTrue(AsetPenempatan::bolehMutasi(false, true));
    }

    public function test_mutasi_boleh_ada_keduanya(): void
    {
        $this->assertTrue(AsetPenempatan::bolehMutasi(true, true));
    }

    // ------------------------------------------------------------------
    // Aturan Edit & Finalisasi
    // ------------------------------------------------------------------

    public function test_tanpa_penempatan_tidak_boleh_edit_finalisasi(): void
    {
        $this->assertFalse(AsetPenempatan::bolehEditFinalisasi(null, true));
    }

    public function test_status_0_penempatan_baru_boleh_edit_finalisasi(): void
    {
        $this->assertTrue(AsetPenempatan::bolehEditFinalisasi($this->penempatan(0), true));
    }

    public function test_status_1_sudah_final_tidak_boleh_edit_finalisasi(): void
    {
        $this->assertFalse(AsetPenempatan::bolehEditFinalisasi($this->penempatan(1), true));
    }

    public function test_status_null_perlu_verifikasi_tidak_boleh_edit_finalisasi(): void
    {
        $this->assertFalse(AsetPenempatan::bolehEditFinalisasi($this->penempatan(null), true));
    }

    public function test_kolom_finalisasi_belum_tersedia_tidak_boleh_edit_finalisasi(): void
    {
        $this->assertFalse(AsetPenempatan::bolehEditFinalisasi($this->penempatan(0), false));
    }
}