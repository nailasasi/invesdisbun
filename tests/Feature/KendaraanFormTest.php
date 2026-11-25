<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Kendaraan;
use App\Models\PemegangAset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Form Tambah / Edit Kendaraan: field wajib, keunikan identitas, dan
 * pemisahan pemegang dari form.
 *
 * Aturan utama:
 * - 10 field wajib: nama kendaraan, nomor kartu barang, nomor rangka,
 *   nomor mesin, nomor polisi, nilai perolehan, tanggal pengadaan,
 *   tanggal habis pakai, kondisi, status aset.
 * - Nomor rangka & nomor mesin unik (kecuali terhadap dirinya sendiri saat
 *   edit); nomor kartu barang unik di tabel aset.
 * - Pemegang BUKAN field form: crafted request yang mengirim
 *   `pemegang`/`pemegang_awal`/`id_pegawai_awal` tidak boleh mengubah
 *   pemegang maupun membuat baris PemegangAset.
 * - Status aset hanya "aktif"/"nonaktif"; status sistem yang sudah ada
 *   (diusulkan_hapus/dihapuskan) harus dipertahankan saat form disimpan.
 *
 * Memakai koneksi MySQL nyata dibungkus transaksi yang di-rollback.
 */
class KendaraanFormTest extends TestCase
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
        return $this->namaDatabasePengujian();
    }

    private function adminAset(): User
    {
        return User::whereHas('role', fn ($r) => $r->where('nama_role', 'Admin Aset'))->first();
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'nama_kendaraan' => 'Mobil Uji Otomatis',
            'jenis_kendaraan' => 'Roda Empat (Mobil)',
            'nomor_kartu_barang' => 'KIB-OTOMATIS-'.uniqid(),
            'nomor_rangka' => 'RANGKA-'.uniqid(),
            'nomor_mesin' => 'MESIN-'.uniqid(),
            'nomor_polisi' => 'B 1 ABC',
            'merk' => 'Toyota',
            'tipe' => 'Test',
            'nilai_perolehan' => 100000,
            'tanggal_pengadaan' => '2024-01-15',
            'tanggal_habis_pakai' => '2030-01-15',
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'keterangan' => 'data uji',
        ], $override);
    }

    public function test_tambah_kendaraan_berhasil_tanpa_pemegang(): void
    {
        $admin = $this->adminAset();
        $this->actingAs($admin);

        $before = Kendaraan::count();
        $beforePemegang = PemegangAset::count();

        $res = $this->postJson(route('kendaraan.store'), $this->payload());

        $res->assertOk()->assertJson(['success' => true]);
        $this->assertSame($before + 1, Kendaraan::count());
        $this->assertSame($beforePemegang, PemegangAset::count(), 'Tambah kendaraan tidak boleh membuat PemegangAset.');

        $k = Kendaraan::orderByDesc('id_kendaraan')->first();
        $this->assertNull($k->pemegang, 'Kolom pemegang tidak boleh diisi dari form.');
        $this->assertNotNull($k->aset);
        $this->assertTrue((bool) $k->aset->is_kendaraan);
        $this->assertSame('KIB-OTOMATIS', substr($k->aset->nomor_kartu_barang, 0, 12));
    }

    public function test_field_wajib_kosong_ditolak(): void
    {
        $this->actingAs($this->adminAset());

        foreach (['nama_kendaraan', 'nomor_kartu_barang', 'nomor_rangka', 'nomor_mesin', 'nomor_polisi', 'nilai_perolehan', 'tanggal_pengadaan', 'tanggal_habis_pakai', 'kondisi', 'status_aset'] as $field) {
            $res = $this->postJson(route('kendaraan.store'), $this->payload([$field => '']));
            $this->assertContains($field, array_keys($res->json('errors') ?? []), "Field {$field} harus wajib.");
        }
    }

    public function test_nomor_rangka_dan_mesin_unik(): void
    {
        $this->actingAs($this->adminAset());

        $ada = Kendaraan::whereNotNull('nomor_rangka')->where('nomor_rangka', '!=', '')->first();
        $this->assertNotNull($ada, 'Butuh data kendaraan untuk uji unique.');

        $res = $this->postJson(route('kendaraan.store'), $this->payload(['nomor_rangka' => $ada->nomor_rangka]));
        $res->assertStatus(422);
        $this->assertArrayHasKey('nomor_rangka', $res->json('errors'));

        $adaMesin = Kendaraan::whereNotNull('nomor_mesin')->where('nomor_mesin', '!=', '')->first();
        $res2 = $this->postJson(route('kendaraan.store'), $this->payload(['nomor_mesin' => $adaMesin->nomor_mesin]));
        $res2->assertStatus(422);
        $this->assertArrayHasKey('nomor_mesin', $res2->json('errors'));
    }

    public function test_nomor_kartu_barang_unik(): void
    {
        $this->actingAs($this->adminAset());

        $aset = Aset::where('is_kendaraan', 1)->whereNotNull('nomor_kartu_barang')->first();
        $this->assertNotNull($aset);

        $res = $this->postJson(route('kendaraan.store'), $this->payload(['nomor_kartu_barang' => $aset->nomor_kartu_barang]));
        $res->assertStatus(422);
        $this->assertArrayHasKey('nomor_kartu_barang', $res->json('errors'));
    }

    public function test_edit_ignores_pemegang_dari_crafted_request(): void
    {
        $this->actingAs($this->adminAset());

        $k = Kendaraan::whereHas('aset')->first();
        $beforePemegang = PemegangAset::count();
        $pemegangLama = $k->pemegang;

        $res = $this->postJson(route('kendaraan.update', $k), $this->payload([
            'id_kendaraan' => $k->id_kendaraan,
            'nomor_rangka' => $k->nomor_rangka,
            'nomor_mesin' => $k->nomor_mesin,
            'nomor_kartu_barang' => $k->aset->nomor_kartu_barang,
            'pemegang' => 'PENYUSUP',
            'pemegang_awal' => 'PENYUSUP',
            'id_pegawai_awal' => 1,
        ]));

        $res->assertOk();
        $k->refresh();
        $this->assertSame($pemegangLama, $k->pemegang, 'Pemegang tidak boleh berubah lewat form.');
        $this->assertSame($beforePemegang, PemegangAset::count());
    }

    public function test_status_aset_hanya_aktif_atau_nonaktif(): void
    {
        $this->actingAs($this->adminAset());

        $res = $this->postJson(route('kendaraan.store'), $this->payload(['status_aset' => 'diusulkan_hapus']));
        $res->assertStatus(422);
        $this->assertArrayHasKey('status_aset', $res->json('errors'));
    }

    public function test_status_sistem_tetap_diperahankan(): void
    {
        $this->actingAs($this->adminAset());

        $k = Kendaraan::whereHas('aset')->first();
        $k->aset->update(['status_aset' => 'diusulkan_hapus']);

        $res = $this->postJson(route('kendaraan.update', $k), $this->payload([
            'id_kendaraan' => $k->id_kendaraan,
            'nomor_rangka' => $k->nomor_rangka,
            'nomor_mesin' => $k->nomor_mesin,
            'nomor_kartu_barang' => $k->aset->nomor_kartu_barang,
            'status_aset' => 'aktif',
        ]));

        $res->assertOk();
        $this->assertSame('diusulkan_hapus', $k->aset->refresh()->status_aset, 'Status sistem harus dipertahankan.');
    }

    public function test_edit_memperbarui_nilai_dan_tanggal(): void
    {
        $this->actingAs($this->adminAset());

        $k = Kendaraan::whereHas('aset')->first();

        $res = $this->postJson(route('kendaraan.update', $k), $this->payload([
            'id_kendaraan' => $k->id_kendaraan,
            'nomor_rangka' => $k->nomor_rangka,
            'nomor_mesin' => $k->nomor_mesin,
            'nomor_kartu_barang' => $k->aset->nomor_kartu_barang,
            'nilai_perolehan' => 123456,
            'tanggal_pengadaan' => '2025-02-02',
            'tanggal_habis_pakai' => '2031-02-02',
            'kondisi' => 'Rusak Ringan',
            'status_aset' => 'nonaktif',
        ]));

        $res->assertOk();
        $k->aset->refresh();
        $this->assertSame(123456.0, (float) $k->aset->nilai_perolehan);
        $this->assertSame('2031-02-02', $k->aset->tanggal_habis_pakai->toDateString());
        $this->assertSame('nonaktif', $k->aset->status_aset);
    }
}
