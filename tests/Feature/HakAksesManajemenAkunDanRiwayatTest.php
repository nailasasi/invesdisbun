<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Pegawai;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HakAksesManajemenAkunDanRiwayatTest extends TestCase
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

    private function getAdminAset(): User
    {
        return User::whereHas('role', fn ($q) => $q->where('nama_role', 'Admin Aset'))->firstOrFail();
    }

    private function getAdminUpt(): User
    {
        return User::whereHas('role', fn ($q) => $q->where('nama_role', 'Admin UPT'))->firstOrFail();
    }

    private function getAdminBidang(): User
    {
        return $this->ambilAtauBuatUserRole('Admin Bidang');
    }

    private function getPegawaiUser(): User
    {
        return $this->ambilAtauBuatUserRole('Pegawai');
    }

    // =========================================================================
    // A. PENGUJIAN ADMIN ASET
    // =========================================================================

    /**
     * 1. Admin Aset dapat membuat akun, otomatis role Pegawai, username NIP, password default NIP.
     */
    public function test_admin_aset_dapat_membuat_akun_dan_otomatis_role_pegawai(): void
    {
        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $skpdSekretariat = Skpd::where('jenis_skpd', 'Sekretariat')->firstOrFail();
        $pegawai = Pegawai::create([
            'nip' => '199001012015011001',
            'nama_pegawai' => 'Pegawai Baru Tanpa Akun',
            'jabatan' => 'Pranata Komputer',
            'id_skpd' => $skpdSekretariat->id_skpd,
        ]);

        $response = $this->post(route('user.akun.store', $pegawai), [
            'password' => '', // kosongkan password
        ]);

        $response->assertRedirect(route('master-data.pegawai.show', ['pegawai' => $pegawai, 'tab' => 'tab-akun']));
        $this->assertTrue(session()->has('success') || session()->has('berhasil'));

        $pegawai->refresh();
        $this->assertNotNull($pegawai->user);
        $this->assertEquals('199001012015011001', $pegawai->user->username);
        $this->assertEquals('aktif', $pegawai->user->status_user);

        $rolePegawai = Role::where('nama_role', 'Pegawai')->firstOrFail();
        $this->assertEquals($rolePegawai->id_role, $pegawai->user->id_role);

        // Verifikasi password default = NIP dapat digunakan untuk login
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('199001012015011001', $pegawai->user->password));
    }

    /**
     * 2. Manipulasi request id_role saat Buat Akun tidak boleh menghasilkan role selain Pegawai.
     */
    public function test_admin_aset_membuat_akun_dengan_manipulasi_request_id_role_tetap_jadi_pegawai(): void
    {
        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $roleAset = Role::where('nama_role', 'Admin Aset')->firstOrFail();
        $rolePegawai = Role::where('nama_role', 'Pegawai')->firstOrFail();
        $skpdSekretariat = Skpd::where('jenis_skpd', 'Sekretariat')->firstOrFail();

        $pegawai = Pegawai::create([
            'nip' => '199001012015011002',
            'nama_pegawai' => 'Pegawai Coba Bypass Role',
            'jabatan' => 'Staf TU',
            'id_skpd' => $skpdSekretariat->id_skpd,
        ]);

        // Kirim request tampering dengan menyisipkan id_role Admin Aset
        $response = $this->post(route('user.akun.store', $pegawai), [
            'id_role' => $roleAset->id_role,
            'password' => 'password123',
        ]);

        $response->assertRedirect();
        $pegawai->refresh();
        $this->assertNotNull($pegawai->user);
        // Sistem WAJIB mengabaikan id_role request dan menetapkan Pegawai
        $this->assertEquals($rolePegawai->id_role, $pegawai->user->id_role);
        $this->assertNotEquals($roleAset->id_role, $pegawai->user->id_role);
    }

    /**
     * 3. Admin Aset dapat mengubah role akun sesuai jenis SKPD pegawai.
     */
    public function test_admin_aset_dapat_mengubah_role_sesuai_skpd(): void
    {
        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $rolePegawai = Role::where('nama_role', 'Pegawai')->firstOrFail();
        $roleAset = Role::where('nama_role', 'Admin Aset')->firstOrFail();
        $roleBidang = Role::where('nama_role', 'Admin Bidang')->firstOrFail();
        $roleUpt = Role::where('nama_role', 'Admin UPT')->firstOrFail();

        $skpdSekretariat = Skpd::where('jenis_skpd', 'Sekretariat')->firstOrFail();
        $skpdBidang = Skpd::where('jenis_skpd', 'Bidang')->firstOrFail();
        $skpdUpt = Skpd::where('jenis_skpd', 'UPT')->firstOrFail();

        // Kasus 1: Sekretariat -> Boleh Pegawai & Admin Aset, Tolak Admin Bidang & Admin UPT
        $peg1 = Pegawai::create(['nip' => '199101012015011011', 'nama_pegawai' => 'Pegawai Sekr', 'id_skpd' => $skpdSekretariat->id_skpd]);
        $this->post(route('user.akun.store', $peg1))->assertRedirect();

        $this->post(route('user.role.update', $peg1), ['id_role' => $roleBidang->id_role])->assertSessionHasErrors('id_role');
        $this->post(route('user.role.update', $peg1), ['id_role' => $roleUpt->id_role])->assertSessionHasErrors('id_role');
        $this->post(route('user.role.update', $peg1), ['id_role' => $roleAset->id_role])->assertSessionHas('success');
        $this->assertEquals($roleAset->id_role, $peg1->fresh()->user->id_role);

        // Kasus 2: Bidang -> Boleh Pegawai & Admin Bidang, Tolak Admin Aset & Admin UPT
        $peg2 = Pegawai::create(['nip' => '199101012015011012', 'nama_pegawai' => 'Pegawai Bid', 'id_skpd' => $skpdBidang->id_skpd]);
        $this->post(route('user.akun.store', $peg2))->assertRedirect();

        $this->post(route('user.role.update', $peg2), ['id_role' => $roleAset->id_role])->assertSessionHasErrors('id_role');
        $this->post(route('user.role.update', $peg2), ['id_role' => $roleUpt->id_role])->assertSessionHasErrors('id_role');
        $this->post(route('user.role.update', $peg2), ['id_role' => $roleBidang->id_role])->assertSessionHas('success');
        $this->assertEquals($roleBidang->id_role, $peg2->fresh()->user->id_role);

        // Kasus 3: UPT -> Boleh Pegawai & Admin UPT, Tolak Admin Aset & Admin Bidang
        $peg3 = Pegawai::create(['nip' => '199101012015011013', 'nama_pegawai' => 'Pegawai Upt', 'id_skpd' => $skpdUpt->id_skpd]);
        $this->post(route('user.akun.store', $peg3))->assertRedirect();

        $this->post(route('user.role.update', $peg3), ['id_role' => $roleAset->id_role])->assertSessionHasErrors('id_role');
        $this->post(route('user.role.update', $peg3), ['id_role' => $roleBidang->id_role])->assertSessionHasErrors('id_role');
        $this->post(route('user.role.update', $peg3), ['id_role' => $roleUpt->id_role])->assertSessionHas('success');
        $this->assertEquals($roleUpt->id_role, $peg3->fresh()->user->id_role);
    }

    /**
     * 4. Admin Aset dapat menonaktifkan dan mengaktifkan kembali akun.
     */
    public function test_admin_aset_dapat_menonaktifkan_dan_mengaktifkan_kembali_akun(): void
    {
        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $skpd = Skpd::firstOrFail();
        $pegawai = Pegawai::create([
            'nip' => '199201012016011003',
            'nama_pegawai' => 'Pegawai Status Uji',
            'id_skpd' => $skpd->id_skpd,
        ]);
        $this->post(route('user.akun.store', $pegawai), ['password' => 'password123'])->assertRedirect();
        $user = $pegawai->fresh()->user;

        // 1. Nonaktifkan akun
        $responseNonaktif = $this->post(route('user.status.update', $pegawai), ['status_user' => 'nonaktif']);
        $responseNonaktif->assertSessionHas('success');
        $this->assertEquals('nonaktif', $user->fresh()->status_user);

        // Verifikasi akun nonaktif tidak dapat login
        Auth::logout();
        $responseLogin = $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'password123',
        ]);
        $responseLogin->assertSessionHasErrors('username');
        $this->assertFalse(Auth::check());

        // 2. Login kembali sebagai Admin Aset & Aktifkan kembali akun
        $this->actingAs($admin);
        $responseAktif = $this->post(route('user.status.update', $pegawai), ['status_user' => 'aktif']);
        $responseAktif->assertSessionHas('success');
        $this->assertEquals('aktif', $user->fresh()->status_user);

        // Verifikasi akun aktif dapat login
        Auth::logout();
        $responseLoginSuccess = $this->post(route('login'), [
            'username' => $user->username,
            'password' => 'password123',
        ]);
        $responseLoginSuccess->assertRedirect(route('dashboard'));
        $this->assertTrue(Auth::check());
        $this->assertEquals($user->id_user, Auth::id());
    }

    /**
     * 5. Admin Aset tidak dapat menonaktifkan atau mengubah role akunnya sendiri.
     */
    public function test_admin_aset_tidak_dapat_menonaktifkan_atau_ubah_role_akunnya_sendiri(): void
    {
        $admin = $this->getAdminAset();
        $this->actingAs($admin);
        $pegawaiAdmin = $admin->pegawai;
        $rolePegawai = Role::where('nama_role', 'Pegawai')->firstOrFail();

        // Upaya nonaktifkan akun sendiri
        $responseStatus = $this->post(route('user.status.update', $pegawaiAdmin), ['status_user' => 'nonaktif']);
        $this->assertTrue(session()->has('error') || session()->has('gagal'));
        $this->assertEquals('aktif', $admin->fresh()->status_user);

        // Upaya ubah role akun sendiri
        $responseRole = $this->post(route('user.role.update', $pegawaiAdmin), ['id_role' => $rolePegawai->id_role]);
        $this->assertTrue(session()->has('error') || session()->has('gagal'));
        $this->assertEquals('Admin Aset', $admin->fresh()->role->nama_role);
    }

    /**
     * 6. Admin Aset dapat melihat riwayat aktivitas dan detail perubahan tanpa bocoran password.
     */
    public function test_admin_aset_dapat_melihat_riwayat_aktivitas_dan_detail_perubahan(): void
    {
        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $skpd = Skpd::firstOrFail();
        $pegawai = Pegawai::create([
            'nip' => '199301012017011004',
            'nama_pegawai' => 'Pegawai Log Test',
            'id_skpd' => $skpd->id_skpd,
        ]);
        $this->post(route('user.akun.store', $pegawai), ['password' => 'password123'])->assertRedirect();

        $responseShow = $this->get(route('master-data.pegawai.show', $pegawai));
        $responseShow->assertStatus(200);
        $responseShow->assertSee('2. Riwayat Aktivitas');
        $responseShow->assertSee('id="tab-riwayat"', false);

        $logs = $responseShow->viewData('riwayatLogs');
        $this->assertNotEmpty($logs);

        // Verifikasi tidak ada plaintext password dalam response atau database activity_logs
        $responseShow->assertDontSee('password123');
        foreach ($logs as $log) {
            $this->assertArrayNotHasKey('password', $log->old_values ?? []);
            $this->assertArrayNotHasKey('password', $log->new_values ?? []);
        }
    }

    // =========================================================================
    // B. PENGUJIAN ADMIN UPT
    // =========================================================================

    /**
     * 7. Admin UPT tidak melihat tombol Buat Akun, Ubah Role, dan Nonaktifkan Akun di index.
     */
    public function test_admin_upt_tidak_melihat_tombol_aksi_akun_di_index(): void
    {
        $adminUpt = $this->getAdminUpt();
        $idSkpdUpt = $adminUpt->pegawai->id_skpd;
        $this->assertNotNull($idSkpdUpt);

        // Buat pegawai di UPT miliknya (tanpa akun)
        $pegTanpaAkun = Pegawai::create([
            'nip' => '199401012018011005',
            'nama_pegawai' => 'Pegawai UPT No Account',
            'id_skpd' => $idSkpdUpt,
        ]);

        // Buat pegawai di UPT miliknya (dengan akun)
        $rolePegawai = Role::where('nama_role', 'Pegawai')->firstOrFail();
        $pegDenganAkun = Pegawai::create([
            'nip' => '199401012018011006',
            'nama_pegawai' => 'Pegawai UPT Has Account',
            'id_skpd' => $idSkpdUpt,
        ]);
        User::create([
            'id_pegawai' => $pegDenganAkun->id_pegawai,
            'username' => '199401012018011006',
            'password' => bcrypt('password123'),
            'id_role' => $rolePegawai->id_role,
            'status_user' => 'aktif',
        ]);

        $this->actingAs($adminUpt);

        $response = $this->get(route('master-data.pegawai.index'));
        $response->assertStatus(200);

        // Pastikan tombol-tombol aksi akun TIDAK ADA
        $response->assertDontSee('title="Buat Akun"', false);
        $response->assertDontSee('title="Ubah Role"', false);
        $response->assertDontSee('title="Nonaktifkan Akun"', false);
        $response->assertDontSee('title="Aktifkan Akun"', false);

        // Pastikan modal-modal aksi akun TIDAK dirender
        $response->assertDontSee('id="modal-buat-akun"', false);
        $response->assertDontSee('id="modal-ubah-role"', false);
        $response->assertDontSee('id="modal-ubah-status"', false);

        // Namun tombol Detail Pegawai dan Edit Pegawai TETAP ADA
        $response->assertSee('title="Detail Pegawai"', false);
        $response->assertSee('title="Edit Pegawai"', false);
    }

    /**
     * 8. Admin UPT ditolak HTTP 403 saat mencoba mengakses langsung endpoint pengelolaan akun.
     */
    public function test_admin_upt_akses_langsung_endpoint_akun_ditolak_403(): void
    {
        $adminUpt = $this->getAdminUpt();
        $idSkpdUpt = $adminUpt->pegawai->id_skpd;

        $pegawaiInternal = Pegawai::create([
            'nip' => '199501012019011007',
            'nama_pegawai' => 'Pegawai UPT Akses Test',
            'id_skpd' => $idSkpdUpt,
        ]);

        $rolePegawai = Role::where('nama_role', 'Pegawai')->firstOrFail();
        $roleAdminUpt = Role::where('nama_role', 'Admin UPT')->firstOrFail();

        $this->actingAs($adminUpt);

        // 1. Upaya Buat Akun -> 403
        $this->post(route('user.akun.store', $pegawaiInternal), [
            'password' => 'password123',
        ])->assertStatus(403);
        $this->assertFalse($pegawaiInternal->user()->exists());

        // Siapkan akun untuk pengujian update role dan status
        User::create([
            'id_pegawai' => $pegawaiInternal->id_pegawai,
            'username' => '199501012019011007',
            'password' => bcrypt('password123'),
            'id_role' => $rolePegawai->id_role,
            'status_user' => 'aktif',
        ]);

        // 2. Upaya Ubah Role -> 403
        $this->post(route('user.role.update', $pegawaiInternal), [
            'id_role' => $roleAdminUpt->id_role,
        ])->assertStatus(403);
        $this->assertEquals($rolePegawai->id_role, $pegawaiInternal->fresh()->user->id_role);

        // 3. Upaya Ubah Status -> 403
        $this->post(route('user.status.update', $pegawaiInternal), [
            'status_user' => 'nonaktif',
        ])->assertStatus(403);
        $this->assertEquals('aktif', $pegawaiInternal->fresh()->user->status_user);

        // 4. Upaya Update Akun -> 403
        $this->post(route('user.update', $pegawaiInternal), [
            'username' => 'hack_username',
        ])->assertStatus(403);

        // 5. Upaya GET user index & show -> 403
        $this->get(route('user.index'))->assertStatus(403);
        $this->get(route('user.show', $pegawaiInternal))->assertStatus(403);
    }

    /**
     * 9. Admin UPT tidak dapat melihat Tab Riwayat Aktivitas dan backend tidak mengirimkan data log.
     */
    public function test_admin_upt_tidak_melihat_tab_riwayat_aktivitas_dan_backend_kirim_empty(): void
    {
        $adminUpt = $this->getAdminUpt();
        $idSkpdUpt = $adminUpt->pegawai->id_skpd;

        $pegawaiInternal = Pegawai::create([
            'nip' => '199601012020011008',
            'nama_pegawai' => 'Pegawai UPT Riwayat Test',
            'id_skpd' => $idSkpdUpt,
        ]);

        // Buat dummy log aktivitas untuk pegawai ini
        ActivityLog::create([
            'log_type' => 'pegawai.created',
            'description' => 'Membuat pegawai tes log',
            'subject_type' => Pegawai::class,
            'subject_id' => $pegawaiInternal->id_pegawai,
            'id_user' => $adminUpt->id_user,
            'nama_aktor' => 'Admin UPT',
        ]);

        $this->actingAs($adminUpt);

        $response = $this->get(route('master-data.pegawai.show', $pegawaiInternal));
        $response->assertStatus(200);

        // Tab Riwayat Aktivitas TIDAK boleh tampil untuk Admin UPT
        $response->assertDontSee('2. Riwayat Aktivitas');
        $response->assertDontSee('id="tab-riwayat"', false);

        // Data riwayatLogs yang dikirim ke view WAJIB kosong
        $riwayatLogs = $response->viewData('riwayatLogs');
        $this->assertEmpty($riwayatLogs);
    }

    /**
     * 10. Admin UPT tetap dapat mengelola data fisik pegawai di UPT miliknya.
     */
    public function test_admin_upt_tetap_dapat_mengelola_data_fisik_pegawai_di_upt_miliknya(): void
    {
        $adminUpt = $this->getAdminUpt();
        $idSkpdUpt = $adminUpt->pegawai->id_skpd;

        // Ambil ruangan di lokasi SKPD UPT
        $lokasi = \App\Models\Lokasi::untukSkpd($idSkpdUpt);
        $ruangan = \App\Models\Ruangan::where('id_lokasi', $lokasi?->id_lokasi)->first();

        $this->actingAs($adminUpt);

        // 1. Tambah pegawai baru di UPT-nya
        $responseStore = $this->post(route('master-data.pegawai.store'), [
            'nip' => '199701012021011009',
            'nama_pegawai' => 'Pegawai Baru UPT Sukses',
            'jabatan' => 'Tenaga Lapangan',
            'id_skpd' => $idSkpdUpt,
            'id_ruangan' => $ruangan?->id_ruangan,
        ]);
        $responseStore->assertRedirect();
        $this->assertDatabaseHas('pegawai', [
            'nip' => '199701012021011009',
            'id_skpd' => $idSkpdUpt,
        ]);

        $pegBaru = Pegawai::where('nip', '199701012021011009')->firstOrFail();

        // 2. Edit pegawai di UPT-nya
        $responseUpdate = $this->put(route('master-data.pegawai.update', $pegBaru), [
            'nip' => '199701012021011009',
            'nama_pegawai' => 'Pegawai Baru UPT Terupdate',
            'jabatan' => 'Koordinator Lapangan',
            'id_skpd' => $idSkpdUpt,
            'id_ruangan' => $ruangan?->id_ruangan,
        ]);
        $responseUpdate->assertRedirect();
        $this->assertEquals('Pegawai Baru UPT Terupdate', $pegBaru->fresh()->nama_pegawai);
    }

    // =========================================================================
    // C. PENGUJIAN ADMIN BIDANG DAN PEGAWAI
    // =========================================================================

    /**
     * 11. Admin Bidang dan Pegawai ditolak dari seluruh endpoint master pegawai dan akun.
     */
    public function test_admin_bidang_dan_pegawai_ditolak_dari_fitur_pegawai_dan_akun(): void
    {
        $adminBidang = $this->getAdminBidang();
        $pegawaiUser = $this->getPegawaiUser();
        $targetPegawai = Pegawai::firstOrFail();

        // Uji Admin Bidang
        $this->actingAs($adminBidang);
        $this->get(route('master-data.pegawai.index'))->assertStatus(403);
        $this->get(route('master-data.pegawai.show', $targetPegawai))->assertStatus(403);
        $this->post(route('user.akun.store', $targetPegawai))->assertStatus(403);
        $this->post(route('user.role.update', $targetPegawai))->assertStatus(403);
        $this->post(route('user.status.update', $targetPegawai))->assertStatus(403);

        // Uji Pegawai
        $this->actingAs($pegawaiUser);
        $this->get(route('master-data.pegawai.index'))->assertStatus(403);
        $this->get(route('master-data.pegawai.show', $targetPegawai))->assertStatus(403);
        $this->post(route('user.akun.store', $targetPegawai))->assertStatus(403);
        $this->post(route('user.role.update', $targetPegawai))->assertStatus(403);
        $this->post(route('user.status.update', $targetPegawai))->assertStatus(403);
    }
}
