<?php

namespace Tests\Feature;

use App\Models\Tanah;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * Form Tanah tidak boleh mengosongkan/menghapus nilai lama yang belum
 * dikenal (mis. hasil import berkapitalisasi berbeda seperti "BAIK").
 *
 * Pengujian hanya merender Blade; tidak menyentuh database.
 */
class TanahFormPreservationTest extends TestCase
{
    /**
     * Render partial form di luar siklus request. Variabel `$errors` yang
     * biasanya dibagikan middleware ShareErrorsFromSession harus disediakan
     * manual (dipakai untuk menampilkan error password_otorisasi).
     */
    private function renderForm(Tanah $tanah): string
    {
        return view('tanah.partials.form', [
            'tanah' => $tanah,
            'errors' => new ViewErrorBag,
        ])->render();
    }

    public function test_nilai_kondisi_lama_yang_tak_dikenal_tetap_terpilih(): void
    {
        $tanah = new Tanah([
            'kondisi' => 'BAIK',
            'penggunaan_air' => 'Air Sumur Bor',
        ]);

        $html = $this->renderForm($tanah);

        // Nilai lama tetap muncul sebagai opsi...
        $this->assertStringContainsString('value="BAIK"', $html);
        $this->assertStringContainsString('value="Air Sumur Bor"', $html);

        // ...dan terpilih, sehingga tidak terhapus saat form disimpan.
        $this->assertMatchesRegularExpression('/value="BAIK"\s+selected/', $html);
        $this->assertMatchesRegularExpression('/value="Air Sumur Bor"\s+selected/', $html);
    }

    public function test_opsi_standar_yang_dikenal_tetap_terpilih(): void
    {
        $tanah = new Tanah([
            'kondisi' => 'Rusak Ringan',
            'penggunaan_air' => 'Mata Air',
        ]);

        $html = $this->renderForm($tanah);

        $this->assertMatchesRegularExpression('/value="Rusak Ringan"\s+selected/', $html);
        $this->assertMatchesRegularExpression('/value="Mata Air"\s+selected/', $html);
    }

    public function test_form_kosong_tidak_membuat_opsi_tambahan(): void
    {
        $tanah = new Tanah;

        $html = $this->renderForm($tanah);

        // Hanya opsi standar + placeholder: Penggunaan Air (4+1) + Kondisi (3+1) = 9.
        $this->assertSame(9, substr_count($html, '<option'));
        $this->assertStringContainsString('-- Pilih Penggunaan Air --', $html);
        $this->assertStringContainsString('-- Pilih Kondisi --', $html);
    }
}
