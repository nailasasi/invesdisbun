<?php

namespace Tests\Feature;

use App\Models\MasterKegiatanRkbmd;
use App\Models\MasterKodeBarangRkbmd;
use Database\Seeders\MasterKegiatanRkbmdSeeder;
use Database\Seeders\MasterKodeBarangRkbmdSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Pengujian Integrasi & Fondasi Master Data RKBMD:
 * - Menjalankan pengujian integrasi penuh pada database testing terpisah (`invesdisbun_test`).
 * - Membuktikan kedua file PHP master data dapat dibaca via require dan berisi data yang tepat:
 *   - master_kode_barang.php: tepat 12.307 data unik.
 *   - master_kegiatan.php: tepat 45 kombinasi unik A+B+D.
 * - Membuktikan 4 kode pendek ('1.3.2.04', '1.3.2.04.01', '1.3.2.08.02', '1.3.2.10.02') tersimpan utuh sebagai string.
 * - Membuktikan kedua Seeder membaca berkas PHP terpisah tanpa PhpSpreadsheet / file Excel.
 * - Membuktikan idempotensi (eksekusi kedua kali tidak menambah jumlah record).
 * - Membuktikan integritas transaksi database (rollback jika terjadi kegagalan).
 * - Membuktikan penolakan jalur berkas alternatif yang tidak valid atau bukan array.
 */
class MasterDataRkbmdTest extends TestCase
{
    private const DB_TEST = 'invesdisbun_test';

    protected function setUp(): void
    {
        parent::setUp();

        // Pastikan koneksi menggunakan database testing terpisah
        $this->siapkanDatabaseTesting();
    }

    protected function tearDown(): void
    {
        // Bersihkan tabel testing setelah tes selesai
        if (config('database.connections.mysql.database') === self::DB_TEST) {
            Schema::dropIfExists('master_kode_barang_rkbmd');
            Schema::dropIfExists('master_kegiatan_rkbmd');
        }

        parent::tearDown();
    }

    private function siapkanDatabaseTesting(): void
    {
        // Alihkan koneksi default ke MySQL database testing
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => self::DB_TEST,
        ]);

        DB::purge('mysql');
        DB::reconnect('mysql');

        // Buat database testing jika belum ada
        DB::statement('CREATE DATABASE IF NOT EXISTS `'.self::DB_TEST.'` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function test_model_master_kode_barang_memiliki_properti_yang_tepat(): void
    {
        $model = new MasterKodeBarangRkbmd;

        $this->assertSame('master_kode_barang_rkbmd', $model->getTable());
        $this->assertSame('id_master_barang', $model->getKeyName());
        $this->assertEquals(['kode_barang', 'nama_barang'], $model->getFillable());
        $this->assertSame('string', $model->getCasts()['kode_barang'] ?? null);
        $this->assertSame('string', $model->getCasts()['nama_barang'] ?? null);
    }

    public function test_model_master_kegiatan_memiliki_properti_yang_tepat(): void
    {
        $model = new MasterKegiatanRkbmd;

        $this->assertSame('master_kegiatan_rkbmd', $model->getTable());
        $this->assertSame('id_kegiatan', $model->getKeyName());
        $this->assertEquals(['bidang_urusan', 'nama_program', 'nama_sub_kegiatan'], $model->getFillable());
        $this->assertSame('string', $model->getCasts()['bidang_urusan'] ?? null);
        $this->assertSame('string', $model->getCasts()['nama_program'] ?? null);
        $this->assertSame('string', $model->getCasts()['nama_sub_kegiatan'] ?? null);
    }

    public function test_file_migration_master_data_tersedia_dan_sesuai_aturan(): void
    {
        $migrasiBarang = database_path('migrations/2026_11_04_000001_create_master_kode_barang_rkbmd_table.php');
        $migrasiKegiatan = database_path('migrations/2026_11_04_000002_create_master_kegiatan_rkbmd_table.php');

        $this->assertFileExists($migrasiBarang);
        $this->assertFileExists($migrasiKegiatan);

        $kontenBarang = file_get_contents($migrasiBarang);
        $this->assertStringContainsString('master_kode_barang_rkbmd', $kontenBarang);
        $this->assertStringContainsString('uk_mkbr_kode_barang', $kontenBarang);

        $kontenKegiatan = file_get_contents($migrasiKegiatan);
        $this->assertStringContainsString('master_kegiatan_rkbmd', $kontenKegiatan);
        $this->assertStringContainsString('uk_mkr_bidang_prog_sub', $kontenKegiatan);
        $this->assertStringContainsString("'bidang_urusan'", $kontenKegiatan);
        $this->assertStringContainsString("'nama_program'", $kontenKegiatan);
        $this->assertStringContainsString("'nama_sub_kegiatan'", $kontenKegiatan);
    }

    public function test_kedua_seeder_tidak_memiliki_ketergantungan_pada_phpspreadsheet_atau_excel(): void
    {
        $seederBarangFile = database_path('seeders/MasterKodeBarangRkbmdSeeder.php');
        $seederKegiatanFile = database_path('seeders/MasterKegiatanRkbmdSeeder.php');

        $kontenBarang = file_get_contents($seederBarangFile);
        $kontenKegiatan = file_get_contents($seederKegiatanFile);

        $this->assertStringNotContainsString('PhpOffice', $kontenBarang);
        $this->assertStringNotContainsString('IOFactory', $kontenBarang);
        $this->assertStringNotContainsString('.xlsx', $kontenBarang);

        $this->assertStringNotContainsString('PhpOffice', $kontenKegiatan);
        $this->assertStringNotContainsString('IOFactory', $kontenKegiatan);
        $this->assertStringNotContainsString('.xlsx', $kontenKegiatan);
    }

    public function test_berkas_data_php_dapat_dibaca_dan_memiliki_jumlah_data_tepat(): void
    {
        $fileBarang = database_path('seeders/data/master_kode_barang.php');
        $fileKegiatan = database_path('seeders/data/master_kegiatan.php');

        $this->assertFileExists($fileBarang);
        $this->assertFileExists($fileKegiatan);

        $dataBarang = require $fileBarang;
        $dataKegiatan = require $fileKegiatan;

        $this->assertIsArray($dataBarang);
        $this->assertIsArray($dataKegiatan);

        // Periksa jumlah data mentah
        $this->assertCount(12307, $dataBarang, 'master_kode_barang.php harus berisi tepat 12.307 data');
        $this->assertCount(45, $dataKegiatan, 'master_kegiatan.php harus berisi tepat 45 data');

        // Periksa keunikan data
        $uniqueKode = [];
        foreach ($dataBarang as $row) {
            $kode = trim((string)($row['kode_barang'] ?? ''));
            $uniqueKode[$kode] = true;
        }
        $this->assertCount(12307, $uniqueKode, 'Seluruh 12.307 kode barang harus unik');

        $uniqueKegiatan = [];
        foreach ($dataKegiatan as $row) {
            $key = trim((string)($row['bidang_urusan'] ?? '')) . '||' .
                   trim((string)($row['nama_program'] ?? '')) . '||' .
                   trim((string)($row['nama_sub_kegiatan'] ?? ''));
            $uniqueKegiatan[$key] = true;
        }
        $this->assertCount(45, $uniqueKegiatan, 'Seluruh 45 kombinasi kegiatan A+B+D harus unik');

        // Periksa 4 kode pendek
        $shortCodes = ['1.3.2.04', '1.3.2.04.01', '1.3.2.08.02', '1.3.2.10.02'];
        foreach ($shortCodes as $sc) {
            $this->assertArrayHasKey($sc, $uniqueKode, "Kode pendek {$sc} harus ada di master_kode_barang.php");
        }
    }

    public function test_seeder_kode_barang_menolak_jalur_alternatif_yang_tidak_valid(): void
    {
        $seederBarang = new MasterKodeBarangRkbmdSeeder;
        $this->expectException(\InvalidArgumentException::class);
        $seederBarang->run('C:/path/palsu/tidak_ada_kode_barang.php');
    }

    public function test_seeder_kegiatan_menolak_jalur_alternatif_yang_tidak_valid(): void
    {
        $seederKegiatan = new MasterKegiatanRkbmdSeeder;
        $this->expectException(\InvalidArgumentException::class);
        $seederKegiatan->run('C:/path/palsu/tidak_ada_kegiatan.php');
    }

    public function test_seeder_menolak_file_data_yang_bukan_mengembalikan_array(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'inv_bad_') . '.php';
        file_put_contents($tempFile, '<?php return "bukan array";');

        try {
            $seeder = new MasterKodeBarangRkbmdSeeder;
            $this->expectException(\UnexpectedValueException::class);
            $seeder->run($tempFile);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function test_integrasi_seeder_kegiatan_pada_database_testing(): void
    {
        Schema::dropIfExists('master_kegiatan_rkbmd');
        $migrasi = require database_path('migrations/2026_11_04_000002_create_master_kegiatan_rkbmd_table.php');
        $migrasi->up();

        $seeder = new MasterKegiatanRkbmdSeeder;

        // Eksekusi pertama menggunakan file PHP default (database/seeders/data/master_kegiatan.php)
        $hasil1 = $seeder->run();
        $this->assertSame(45, $hasil1['terbaca']);
        $this->assertSame(45, $hasil1['valid']);
        $this->assertSame(0, $hasil1['kosong']);
        $this->assertSame(0, $hasil1['duplikat']);
        $this->assertSame(45, $hasil1['berhasil']);
        $this->assertSame(0, $hasil1['gagal']);
        $this->assertSame(45, MasterKegiatanRkbmd::count());

        // Eksekusi kedua (Uji Idempotensi)
        $hasil2 = $seeder->run();
        $this->assertSame(45, $hasil2['berhasil']);
        $this->assertSame(45, MasterKegiatanRkbmd::count(), 'Eksekusi kedua tidak boleh menambah record');

        // Uji helper Model daftarProgram() & subKegiatanPerProgram()
        $daftarProgram = MasterKegiatanRkbmd::daftarProgram();
        $this->assertNotEmpty($daftarProgram);

        $programPertama = $daftarProgram->first();
        $subKegiatan = MasterKegiatanRkbmd::subKegiatanPerProgram($programPertama);
        $this->assertNotEmpty($subKegiatan);
        $this->assertSame($programPertama, $subKegiatan->first()->nama_program);
    }

    public function test_integrasi_seeder_kode_barang_pada_database_testing(): void
    {
        Schema::dropIfExists('master_kode_barang_rkbmd');
        $migrasi = require database_path('migrations/2026_11_04_000001_create_master_kode_barang_rkbmd_table.php');
        $migrasi->up();

        $seeder = new MasterKodeBarangRkbmdSeeder;

        // Eksekusi pertama menggunakan file PHP default (database/seeders/data/master_kode_barang.php)
        $hasil1 = $seeder->run();
        $this->assertSame(12307, $hasil1['terbaca']);
        $this->assertSame(12307, $hasil1['valid']);
        $this->assertSame(0, $hasil1['kosong']);
        $this->assertSame(0, $hasil1['duplikat']);
        $this->assertSame(12307, $hasil1['berhasil']);
        $this->assertSame(0, $hasil1['gagal']);
        $this->assertSame(12307, MasterKodeBarangRkbmd::count());

        // Uji keberadaan 4 kode pendek tersimpan utuh sebagai string
        $shortCodes = [
            '1.3.2.04' => 'ALAT PERTANIAN',
            '1.3.2.04.01' => 'ALAT PENGOLAHAN',
            '1.3.2.08.02' => 'UNIT ALAT LABORATORIUM KIMIA NUKLIR',
            '1.3.2.10.02' => 'PERALATAN KOMPUTER',
        ];

        foreach ($shortCodes as $kode => $nama) {
            $item = MasterKodeBarangRkbmd::where('kode_barang', $kode)->first();
            $this->assertNotNull($item, "Kode pendek {$kode} harus tersimpan di database testing");
            $this->assertSame($nama, $item->nama_barang);
            $this->assertIsString($item->kode_barang);
        }

        // Eksekusi kedua (Uji Idempotensi)
        $hasil2 = $seeder->run();
        $this->assertSame(12307, $hasil2['berhasil']);
        $this->assertSame(12307, MasterKodeBarangRkbmd::count(), 'Eksekusi kedua tidak boleh menambah record');

        // Uji scope pencarian
        $hasilCari = MasterKodeBarangRkbmd::cari('KOMPUTER')->get();
        $this->assertNotEmpty($hasilCari);
        $this->assertTrue($hasilCari->contains('kode_barang', '1.3.2.10.02'));
    }

    public function test_transaksi_rollback_jika_terjadi_kegagalan_database(): void
    {
        Schema::dropIfExists('master_kegiatan_rkbmd');
        $migrasi = require database_path('migrations/2026_11_04_000002_create_master_kegiatan_rkbmd_table.php');
        $migrasi->up();

        $initialCount = MasterKegiatanRkbmd::count();
        $this->assertSame(0, $initialCount);

        try {
            DB::transaction(function () {
                MasterKegiatanRkbmd::create([
                    'bidang_urusan' => 'Urusan Tes',
                    'nama_program' => 'Program Tes',
                    'nama_sub_kegiatan' => 'Sub Tes',
                ]);

                // Sengaja throw exception untuk memicu rollback
                throw new \RuntimeException('Simulasi kegagalan transaksi');
            });
        } catch (\RuntimeException $e) {
            // Ditangkap untuk memvalidasi rollback
        }

        $this->assertSame(0, MasterKegiatanRkbmd::count(), 'Data harus di-rollback saat terjadi exception');
    }
}
