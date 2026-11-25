<?php

namespace Tests\Feature;

use App\Models\Aset;
use App\Models\Kendaraan;
use App\Models\MasterBarang;
use App\Models\MutasiAset;
use App\Models\Pegawai;
use App\Models\PemegangAset;
use App\Models\Role;
use App\Models\Skpd;
use App\Models\User;
use App\Models\UsulanRkbmd;
use App\Services\AsetScope;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardAdminAsetTest extends TestCase
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

    private function loginSebagai(User $user): void
    {
        app()->forgetInstance(AsetScope::class);
        $this->actingAs($user);
    }

    private function buatAdminAset(): User
    {
        $role = Role::firstOrCreate(
            ['nama_role' => 'Admin Aset'],
            ['deskripsi' => 'Administrator Aset']
        );

        $user = User::where('id_role', $role->id_role)->first();
        if ($user) {
            return $user;
        }

        return User::factory()->create([
            'username' => 'admin_aset_test_' . uniqid(),
            'id_role' => $role->id_role,
            'is_active' => true,
        ]);
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

        $pegawai = Pegawai::create([
            'nama_pegawai' => 'Pegawai Test ' . uniqid(),
            'nip' => '19900101' . rand(100000, 999999),
            'id_skpd' => $skpd->id_skpd,
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

    public function test_dashboard_admin_aset_bisa_diakses_dan_bebas_error_variabel(): void
    {
        $adminAset = $this->buatAdminAset();
        $this->loginSebagai($adminAset);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard.index');
        $response->assertViewHas([
            'role',
            'totalAset',
            'totalNilai',
            'kendaraanTersedia',
            'kendaraanDipakai',
            'tanahKibA',
            'asetBaik',
            'asetRusakRingan',
            'asetRusakBerat',
            'kendaraanPajak',
            'kendaraanPajakCount',
            'rkbmdPending',
            'rkbmdPendingCount',
            'aktivitasTerbaru',
        ]);
        // Pastikan variabel pegawaiTanpaSppbi sudah tidak dikirim
        $this->assertArrayNotHasKey('pegawaiTanpaSppbi', $response->original->getData());
    }

    public function test_seluruh_komponen_sppbi_dihapus_dari_dashboard_admin_aset(): void
    {
        $adminAset = $this->buatAdminAset();
        $this->loginSebagai($adminAset);

        $response = $this->get(route('dashboard'));
        $content = $response->getContent();

        // 1. Judul SPPBI Belum Diunggah tidak ada
        $this->assertStringNotContainsString('SPPBI Belum Diunggah', $content);

        // 2. Keterangan "Pemegang aset tanpa scan TTD" tidak ada
        $this->assertStringNotContainsString('Pemegang aset tanpa scan TTD', $content);

        // 3. Tombol "Unggah Sekarang" tidak ada
        $this->assertStringNotContainsString('Unggah Sekarang', $content);

        // 4. "Verifikasi Berkas SPPBI" pada hero kanan tidak ada
        $this->assertStringNotContainsString('Verifikasi Berkas SPPBI', $content);
        $this->assertStringNotContainsString('pemegang belum mengunggah scan TTD', $content);
        $this->assertStringNotContainsString('kelengkapan berkas', $content);
    }

    public function test_card_pajak_kendaraan_tetap_tampil_dengan_data_lengkap(): void
    {
        $adminAset = $this->buatAdminAset();
        $this->loginSebagai($adminAset);

        $response = $this->get(route('dashboard'));

        $response->assertSee('Pajak Kendaraan');
        $response->assertSee('Jatuh tempo ≤ 30 hari / lewat');
    }

    public function test_komponen_kondisi_kesehatan_aset_tetap_tampil(): void
    {
        $adminAset = $this->buatAdminAset();
        $this->loginSebagai($adminAset);

        $response = $this->get(route('dashboard'));

        $response->assertSee('Status Kesehatan Aset');
        $response->assertSee('aset dinilai');
        $response->assertSee('Kondisi Fisik Inventaris');
        $response->assertSee('Distribusi kondisi aset aktif');
        $response->assertSee('Baik');
        $response->assertSee('Rusak Ringan');
        $response->assertSee('Rusak Berat');
    }

    public function test_monitoring_mutasi_dan_rkbmd_tetap_berfungsi(): void
    {
        $adminAset = $this->buatAdminAset();
        $this->loginSebagai($adminAset);

        $response = $this->get(route('dashboard'));

        $response->assertSee('Aktivitas Terkini');
        $response->assertSee('Rekam jejak mutasi aset terbaru');
        $response->assertSee('RKBMD Diajukan');
        $response->assertSee('Pengajuan Izin Pending');
    }

    public function test_dashboard_admin_aset_memiliki_akses_global_seluruh_skpd(): void
    {
        $adminAset = $this->buatAdminAset();
        $this->loginSebagai($adminAset);

        $response = $this->get(route('dashboard'));
        $data = $response->original->getData();

        // Scope Admin Aset mencakup seluruh dinas tanpa pembatasan id_skpd
        $totalAsetDb = Aset::whereIn('status_aset', ['aktif', 'Aktif'])->count();
        $this->assertEquals($totalAsetDb, $data['totalAset']);
    }

    public function test_dashboard_pegawai_dapat_diakses_dengan_role_pegawai(): void
    {
        $userPegawai = $this->buatPegawai();
        $this->loginSebagai($userPegawai);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewIs('dashboard.index');
        $this->assertEquals('Pegawai', $response->original->getData()['role']);
    }
}
