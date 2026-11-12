<?php

namespace Tests\Feature;

use App\Models\Tanah;
use App\Models\TanahHistory;
use Tests\TestCase;

/**
 * Pengujian perbaikan Tahap 2 Modul Tanah:
 * - Penghapusan method anomali TanahHistory::histories()
 * - Penghapusan in-template DB query di Blade show.blade.php
 * - Perbaikan colspan tabel kosong di Blade index.blade.php
 */
class TanahPerbaikanTahap2Test extends TestCase
{
    public function test_tanah_history_tidak_memiliki_method_histories(): void
    {
        $this->assertFalse(
            method_exists(TanahHistory::class, 'histories'),
            'Method histories() pada TanahHistory seharusnya sudah dihapus.'
        );
    }

    public function test_tanah_show_blade_tidak_memiliki_query_langsung(): void
    {
        $bladePath = resource_path('views/tanah/show.blade.php');
        $this->assertFileExists($bladePath);

        $konten = file_get_contents($bladePath);

        $this->assertStringNotContainsString(
            '$tanah->histories()->latest()->first()',
            $konten,
            'Query langsung database di Blade show.blade.php harus dipindahkan ke controller.'
        );
    }

    public function test_tanah_index_blade_colspan_adalah_11(): void
    {
        $bladePath = resource_path('views/tanah/index.blade.php');
        $this->assertFileExists($bladePath);

        $konten = file_get_contents($bladePath);

        $this->assertStringContainsString(
            '<td colspan="11"',
            $konten,
            'Colspan pada baris kosong tabel tanah/index.blade.php harus 11.'
        );

        $this->assertStringNotContainsString(
            '<td colspan="26"',
            $konten,
            'Colspan lama 26 tidak boleh lagi ada di tanah/index.blade.php.'
        );
    }
}

