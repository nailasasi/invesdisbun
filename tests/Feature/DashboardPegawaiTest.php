<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\IzinKendaraan;
use App\Models\Kendaraan;
use App\Models\MasterBarang;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Skpd;
use App\Models\User;
use App\Services\AsetScope;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardPegawaiTest extends TestCase
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

    private function loginSebagai(User $user): void
    {
        app()->forgetInstance(AsetScope::class);
        $this->actingAs($user);
    }

    private function buatPegawai(int $idSkpd = 1): User
    {
        $role = Role::firstOrCreate(
            ['nama_role' => 'Pegawai'],
            ['deskripsi' => 'Pegawai']
        );

        $skpd = Skpd::find($idSkpd) ?? Skpd::create([
            'id_skpd' => $idSkpd,
            'nama_skpd' => 'SKPD Test ' . $idSkpd,
            'jenis_skpd' => 'Bidang',
        ]);

        $ruangan = Ruangan::firstOrCreate(
            ['nama_ruangan' => 'Ruangan Pegawai ' . $idSkpd],
            ['id_skpd' => $idSkpd, 'status' => 'Aktif']
        );

        $pegawai = Pegawai::create([
            'nama_pegawai' => 'Pegawai Test ' . uniqid(),
            'nip' => '19900101' . rand(100000, 999999),
            'id_skpd' => $skpd->id_skpd,
            'id_ruangan' => $ruangan->id_ruangan,
            'jabatan' => 'Staf Test',
        ]);

        return User::create([
            'username' => 'pegawai_test_' . uniqid(),
            'password' => bcrypt('password123'),
            'id_role' => $role->id_role,
            'id_pegawai' => $pegawai->id_pegawai,
            'status_user' => 'aktif',
        ]);
    }

    public function test_seluruh_komponen_sppbi_tidak_muncul_lagi_di_dashboard_pegawai(): void
    {
        $user = $this->buatPegawai();
        $this->loginSebagai($user);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $content = $response->getContent();

        // 1. Badge & tombol SPPBI di header
        $this->assertStringNotContainsString('SPPBI Belum Diunggah', $content);
        $this->assertStringNotContainsString('SPPBI Terunggah', $content);
        $this->assertStringNotContainsString('Unggah SPPBI Sekarang', $content);

        // 2. Card Status SPPBI & status Belum Unggah
        $this->assertStringNotContainsString('Status SPPBI', $content);
        $this->assertStringNotContainsString('Belum Unggah', $content);
        $this->assertStringNotContainsString('scan tanda tangan SPPBI', $content);

        // 3. Banner peringatan scan TTD SPPBI
        $this->assertStringNotContainsString('Scan tanda tangan SPPBI Anda belum diunggah', $content);
        $this->assertStringNotContainsString('File scan TTD diperlukan untuk melengkapi administrasi', $content);
    }

    public function test_tombol_lihat_aset_saya_tampil_di_header_hijau(): void
    {
        $user = $this->buatPegawai();
        $this->loginSebagai($user);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Lihat Aset Saya');
        $expectedUrl = route('aset-barang.show', ['pegawai' => $user->id_pegawai, 'from' => 'dashboard']);
        $response->assertSee(e($expectedUrl), false);
    }

    public function test_tombol_hanya_menampilkan_aset_milik_pegawai_yang_login(): void
    {
        $user = $this->buatPegawai();
        $this->loginSebagai($user);

        // Pasangkan aset ke pegawai ini
        $barang = MasterBarang::firstOrCreate(
            ['kode_barang' => 'BRG_TEST_' . uniqid()],
            ['nama_barang' => 'Laptop Inventaris Khusus', 'id_kategori' => 1]
        );
        $aset = Aset::create([
            'id_barang' => $barang->id_barang,
            'nomor_kartu_barang' => 'NKB_TEST_' . uniqid(),
            'kondisi' => 'Baik',
            'status_aset' => 'aktif',
            'is_kendaraan' => false,
        ]);
        PemegangAset::create([
            'id_aset' => $aset->id_aset,
            'id_pegawai' => $user->id_pegawai,
            'status' => 'aktif',
        ]);

        $url = route('aset-barang.show', ['pegawai' => $user->id_pegawai, 'from' => 'dashboard']);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('Laptop Inventaris Khusus');
    }

    public function test_pegawai_tidak_bisa_melihat_aset_pegawai_lain_melalui_manipulasi_url(): void
    {
        $user1 = $this->buatPegawai();
        $user2 = $this->buatPegawai();

        $this->loginSebagai($user1);

        // Pegawai 1 mencoba membuka detail aset pribadi Pegawai 2
        $url = route('aset-barang.show', ['pegawai' => $user2->id_pegawai]);
        $response = $this->get($url);

        $response->assertStatus(403);
    }

    public function test_tombol_kembali_mengarahkan_pegawai_ke_dashboard_pegawai_jika_dibuka_dari_dashboard(): void
    {
        $user = $this->buatPegawai();
        $this->loginSebagai($user);

        $url = route('aset-barang.show', ['pegawai' => $user->id_pegawai, 'from' => 'dashboard']);
        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('Kembali ke Dashboard');
        $response->assertSee(route('dashboard'));
    }

    public function test_section_aset_saya_yang_lama_sudah_tidak_muncul(): void
    {
        $user = $this->buatPegawai();
        $this->loginSebagai($user);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $content = $response->getContent();

        // Keterangan section lama
        $this->assertStringNotContainsString('Barang yang saat ini menjadi tanggung jawab Anda', $content);
        $this->assertStringNotContainsString('Lihat Dokumen TTD', $content);
    }

    public function test_pengajuan_izin_pending_tetap_tampil_dan_berfungsi(): void
    {
        $user = $this->buatPegawai();
        $this->loginSebagai($user);

        // Buat pengajuan izin kendaraan
        $kendaraan = Kendaraan::first();
        if (! $kendaraan) {
            $barang = MasterBarang::firstOrCreate(
                ['kode_barang' => 'KND_TEST_' . uniqid()],
                ['nama_barang' => 'Mobil Dinas Test', 'id_kategori' => 2]
            );
            $aset = Aset::create([
                'id_barang' => $barang->id_barang,
                'nomor_kartu_barang' => 'NKB_KND_' . uniqid(),
                'kondisi' => 'Baik',
                'status_aset' => 'aktif',
                'is_kendaraan' => true,
            ]);
            $kendaraan = Kendaraan::create([
                'id_aset' => $aset->id_aset,
                'nama_kendaraan' => 'Avanza Test',
                'status_penggunaan' => 'Dipakai',
            ]);
        }

        $izin = IzinKendaraan::create([
            'id_pegawai_pengaju' => $user->id_pegawai,
            'id_kendaraan' => $kendaraan->id_kendaraan,
            'keperluan' => 'Dinas Luar Kota Uji Coba',
            'status_approval' => 'Menunggu',
            'tanggal_berangkat' => now()->addDays(2),
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Status Pengajuan Izin Kendaraan Anda');
        $response->assertSee('Menunggu Verifikasi Admin');
        $response->assertSee('Pengajuan izin kendaraan');
    }

    public function test_dashboard_tidak_error_ketika_pegawai_belum_memiliki_aset(): void
    {
        $user = $this->buatPegawai();
        $this->loginSebagai($user);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard.index');
        $this->assertEquals(0, $response->original->getData()['asetSaya']->count());
        $response->assertSee('Aset Saya');
        $response->assertSee('0');
        $response->assertSee('aset yang sedang Anda pegang');
    }
}

