<?php

namespace Tests\Unit;

use App\Models\PenempatanAset;
use App\Services\AsetPenempatan;
use Tests\TestCase;

/**
 * Aturan finalisasi penempatan saat mutasi.
 *
 * Alur mutasi (Pindah Ruangan, Ganti Pemegang) TIDAK mewariskan status
 * finalisasi penempatan lama: penempatan aktif baru langsung difinalisasi
 * (is_finalized = 1) beserta jejak finalisasi yang baru, sehingga mutasi
 * tidak memerlukan finalisasi ulang dan aset tidak diam-diam hilang dari
 * SPPBI pemegang baru.
 *
 * Helper pewarisan tetap tersedia untuk alur lain yang memang memakai aset
 * yang sama di ruangan yang sama. Test ini tidak menyentuh database.
 */
class SppbiSinkronisasiTest extends TestCase
{
    private function penempatan(?int $isFinalized, ?int $finalizedBy = 1): PenempatanAset
    {
        $p = new PenempatanAset();
        $p->setAttribute('is_finalized', $isFinalized);
        $p->setAttribute('finalized_at', '2026-10-21 10:00:00');
        $p->setAttribute('finalized_by', $finalizedBy);

        return $p;
    }

    public function test_finalisasi_langsung_set_status_sudah_difinalisasi(): void
    {
        $hasil = AsetPenempatan::finalisasiLangsung();

        $this->assertSame(1, $hasil['is_finalized']);
        $this->assertNotNull($hasil['finalized_at'], 'Jejak waktu finalisasi wajib terisi.');
        $this->assertArrayHasKey('finalized_by', $hasil);
    }

    public function test_warisan_final_carries_status_dan_identitas(): void
    {
        $hasil = AsetPenempatan::finalisasiWarisan($this->penempatan(1, 7));

        $this->assertSame(1, $hasil['is_finalized']);
        $this->assertSame(7, $hasil['finalized_by']);
        $this->assertNotNull($hasil['finalized_at']);
    }

    public function test_warisan_belum_final_diwariskan_sebagai_nol(): void
    {
        // Penempatan yang belum difinalisasi (0) tetap diwariskan sebagai 0
        // (tetap "Belum Difinalisasi"), jadi tidak kehilangan status.
        $hasil = AsetPenempatan::finalisasiWarisan($this->penempatan(0));

        $this->assertSame(0, $hasil['is_finalized']);
    }

    public function test_warisan_null_perlu_verifikasi_tidak_mewariskan(): void
    {
        // Penempatan lama NULL (Perlu Verifikasi) tidak boleh diwariskan.
        $this->assertSame([], AsetPenempatan::finalisasiWarisan($this->penempatan(null)));
    }

    public function test_warisan_tanpa_penempatan_tidak_mewariskan(): void
    {
        $this->assertSame([], AsetPenempatan::finalisasiWarisan(null));
    }
}