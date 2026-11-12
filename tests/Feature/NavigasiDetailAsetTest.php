<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Skpd;
use App\Models\User;
use App\Services\AsetScope;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NavigasiDetailAsetTest extends TestCase
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

    private function buatUserRole(string $namaRole, int $idSkpd, string $jenisSkpd): User
    {
        $role = Role::firstOrCreate(
            ['nama_role' => $namaRole],
            ['deskripsi' => 'Role ' . $namaRole]
        );

        $skpd = Skpd::find($idSkpd);
        if (! $skpd) {
            $skpd = Skpd::create([
                'id_skpd' => $idSkpd,
                'nama_skpd' => $namaRole . ' Unit ' . $idSkpd,
                'jenis_skpd' => $jenisSkpd,
            ]);
        }

        $ruangan = Ruangan::firstOrCreate(
            ['nama_ruangan' => 'Ruangan ' . $namaRole . ' ' . $idSkpd],
            ['id_skpd' => $idSkpd, 'status' => 'Aktif']
        );

        $pegawai = Pegawai::create([
            'nama_pegawai' => 'Pegawai ' . $namaRole . ' ' . uniqid(),
            'nip' => '19900101' . rand(100000, 999999),
            'id_skpd' => $skpd->id_skpd,
            'id_ruangan' => $ruangan->id_ruangan,
            'jabatan' => 'Staf ' . $namaRole,
        ]);

        return User::create([
            'username' => 'user_' . strtolower(str_replace(' ', '_', $namaRole)) . '_' . uniqid(),
            'password' => bcrypt('password123'),
            'id_pegawai' => $pegawai->id_pegawai,
            'id_role' => $role->id_role,
            'status_user' => 'aktif',
        ]);
    }

    /**
     * 1. Admin UPT membuka "Lihat Aset Saya" dari Dashboard Admin UPT,
     * lalu menekan Kembali, harus kembali ke Dashboard Admin UPT.
     */
    public function test_admin_upt_buka_lihat_aset_saya_kembali_ke_dashboard_admin_upt(): void
    {
        $adminUpt = $this->buatUserRole('Admin UPT', 6, 'UPT');
        $this->loginSebagai($adminUpt);

        // Akses dashboard: tombol "Lihat Aset Saya" harus memiliki parameter from=dashboard
        $dashRes = $this->get(route('dashboard'));
        $dashRes->assertStatus(200);
        $expectedUrl = route('aset-barang.show', ['pegawai' => $adminUpt->id_pegawai, 'from' => 'dashboard']);
        $dashRes->assertSee($expectedUrl);

        // Buka halaman detail aset dengan parameter from=dashboard
        $showRes = $this->get($expectedUrl);
        $showRes->assertStatus(200);
        $showRes->assertSee(route('dashboard'));
        $showRes->assertSee('Kembali ke Dashboard');

        // Klik tombol Kembali -> harus kembali ke Dashboard Admin UPT
        $kembaliRes = $this->get(route('dashboard'));
        $kembaliRes->assertStatus(200);
        $kembaliRes->assertSee('Dashboard Unit Kerja');
        $kembaliRes->assertSee($adminUpt->pegawai->skpd->nama_skpd);
        $kembaliRes->assertDontSee('Dashboard Admin Bidang');
    }

    /**
     * 2. Admin Bidang membuka "Lihat Aset Saya" dari Dashboard Admin Bidang,
     * lalu menekan Kembali, harus kembali ke Dashboard Admin Bidang.
     */
    public function test_admin_bidang_buka_lihat_aset_saya_kembali_ke_dashboard_admin_bidang(): void
    {
        // SKPD Bidang (misal ID 2)
        $skpdBidang = Skpd::where('jenis_skpd', 'Bidang')->first();
        $idSkpd = $skpdBidang ? $skpdBidang->id_skpd : 2;
        $adminBidang = $this->buatUserRole('Admin Bidang', $idSkpd, 'Bidang');
        $this->loginSebagai($adminBidang);

        // Akses dashboard: tombol "Lihat Aset Saya" harus memiliki parameter from=dashboard
        $dashRes = $this->get(route('dashboard'));
        $dashRes->assertStatus(200);
        $expectedUrl = route('aset-barang.show', ['pegawai' => $adminBidang->id_pegawai, 'from' => 'dashboard']);
        $dashRes->assertSee($expectedUrl);

        // Buka halaman detail aset dengan parameter from=dashboard
        $showRes = $this->get($expectedUrl);
        $showRes->assertStatus(200);
        $showRes->assertSee(route('dashboard'));
        $showRes->assertSee('Kembali ke Dashboard');

        // Klik tombol Kembali -> harus kembali ke Dashboard Admin Bidang
        $kembaliRes = $this->get(route('dashboard'));
        $kembaliRes->assertStatus(200);
        $kembaliRes->assertSee('Dashboard Admin Bidang');
        $kembaliRes->assertSee($adminBidang->pegawai->skpd->nama_skpd);
        $kembaliRes->assertDontSee('Dashboard Unit Kerja');
    }

    /**
     * 3. Pengguna membuka detail aset dari Modul Aset Barang,
     * lalu menekan Kembali, harus kembali ke Modul Aset Barang.
     */
    public function test_pengguna_buka_detail_aset_dari_aset_barang_kembali_ke_aset_barang(): void
    {
        $adminAset = $this->buatUserRole('Admin Aset', 1, 'Dinas');
        $pegawai = Pegawai::create([
            'nama_pegawai' => 'Pegawai Uji Aset ' . uniqid(),
            'nip' => '19920101' . rand(100000, 999999),
            'id_skpd' => 1,
        ]);

        $this->loginSebagai($adminAset);

        // 3a. Akses dengan from=aset-barang
        $urlAsetBarang = route('aset-barang.show', ['pegawai' => $pegawai->id_pegawai, 'from' => 'aset-barang']);
        $resAsetBarang = $this->get($urlAsetBarang);
        $resAsetBarang->assertStatus(200);
        $resAsetBarang->assertSee(route('aset-barang.index'));
        $resAsetBarang->assertSee('Kembali ke Aset Barang');

        // Klik kembali -> kembali ke aset-barang.index
        $kembaliRes = $this->get(route('aset-barang.index'));
        $kembaliRes->assertStatus(200);
    }

    /**
     * 4. Admin UPT tidak diarahkan ke Dashboard Admin Bidang dan sebaliknya.
     */
    public function test_pemisahan_tujuan_dashboard_antar_role(): void
    {
        $adminUpt = $this->buatUserRole('Admin UPT', 6, 'UPT');
        $this->loginSebagai($adminUpt);

        // Admin UPT membuka dashboard
        $resUpt = $this->get(route('dashboard'));
        $resUpt->assertStatus(200);
        $resUpt->assertSee('Dashboard Unit Kerja');
        $resUpt->assertDontSee('Dashboard Admin Bidang');

        // Admin Bidang membuka dashboard
        $skpdBidang = Skpd::where('jenis_skpd', 'Bidang')->first();
        $adminBidang = $this->buatUserRole('Admin Bidang', $skpdBidang ? $skpdBidang->id_skpd : 2, 'Bidang');
        $this->loginSebagai($adminBidang);

        $resBidang = $this->get(route('dashboard'));
        $resBidang->assertStatus(200);
        $resBidang->assertSee('Dashboard Admin Bidang');
        $resBidang->assertDontSee('Dashboard Unit Kerja');
    }

    /**
     * 5. Validasi return URL menolak URL eksternal / manipulasi dan menggunakan fallback aman.
     */
    public function test_validasi_return_url_menolak_url_eksternal_dan_skema_tidak_aman(): void
    {
        $adminUpt = $this->buatUserRole('Admin UPT', 6, 'UPT');
        $this->loginSebagai($adminUpt);

        // Uji coba injeksi URL eksternal
        $urlEksternal = route('aset-barang.show', [
            'pegawai' => $adminUpt->id_pegawai,
            'return_url' => 'https://malicious-site.com/evil',
        ]);
        $resEksternal = $this->get($urlEksternal);
        $resEksternal->assertStatus(200);
        $resEksternal->assertDontSee('https://malicious-site.com/evil');
        // Fallback aman untuk Admin UPT adalah dashboard
        $resEksternal->assertSee(route('dashboard'));
        $resEksternal->assertSee('Kembali ke Dashboard');

        // Uji coba skema javascript:
        $urlJs = route('aset-barang.show', [
            'pegawai' => $adminUpt->id_pegawai,
            'return_url' => 'javascript:alert(1)',
        ]);
        $resJs = $this->get($urlJs);
        $resJs->assertStatus(200);
        $resJs->assertDontSee('javascript:alert(1)');
        $resJs->assertSee(route('dashboard'));

        // Uji coba protocol-relative //evil.com
        $urlProto = route('aset-barang.show', [
            'pegawai' => $adminUpt->id_pegawai,
            'return_url' => '//evil.com/hack',
        ]);
        $resProto = $this->get($urlProto);
        $resProto->assertStatus(200);
        $resProto->assertDontSee('//evil.com/hack');
        $resProto->assertSee(route('dashboard'));
    }

    /**
     * 6. Akses detail aset pribadi tetap aman: manipulasi id_pegawai tetap ditolak 403.
     */
    public function test_akses_detail_aset_pribadi_tetap_terkunci_ke_id_pegawai_sendiri(): void
    {
        $adminUpt = $this->buatUserRole('Admin UPT', 6, 'UPT');
        $pegawaiLain = Pegawai::create([
            'nama_pegawai' => 'Pegawai Lain ' . uniqid(),
            'nip' => '19930101' . rand(100000, 999999),
            'id_skpd' => 6,
        ]);

        $this->loginSebagai($adminUpt);

        // Mencoba membuka aset pegawai lain dengan manipulasi id
        $res = $this->get(route('aset-barang.show', ['pegawai' => $pegawaiLain->id_pegawai, 'from' => 'dashboard']));
        $res->assertStatus(403);
    }
}

