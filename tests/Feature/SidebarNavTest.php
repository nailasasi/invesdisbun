<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SidebarNavTest extends TestCase
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

    /** 1. Verifikasi kelompok Administrasi & Pengaturan telah dihapus dari sidebar */
    public function test_sidebar_tidak_mengandung_kelompok_administrasi_dan_pengaturan(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('nama_role', 'Admin Aset'))->first();
        $this->actingAs($admin);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);

        $html = $response->getContent();

        // Tidak boleh ada judul Administrasi & Pengaturan
        $this->assertStringNotContainsString('Administrasi & Pengaturan', $html);
        $this->assertStringNotContainsString('>Administrasi<', $html);
        $this->assertStringNotContainsString('>Pengaturan<', $html);
    }

    /** 2. Verifikasi MASTER DATA berada di bagian paling bawah untuk Admin Aset */
    public function test_master_data_berada_di_bawah_seluruh_menu_operasional_admin_aset(): void
    {
        $admin = User::whereHas('role', fn ($q) => $q->where('nama_role', 'Admin Aset'))->first();
        $this->actingAs($admin);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);

        $html = $response->getContent();

        $posInventaris = strpos($html, 'Inventaris Aset');
        $posPengelolaan = strpos($html, 'Pengelolaan Aset');
        $posDokumen = strpos($html, 'Dokumen');
        $posMasterData = strpos($html, 'Master Data');

        $this->assertNotFalse($posInventaris, 'Kelompok Inventaris Aset harus ada');
        $this->assertNotFalse($posPengelolaan, 'Kelompok Pengelolaan Aset harus ada');
        $this->assertNotFalse($posDokumen, 'Kelompok Dokumen harus ada');
        $this->assertNotFalse($posMasterData, 'Kelompok Master Data harus ada');

        // Master Data harus berada setelah seluruh kelompok menu operasional lainnya
        $this->assertGreaterThan($posInventaris, $posMasterData, 'Master Data harus berada setelah Inventaris Aset');
        $this->assertGreaterThan($posPengelolaan, $posMasterData, 'Master Data harus berada setelah Pengelolaan Aset');
        $this->assertGreaterThan($posDokumen, $posMasterData, 'Master Data harus berada setelah Dokumen');

        // Menu di dalam Master Data tetap lengkap (disatukan menjadi Manajemen Pegawai)
        $this->assertStringContainsString('Manajemen Pegawai', $html);

        // Menu operasional lainnya tetap ada
        $this->assertStringContainsString('Monitoring Aset', $html);
        $this->assertStringContainsString('Mutasi Aset', $html);
        $this->assertStringContainsString('Usulan Penghapusan', $html);
        $this->assertStringContainsString('Kendaraan', $html);
        $this->assertStringContainsString('Laporan', $html);
        $this->assertStringContainsString('Template Dokumen', $html);
    }

    /** 3. Verifikasi hak akses role Pegawai tidak dapat melihat Master Data */
    public function test_role_pegawai_tidak_melihat_master_data(): void
    {
        $pegawai = $this->ambilAtauBuatUserRole('Pegawai');
        $this->actingAs($pegawai);

        $response = $this->get(route('dashboard'));
        $response->assertStatus(200);

        $html = $response->getContent();

        $this->assertStringNotContainsString('Master Data', $html);
        $this->assertStringNotContainsString('Manajemen Pegawai', $html);
        $this->assertStringNotContainsString('Data Pegawai', $html);
        $this->assertStringNotContainsString('User Management', $html);
        $this->assertStringNotContainsString('Pengelolaan Aset', $html);
        $this->assertStringNotContainsString('Administrasi', $html);
    }
}

