<?php

namespace Tests\Feature;

use App\Models\MasterKegiatanRkbmd;
use App\Models\MasterKodeBarangRkbmd;
use App\Models\Role;
use App\Models\Skpd;
use App\Models\User;
use App\Models\UsulanRkbmd;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RkbmdMasterIntegrationTest extends TestCase
{
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Gunakan MySQL database invesdisbun
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'invesdisbun',
        ]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        // Ambil user admin untuk request terotentikasi
        $role = Role::where('nama_role', 'Admin Aset')->first() ?? Role::first();
        $this->adminUser = User::where('id_role', $role->id_role)->first() ?? User::first();
    }

    public function test_endpoint_search_master_barang_mengembalikan_pagination_25_data(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson(route('rkbmd.master.search', ['page' => 1]));

        $response->assertOk();
        $response->assertJsonStructure([
            'results' => [
                '*' => ['id', 'text', 'kode_barang', 'nama_barang']
            ],
            'pagination' => ['more']
        ]);

        $data = $response->json();
        $this->assertCount(25, $data['results'], 'Halaman pertama harus mengembalikan tepat 25 data');
        $this->assertTrue($data['pagination']['more'], 'Harus terdapat halaman berikutnya (infinite scroll)');

        // Uji halaman kedua
        $response2 = $this->actingAs($this->adminUser)
            ->getJson(route('rkbmd.master.search', ['page' => 2]));

        $response2->assertOk();
        $data2 = $response2->json();
        $this->assertCount(25, $data2['results']);
        $this->assertNotSame($data['results'][0]['id'], $data2['results'][0]['id'], 'Halaman 2 harus berbeda dari halaman 1');
    }

    public function test_pencarian_berdasarkan_kode_dan_nama_barang(): void
    {
        // 1. Pencarian berdasarkan nama
        $resNama = $this->actingAs($this->adminUser)
            ->getJson(route('rkbmd.master.search', ['q' => 'KOMPUTER']));

        $resNama->assertOk();
        $itemsNama = $resNama->json('results');
        $this->assertNotEmpty($itemsNama);
        $this->assertStringContainsStringIgnoringCase('KOMPUTER', $itemsNama[0]['nama_barang']);

        // 2. Pencarian berdasarkan kode
        $resKode = $this->actingAs($this->adminUser)
            ->getJson(route('rkbmd.master.search', ['q' => '1.3.1.01']));

        $resKode->assertOk();
        $itemsKode = $resKode->json('results');
        $this->assertNotEmpty($itemsKode);
        $this->assertStringStartsWith('1.3.1.01', $itemsKode[0]['kode_barang']);
    }

    public function test_empat_kode_pendek_dapat_ditemukan_dan_tetap_bertipe_string(): void
    {
        $shortCodes = [
            '1.3.2.04' => 'ALAT PERTANIAN',
            '1.3.2.04.01' => 'ALAT PENGOLAHAN',
            '1.3.2.08.02' => 'UNIT ALAT LABORATORIUM KIMIA NUKLIR',
            '1.3.2.10.02' => 'PERALATAN KOMPUTER',
        ];

        foreach ($shortCodes as $kode => $nama) {
            $res = $this->actingAs($this->adminUser)
                ->getJson(route('rkbmd.master.search', ['q' => $kode]));

            $res->assertOk();
            $results = $res->json('results');
            $this->assertNotEmpty($results, "Pencarian untuk kode pendek {$kode} tidak boleh kosong");

            $matched = collect($results)->firstWhere('kode_barang', $kode);
            $this->assertNotNull($matched, "Kode pendek {$kode} harus ditemukan");
            $this->assertSame($kode, $matched['kode_barang']);
            $this->assertIsString($matched['kode_barang'], "Kode barang {$kode} harus bertipe string");
            $this->assertSame($nama, $matched['nama_barang']);
        }
    }

    public function test_halaman_index_rkbmd_memuat_data_program_dan_kegiatan_master(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('rkbmd.index'));

        $response->assertOk();
        $response->assertViewHas('programList', function ($list) {
            return count($list) === 5;
        });

        $response->assertViewHas('kegiatanMaster', function ($list) {
            return count($list) === 45;
        });

        // Pastikan HTML modal memuat elemen Program, Sub Kegiatan, dan Bidang Urusan
        $response->assertSee('id="usulan-program"', false);
        $response->assertSee('id="usulan-sub-kegiatan"', false);
        $response->assertSee('id="usulan-bidang-urusan"', false);
        $response->assertSee('id="search_master_barang"', false);
        $response->assertSee('Cari atau pilih Program', false);
        $response->assertSee('Pilih Program terlebih dahulu', false);
    }

    public function test_data_usulan_lama_id_10_tetap_dapat_ditampilkan_di_tabel_index(): void
    {
        // Pastikan record ID 10 ada di database
        $usulanLama = UsulanRkbmd::find(10);
        $this->assertNotNull($usulanLama, 'Usulan lama ID 10 harus tetap ada');
        $this->assertSame('Program Penunjang Pemerintah', $usulanLama->program_kegiatan);
        $this->assertSame('Ditolak', $usulanLama->status_usulan);

        $response = $this->actingAs($this->adminUser)
            ->get(route('rkbmd.index'));

        $response->assertOk();
        // Pastikan tabel merender baris usulan lama
        $response->assertSee($usulanLama->nama_barang);
        $response->assertSee($usulanLama->status_usulan);
        $response->assertSee('TA ' . $usulanLama->tahun_anggaran);
    }

    public function test_store_usulan_berhasil_menyimpan_relasi_dan_snapshot_kegiatan(): void
    {
        DB::beginTransaction();
        try {
            $skpd = Skpd::first();
            $kegiatan = MasterKegiatanRkbmd::first();
            $this->assertNotNull($kegiatan, 'Master kegiatan harus tersedia');

            $payload = [
                'id_skpd' => $skpd->id_skpd,
                'tahun_anggaran' => 2027,
                'jenis_usulan' => 'Pengadaan',
                'id_kegiatan' => $kegiatan->id_kegiatan,
                'nama_barang' => 'Laptop Testing Master RKBMD',
                'kode_barang' => '1.3.2.10.02',
                'spesifikasi' => 'Core i7, RAM 16GB, SSD 512GB',
                'jumlah' => 2,
                'satuan' => 'Unit',
                'kebutuhan_maksimum' => 5,
                'kebutuhan_riil' => 2,
                'alasan_kebutuhan' => 'Kebutuhan pengujian otomatis integrasi Master Kegiatan RKBMD.',
            ];

            $response = $this->actingAs($this->adminUser)
                ->from(route('rkbmd.index'))
                ->post(route('rkbmd.store'), $payload);

            $response->assertRedirect(route('rkbmd.index'));
            $response->assertSessionHas('success');

            $usulan = UsulanRkbmd::where('nama_barang', 'Laptop Testing Master RKBMD')
                ->latest('id_usulan')
                ->first();

            $this->assertNotNull($usulan, 'Usulan baru harus tersimpan di database');
            $this->assertEquals($kegiatan->id_kegiatan, $usulan->id_kegiatan);
            $this->assertSame($kegiatan->bidang_urusan, $usulan->bidang_urusan);
            $this->assertSame($kegiatan->nama_program, $usulan->nama_program);
            $this->assertSame($kegiatan->nama_sub_kegiatan, $usulan->nama_sub_kegiatan);

            // Format baku snapshot program_kegiatan
            $expectedProgramKegiatan = "{$kegiatan->bidang_urusan} - {$kegiatan->nama_program} - {$kegiatan->nama_sub_kegiatan}";
            $this->assertSame($expectedProgramKegiatan, $usulan->program_kegiatan);

            // Relasi kegiatan()
            $this->assertInstanceOf(MasterKegiatanRkbmd::class, $usulan->kegiatan);
            $this->assertEquals($kegiatan->id_kegiatan, $usulan->kegiatan->id_kegiatan);
            $this->assertSame('Draft', $usulan->status_usulan);
        } finally {
            DB::rollBack();
        }
    }

    public function test_store_usulan_menolak_id_kegiatan_yang_tidak_valid(): void
    {
        DB::beginTransaction();
        try {
            $skpd = Skpd::first();
            $basePayload = [
                'id_skpd' => $skpd->id_skpd,
                'tahun_anggaran' => 2027,
                'jenis_usulan' => 'Pengadaan',
                'nama_barang' => 'Barang Validasi Test',
                'kode_barang' => '1.3.2.10.02',
                'jumlah' => 1,
                'satuan' => 'Unit',
                'kebutuhan_maksimum' => 1,
                'kebutuhan_riil' => 1,
                'alasan_kebutuhan' => 'Pengujian validasi id_kegiatan.',
            ];

            // 1. id_kegiatan tidak diisi
            $resMissing = $this->actingAs($this->adminUser)
                ->from(route('rkbmd.index'))
                ->post(route('rkbmd.store'), $basePayload);
            $resMissing->assertSessionHasErrors('id_kegiatan');

            // 2. id_kegiatan tidak ada di tabel master
            $resNonExistent = $this->actingAs($this->adminUser)
                ->from(route('rkbmd.index'))
                ->post(route('rkbmd.store'), array_merge($basePayload, ['id_kegiatan' => 999999]));
            $resNonExistent->assertSessionHasErrors('id_kegiatan');

            // 3. id_kegiatan bukan integer
            $resString = $this->actingAs($this->adminUser)
                ->from(route('rkbmd.index'))
                ->post(route('rkbmd.store'), array_merge($basePayload, ['id_kegiatan' => 'bukan-id']));
            $resString->assertSessionHasErrors('id_kegiatan');
        } finally {
            DB::rollBack();
        }
    }

    public function test_update_usulan_dapat_mengganti_kegiatan_dan_snapshot(): void
    {
        DB::beginTransaction();
        try {
            $skpd = Skpd::first();
            $kegiatanList = MasterKegiatanRkbmd::orderBy('id_kegiatan')->take(2)->get();
            $this->assertCount(2, $kegiatanList, 'Harus ada minimal 2 kegiatan master untuk pengujian penggantian kegiatan');
            $keg1 = $kegiatanList[0];
            $keg2 = $kegiatanList[1];

            // Buat usulan awal dengan keg1
            $usulan = UsulanRkbmd::create([
                'id_skpd' => $skpd->id_skpd,
                'id_pegawai' => $this->adminUser->pegawai?->id_pegawai,
                'tahun_anggaran' => 2027,
                'jenis_usulan' => 'Pengadaan',
                'id_kegiatan' => $keg1->id_kegiatan,
                'bidang_urusan' => $keg1->bidang_urusan,
                'nama_program' => $keg1->nama_program,
                'nama_sub_kegiatan' => $keg1->nama_sub_kegiatan,
                'program_kegiatan' => "{$keg1->bidang_urusan} - {$keg1->nama_program} - {$keg1->nama_sub_kegiatan}",
                'nama_barang' => 'Barang Update Test',
                'kode_barang' => '1.3.2.10.02',
                'spesifikasi' => 'Spesifikasi awal',
                'jumlah' => 1,
                'satuan' => 'Unit',
                'kebutuhan_maksimum' => 2,
                'kebutuhan_riil' => 1,
                'alasan_kebutuhan' => 'Alasan awal',
                'status_usulan' => 'Draft',
                'tanggal_usulan' => now()->toDateString(),
            ]);

            // Update ke keg2
            $updatePayload = [
                'id_skpd' => $skpd->id_skpd,
                'tahun_anggaran' => 2027,
                'jenis_usulan' => 'Pengadaan',
                'id_kegiatan' => $keg2->id_kegiatan,
                'nama_barang' => 'Barang Update Test (Revisi)',
                'kode_barang' => '1.3.2.10.02',
                'spesifikasi' => 'Spesifikasi revisi',
                'jumlah' => 3,
                'satuan' => 'Unit',
                'kebutuhan_maksimum' => 5,
                'kebutuhan_riil' => 3,
                'alasan_kebutuhan' => 'Alasan diperbarui dengan kegiatan master baru.',
            ];

            $response = $this->actingAs($this->adminUser)
                ->from(route('rkbmd.index'))
                ->patch(route('rkbmd.update', $usulan->id_usulan), $updatePayload);

            $response->assertRedirect(route('rkbmd.index'));
            $response->assertSessionHas('success');

            $usulan->refresh();
            $this->assertEquals($keg2->id_kegiatan, $usulan->id_kegiatan);
            $this->assertSame($keg2->bidang_urusan, $usulan->bidang_urusan);
            $this->assertSame($keg2->nama_program, $usulan->nama_program);
            $this->assertSame($keg2->nama_sub_kegiatan, $usulan->nama_sub_kegiatan);
            $expectedProgram = "{$keg2->bidang_urusan} - {$keg2->nama_program} - {$keg2->nama_sub_kegiatan}";
            $this->assertSame($expectedProgram, $usulan->program_kegiatan);
            $this->assertSame('Barang Update Test (Revisi)', $usulan->nama_barang);
            $this->assertEquals(3, $usulan->jumlah);
        } finally {
            DB::rollBack();
        }
    }

    public function test_update_usulan_lama_tanpa_memilih_master_kegiatan_tetap_mempertahankan_data_lama(): void
    {
        DB::beginTransaction();
        try {
            $skpd = Skpd::first();

            // Simulasi usulan berformat lama (tanpa id_kegiatan / null) berstatus Draft
            $usulanLamaDraft = UsulanRkbmd::create([
                'id_skpd' => $skpd->id_skpd,
                'id_pegawai' => $this->adminUser->pegawai?->id_pegawai,
                'tahun_anggaran' => 2026,
                'jenis_usulan' => 'Pengadaan',
                'id_kegiatan' => null,
                'bidang_urusan' => null,
                'nama_program' => null,
                'nama_sub_kegiatan' => null,
                'program_kegiatan' => 'Program Penunjang Pemerintah Legacy Text',
                'nama_barang' => 'Meja Rapat Legacy',
                'kode_barang' => '1.3.2.04',
                'jumlah' => 4,
                'satuan' => 'Unit',
                'kebutuhan_maksimum' => 10,
                'kebutuhan_riil' => 4,
                'alasan_kebutuhan' => 'Kebutuhan lama belum terhubung master kegiatan',
                'status_usulan' => 'Draft',
                'tanggal_usulan' => '2026-01-15',
            ]);

            // Update tanpa memilih id_kegiatan baru (mengirim program_kegiatan lama atau tanpa id_kegiatan)
            $payload = [
                'id_skpd' => $skpd->id_skpd,
                'tahun_anggaran' => 2026,
                'jenis_usulan' => 'Pengadaan',
                'program_kegiatan' => 'Program Penunjang Pemerintah Legacy Text',
                'nama_barang' => 'Meja Rapat Legacy (Diedit)',
                'kode_barang' => '1.3.2.04',
                'jumlah' => 5,
                'satuan' => 'Unit',
                'kebutuhan_maksimum' => 10,
                'kebutuhan_riil' => 5,
                'alasan_kebutuhan' => 'Kebutuhan diupdate jumlahnya tetapi master kegiatan tidak diubah.',
            ];

            $response = $this->actingAs($this->adminUser)
                ->from(route('rkbmd.index'))
                ->patch(route('rkbmd.update', $usulanLamaDraft->id_usulan), $payload);

            $response->assertRedirect(route('rkbmd.index'));
            $response->assertSessionHas('success');

            $usulanLamaDraft->refresh();
            // id_kegiatan tetap null
            $this->assertNull($usulanLamaDraft->id_kegiatan);
            // program_kegiatan teks historis lama tetap terjaga
            $this->assertSame('Program Penunjang Pemerintah Legacy Text', $usulanLamaDraft->program_kegiatan);
            $this->assertSame('Meja Rapat Legacy (Diedit)', $usulanLamaDraft->nama_barang);
            $this->assertEquals(5, $usulanLamaDraft->jumlah);
        } finally {
            DB::rollBack();
        }
    }

    public function test_record_lama_id_10_tetap_utuh_dan_tidak_berubah(): void
    {
        $usulan10 = UsulanRkbmd::find(10);
        $this->assertNotNull($usulan10, 'Record ID 10 harus tetap ada di tabel usulan_rkbmd');
        $this->assertEquals(10, $usulan10->id_usulan);
        $this->assertNull($usulan10->id_kegiatan, 'Record ID 10 id_kegiatan harus tetap NULL');
        $this->assertNull($usulan10->bidang_urusan, 'Record ID 10 bidang_urusan harus tetap NULL');
        $this->assertNull($usulan10->nama_program, 'Record ID 10 nama_program harus tetap NULL');
        $this->assertNull($usulan10->nama_sub_kegiatan, 'Record ID 10 nama_sub_kegiatan harus tetap NULL');
        $this->assertSame('Program Penunjang Pemerintah', $usulan10->program_kegiatan, 'Kolom program_kegiatan ID 10 tidak boleh berubah');
        $this->assertSame('test', $usulan10->nama_barang);
        $this->assertSame('Ditolak', $usulan10->status_usulan);
    }
}

