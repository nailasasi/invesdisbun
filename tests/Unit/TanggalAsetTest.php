<?php

namespace Tests\Unit;

use App\Services\TanggalAset;
use PHPUnit\Framework\TestCase;

class TanggalAsetTest extends TestCase
{
    public function test_simpan_mengisi_tanggal_pengadaan_saja(): void
    {
        $hasil = TanggalAset::samakanUntukSimpan([
            'tanggal_pengadaan' => '2025-03-10',
        ]);

        $this->assertSame('2025-03-10', $hasil['tanggal_pengadaan']);
        $this->assertArrayNotHasKey('tanggal_perolehan', $hasil);
    }

    public function test_simpan_mengisi_dari_perolehan_bila_pengadaan_tidak_ada(): void
    {
        $hasil = TanggalAset::samakanUntukSimpan([
            'tanggal_perolehan' => '2024-12-01',
        ]);

        $this->assertSame('2024-12-01', $hasil['tanggal_pengadaan']);
        $this->assertArrayNotHasKey('tanggal_perolehan', $hasil);
    }

    public function test_simpan_tanpa_tanggal_menghasilkan_null(): void
    {
        $hasil = TanggalAset::samakanUntukSimpan([
            'tanggal_pengadaan' => '',
        ]);

        $this->assertNull($hasil['tanggal_pengadaan']);
        $this->assertArrayNotHasKey('tanggal_perolehan', $hasil);
    }

    public function test_update_tidak_mengubah_tanggal_bila_nilainya_sama(): void
    {
        $hasil = TanggalAset::samakanUntukUpdate([
            'nama_barang' => 'Laptop',
            'tanggal_pengadaan' => '2025-03-10',
        ], '2025-03-10');

        $this->assertArrayNotHasKey('tanggal_pengadaan', $hasil);
        $this->assertArrayNotHasKey('tanggal_perolehan', $hasil);
        $this->assertSame('Laptop', $hasil['nama_barang']);
    }

    public function test_update_menyimpan_hanya_tanggal_pengadaan_bila_tanggal_berubah(): void
    {
        $hasil = TanggalAset::samakanUntukUpdate([
            'tanggal_pengadaan' => '2025-04-15',
        ], '2025-03-10');

        $this->assertSame('2025-04-15', $hasil['tanggal_pengadaan']);
        $this->assertArrayNotHasKey('tanggal_perolehan', $hasil);
    }

    public function test_update_dapat_mengosongkan_tanggal(): void
    {
        $hasil = TanggalAset::samakanUntukUpdate([
            'tanggal_pengadaan' => null,
        ], '2025-03-10');

        $this->assertArrayHasKey('tanggal_pengadaan', $hasil);
        $this->assertNull($hasil['tanggal_pengadaan']);
        $this->assertArrayNotHasKey('tanggal_perolehan', $hasil);
    }

    public function test_update_tanpa_field_tanggal_tidak_menyentuh_tanggal(): void
    {
        $hasil = TanggalAset::samakanUntukUpdate([
            'nama_barang' => 'Laptop',
        ], '2025-03-10');

        $this->assertArrayNotHasKey('tanggal_pengadaan', $hasil);
        $this->assertArrayNotHasKey('tanggal_perolehan', $hasil);
    }

    public function test_import_satu_tanggal_pengadaan_mengisi_keduanya(): void
    {
        $hasil = TanggalAset::untukImport('2025-05-01', null);

        $this->assertFalse($hasil['konflik']);
        $this->assertSame('2025-05-01', $hasil['tanggal']);
    }

    public function test_import_satu_tanggal_perolehan_mengisi_keduanya(): void
    {
        $hasil = TanggalAset::untukImport(null, '2025-06-01');

        $this->assertFalse($hasil['konflik']);
        $this->assertSame('2025-06-01', $hasil['tanggal']);
    }

    public function test_import_dua_tanggal_sama_tidak_konflik(): void
    {
        $hasil = TanggalAset::untukImport('2025-06-01', '2025-06-01');

        $this->assertFalse($hasil['konflik']);
        $this->assertSame('2025-06-01', $hasil['tanggal']);
    }

    public function test_import_dua_tanggal_berbeda_dianggap_konflik(): void
    {
        $hasil = TanggalAset::untukImport('2025-06-01', '2025-07-01');

        $this->assertTrue($hasil['konflik']);
        $this->assertNull($hasil['tanggal']);
    }

    public function test_import_tanpa_tanggal_menghasilkan_null(): void
    {
        $hasil = TanggalAset::untukImport(null, null);

        $this->assertFalse($hasil['konflik']);
        $this->assertNull($hasil['tanggal']);
    }
}
