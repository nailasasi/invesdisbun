<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Tahap 3 — foto bukti otorisasi TIDAK boleh dapat diakses tanpa login.
 *
 * Pengujian tidak menyentuh database: middleware `auth` menghentikan request
 * sebelum route model binding dijalankan.
 */
class BuktiOtorisasiAksesTest extends TestCase
{
    public function test_tanpa_login_diarahkan_ke_halaman_login(): void
    {
        $response = $this->get(route('bukti-otorisasi.show', 1));

        $response->assertRedirect(route('login'));
    }
}
