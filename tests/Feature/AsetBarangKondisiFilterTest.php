<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AsetBarangKondisiFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabase(),
        ]);

        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function namaDatabase(): string
    {
        foreach (file(base_path('.env')) as $line) {
            if (str_starts_with(trim($line), 'DB_DATABASE=')) {
                $nilai = trim(explode('=', $line, 2)[1]);

                return $nilai === '' ? 'invesdisbun' : $nilai;
            }
        }

        return 'invesdisbun';
    }

    private function adminAset(): User
    {
        return User::whereHas('role', fn ($r) => $r->where('nama_role', 'Admin Aset'))->first();
    }

    public function test_dropdown_kondisi_terender_dengan_opsi_yang_benar(): void
    {
        $response = $this->actingAs($this->adminAset())
            ->get(route('aset-barang.index'));

        $response->assertOk();
        $response->assertSee('name="kondisi"', false);
        $response->assertSee('Semua Kondisi');
        $response->assertSee('value="Baik"', false);
        $response->assertSee('value="Rusak Ringan"', false);
        // Reset button should not be present when no filters are set
        $response->assertDontSeeText('Reset');
    }

    public function test_filter_kondisi_semua_menampilkan_seluruh_aset_tanpa_penyaringan(): void
    {
        $response = $this->actingAs($this->adminAset())
            ->get(route('aset-barang.index', ['kondisi' => '']));

        $response->assertOk();
        $asetList = $response->viewData('asetList');
        $this->assertNotEmpty($asetList);

        // Harus ada aset dengan kondisi Baik
        $hasBaik = $asetList->contains(fn ($a) => $a->kondisi === 'Baik');
        $this->assertTrue($hasBaik);
    }

    public function test_filter_kondisi_baik_hanya_menampilkan_aset_kondisi_baik(): void
    {
        $response = $this->actingAs($this->adminAset())
            ->get(route('aset-barang.index', ['kondisi' => 'Baik']));

        $response->assertOk();
        $response->assertSee('<option value="Baik" selected>Baik</option>', false);
        $response->assertSeeText('Reset');

        $asetList = $response->viewData('asetList');
        $this->assertNotEmpty($asetList);

        foreach ($asetList as $aset) {
            $this->assertSame('Baik', $aset->kondisi, "Aset ID {$aset->id_aset} memiliki kondisi {$aset->kondisi}, seharusnya Baik");
        }
    }

    public function test_filter_kondisi_rusak_ringan_hanya_menampilkan_aset_rusak_ringan(): void
    {
        $response = $this->actingAs($this->adminAset())
            ->get(route('aset-barang.index', ['kondisi' => 'Rusak Ringan']));

        $response->assertOk();
        $response->assertSee('<option value="Rusak Ringan" selected>Rusak Ringan</option>', false);
        $response->assertSeeText('Reset');

        $asetList = $response->viewData('asetList');
        $this->assertNotEmpty($asetList);

        foreach ($asetList as $aset) {
            $this->assertSame('Rusak Ringan', $aset->kondisi, "Aset ID {$aset->id_aset} memiliki kondisi {$aset->kondisi}, seharusnya Rusak Ringan");
        }
    }

    public function test_filter_kondisi_tidak_valid_diabaikan_dan_menampilkan_semua(): void
    {
        $responseDefault = $this->actingAs($this->adminAset())
            ->get(route('aset-barang.index'));

        $responseInvalid = $this->actingAs($this->adminAset())
            ->get(route('aset-barang.index', ['kondisi' => 'NilaiSembarang']));

        $this->assertSame(
            $responseDefault->viewData('asetList')->total(),
            $responseInvalid->viewData('asetList')->total()
        );
    }

    public function test_kombinasi_filter_kondisi_dengan_pencarian(): void
    {
        // Cari aset Rusak Ringan dengan search 'EPSON'
        $response = $this->actingAs($this->adminAset())
            ->get(route('aset-barang.index', [
                'search' => 'EPSON',
                'kondisi' => 'Rusak Ringan',
            ]));

        $response->assertOk();
        $asetList = $response->viewData('asetList');

        foreach ($asetList as $aset) {
            $this->assertSame('Rusak Ringan', $aset->kondisi);
            $cocokSearch = str_contains(strtoupper($aset->merk), 'EPSON')
                || str_contains(strtoupper($aset->nomor_kartu_barang ?? ''), 'EPSON')
                || str_contains(strtoupper($aset->barang?->nama_barang ?? ''), 'EPSON');
            $this->assertTrue($cocokSearch);
        }
    }

    public function test_pagination_mempertahankan_query_kondisi(): void
    {
        $response = $this->actingAs($this->adminAset())
            ->get(route('aset-barang.index', ['kondisi' => 'Baik', 'page' => 1]));

        $response->assertOk();
        $asetList = $response->viewData('asetList');

        if ($asetList->hasPages()) {
            $nextPageUrl = $asetList->nextPageUrl();
            $this->assertStringContainsString('kondisi=Baik', $nextPageUrl);
        }
    }

    public function test_kombinasi_filter_kondisi_dengan_lokasi(): void
    {
        $lokasiPertama = \App\Models\Lokasi::where('status', 'Aktif')->first();
        if (! $lokasiPertama) {
            $this->markTestSkipped('Tidak ada master lokasi aktif.');
        }

        $response = $this->actingAs($this->adminAset())
            ->get(route('aset-barang.index', [
                'kondisi' => 'Baik',
                'lokasi' => $lokasiPertama->id_lokasi,
            ]));

        $response->assertOk();
        $asetList = $response->viewData('asetList');

        foreach ($asetList as $aset) {
            $this->assertSame('Baik', $aset->kondisi);
            $this->assertSame((int) $lokasiPertama->id_lokasi, (int) $aset->penempatanAktif?->ruangan?->id_lokasi);
        }
    }

    public function test_tombol_reset_muncul_dan_mengarah_ke_index_tanpa_query(): void
    {
        $response = $this->actingAs($this->adminAset())
            ->get(route('aset-barang.index', ['kondisi' => 'Baik']));

        $response->assertOk();
        $response->assertSee(route('aset-barang.index'));
        $response->assertSeeText('Reset');
    }
}
