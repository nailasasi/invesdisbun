<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Pegawai;
use App\Models\Role;
use App\Models\Ruangan;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ManajemenPegawaiRefactorTest extends TestCase
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

    private function getAdminAset(): User
    {
        return User::whereHas('role', fn ($q) => $q->where('nama_role', 'Admin Aset'))->firstOrFail();
    }

    private function getAdminBidang(): User
    {
        return User::whereHas('role', fn ($q) => $q->where('nama_role', 'Admin Bidang'))->firstOrFail();
    }

    private function getAdminUpt(): User
    {
        return User::whereHas('role', fn ($q) => $q->where('nama_role', 'Admin UPT'))->firstOrFail();
    }

    private function getPegawaiUser(): User
    {
        return User::whereHas('role', fn ($q) => $q->where('nama_role', 'Pegawai'))->firstOrFail();
    }

    /**
     * 1. Daftar Manajemen Pegawai menampilkan seluruh pegawai satu kali.
     */
    public function test_01_daftar_manajemen_pegawai_menampilkan_seluruh_pegawai_satu_kali(): void
    {
        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $response = $this->get(route('master-data.pegawai.index'));
        $response->assertStatus(200);
        $response->assertViewIs('master-data.pegawai.index');

        $pegawaiList = $response->viewData('pegawaiList');
        $this->assertNotNull($pegawaiList);

        // Pastikan tidak ada duplikasi ID pegawai dalam satu halaman
        $ids = $pegawaiList->pluck('id_pegawai');
        $this->assertEquals($ids->count(), $ids->unique()->count());

        // Header halaman Manajemen Pegawai
        $response->assertSee('Manajemen Pegawai');
        $response->assertSee('Role');
        $response->assertSee('Status Akun');
    }

    /**
     * 2. Pegawai tanpa akun memiliki status Belum Ada Akun.
     */
    public function test_02_pegawai_tanpa_akun_memiliki_status_belum_ada_akun(): void
    {
        $pegawai = Pegawai::create([
            'nip' => '9999000011112222',
            'nama_pegawai' => 'Pegawai Dummy Tanpa Akun',
            'jabatan' => 'Staf Pengujian',
            'id_skpd' => 1,
        ]);

        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        // Cek pada daftar (index): ikon Detail, Edit, dan Buat Akun ADA. Ikon Ubah Role & Status TIDAK ada.
        $responseIndex = $this->get(route('master-data.pegawai.index', ['search' => '9999000011112222']));
        $responseIndex->assertStatus(200);
        $responseIndex->assertSee('Belum Ada Akun');
        $responseIndex->assertSee('title="Detail Pegawai"', false);
        $responseIndex->assertSee('title="Edit Pegawai"', false);
        $responseIndex->assertSee('title="Buat Akun"', false);
        $responseIndex->assertDontSee('title="Ubah Role"', false);
        $responseIndex->assertDontSee('title="Nonaktifkan Akun"', false);
        $responseIndex->assertDontSee('title="Aktifkan Akun"', false);

        // Cek pada detail (show): tepat 2 tab, ada badge Belum Ada Akun, TIDAK ada tombol Edit maupun Buat Akun (detail strictly read-only)
        $responseShow = $this->get(route('master-data.pegawai.show', $pegawai));
        $responseShow->assertStatus(200);
        $responseShow->assertSee('1. Data Pegawai &amp; Akun', false);
        $responseShow->assertSee('2. Riwayat Aktivitas');
        $responseShow->assertDontSee('3. Riwayat Aktivitas');
        $responseShow->assertSee('Belum Ada Akun');
        $responseShow->assertDontSee('id="btn-buat-akun"', false);
        $responseShow->assertDontSee('id="buat-akun-modal"', false);
        $responseShow->assertDontSee('Edit Pegawai');
    }

    /**
     * 3. Pegawai dengan akun aktif memiliki status Aktif dan ikon Nonaktifkan pada index.
     */
    public function test_03_pegawai_dengan_akun_aktif_memiliki_status_aktif(): void
    {
        $pegawai = Pegawai::create([
            'nip' => '9999000011112233',
            'nama_pegawai' => 'Pegawai Dummy Akun Aktif',
            'jabatan' => 'Staf Pengujian Aktif',
            'id_skpd' => 1,
        ]);

        $rolePegawai = Role::where('nama_role', 'Pegawai')->firstOrFail();

        $user = User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'username' => 'dummy_aktif_user',
            'password' => bcrypt('password123'),
            'id_role' => $rolePegawai->id_role,
            'status_user' => 'aktif',
        ]);

        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        // Cek pada daftar (index): status Aktif, ikon Ubah Role, ikon Nonaktifkan Akun
        $responseIndex = $this->get(route('master-data.pegawai.index', ['search' => '9999000011112233']));
        $responseIndex->assertStatus(200);
        $responseIndex->assertSee('Aktif');
        $responseIndex->assertSee('Ubah Role');
        $responseIndex->assertSee('Nonaktifkan Akun');
        $responseIndex->assertDontSee('title="Aktifkan Akun"', false);

        // Cek pada detail (show): tepat 2 tab, tidak ada tombol Edit Pegawai atau Ubah Role/Status redundan
        $responseShow = $this->get(route('master-data.pegawai.show', $pegawai));
        $responseShow->assertStatus(200);
        $responseShow->assertSee('1. Data Pegawai &amp; Akun', false);
        $responseShow->assertSee('2. Riwayat Aktivitas');
        $responseShow->assertDontSee('3. Riwayat Aktivitas');
        $responseShow->assertDontSee('Edit Pegawai');
        $responseShow->assertDontSee('title="Nonaktifkan Akun"', false);
    }

    /**
     * 4. Pegawai dengan akun nonaktif memiliki status Nonaktif dan ikon Aktifkan pada index.
     */
    public function test_04_pegawai_dengan_akun_nonaktif_memiliki_status_nonaktif(): void
    {
        $pegawai = Pegawai::create([
            'nip' => '9999000011112244',
            'nama_pegawai' => 'Pegawai Dummy Nonaktif',
            'jabatan' => 'Staf Pengujian Nonaktif',
            'id_skpd' => 1,
        ]);

        $rolePegawai = Role::where('nama_role', 'Pegawai')->firstOrFail();

        $user = User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'username' => 'dummy_nonaktif_user',
            'password' => bcrypt('password123'),
            'id_role' => $rolePegawai->id_role,
            'status_user' => 'nonaktif',
        ]);

        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        // Cek pada daftar (index): status Nonaktif, ikon Ubah Role, ikon Aktifkan Akun (dan BUKAN Nonaktifkan)
        $responseIndex = $this->get(route('master-data.pegawai.index', ['search' => '9999000011112244']));
        $responseIndex->assertStatus(200);
        $responseIndex->assertSee('Nonaktif');
        $responseIndex->assertSee('Ubah Role');
        $responseIndex->assertSee('Aktifkan Akun');
        $responseIndex->assertDontSee('title="Nonaktifkan Akun"', false);

        // Cek pada detail (show): status Nonaktif, tidak ada tombol redundan
        $responseShow = $this->get(route('master-data.pegawai.show', $pegawai));
        $responseShow->assertStatus(200);
        $responseShow->assertSee('Nonaktif');
        $responseShow->assertDontSee('Edit Pegawai');
        $responseShow->assertDontSee('title="Aktifkan Akun"', false);
    }

    /**
     * 5. Admin dapat membuat akun dari detail pegawai.
     */
    public function test_05_admin_dapat_membuat_akun_dari_detail_pegawai(): void
    {
        $pegawai = Pegawai::create([
            'nip' => '199912312024011001',
            'nama_pegawai' => 'Pegawai Siap Buat Akun',
            'jabatan' => 'Staf Bidang',
            'id_skpd' => 2, // Bidang Perlindungan Perkebunan
        ]);

        $rolePegawai = Role::where('nama_role', 'Pegawai')->firstOrFail();

        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $response = $this->post(route('user.akun.store', $pegawai), [
            'id_role' => $rolePegawai->id_role,
        ]);

        $response->assertRedirect(route('master-data.pegawai.show', ['pegawai' => $pegawai, 'tab' => 'tab-akun']));
        $this->assertTrue(session()->has('success') || session()->has('berhasil'));

        $pegawai->refresh();
        $this->assertNotNull($pegawai->user);
        $this->assertEquals($pegawai->nip, $pegawai->user->username);
        $this->assertEquals('aktif', $pegawai->user->status_user);
        $this->assertEquals($rolePegawai->id_role, $pegawai->user->id_role);
    }

    /**
     * 6. Role akun baru otomatis Pegawai & validasi role tidak sesuai SKPD pada Ubah Role ditolak backend.
     */
    public function test_06_role_tidak_sesuai_skpd_ditolak_backend(): void
    {
        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $rolePegawai = Role::where('nama_role', 'Pegawai')->firstOrFail();
        $roleAdminUpt = Role::where('nama_role', 'Admin UPT')->firstOrFail();
        $roleAdminBidang = Role::where('nama_role', 'Admin Bidang')->firstOrFail();
        $roleAdminAset = Role::where('nama_role', 'Admin Aset')->firstOrFail();

        // 1. Parameter tampering saat buat akun: mencoba kirim id_role Admin UPT untuk pegawai Bidang
        // Sistem WAJIB mengabaikan id_role dari request dan tetap menetapkan role Pegawai secara otomatis.
        $pegawaiBidang = Pegawai::create([
            'nip' => '199912312024011002',
            'nama_pegawai' => 'Pegawai Bidang Uji Role',
            'id_skpd' => 2,
        ]);

        $responseTamper = $this->post(route('user.akun.store', $pegawaiBidang), [
            'id_role' => $roleAdminUpt->id_role,
        ]);
        $responseTamper->assertRedirect();
        $this->assertTrue($pegawaiBidang->user()->exists());
        $this->assertEquals($rolePegawai->id_role, $pegawaiBidang->user->id_role, 'Akun baru wajib otomatis ber-role Pegawai meskipun request menyertakan id_role lain.');

        // 2. Pada fitur Ubah Role: Pegawai Bidang ditolak jika diubah ke Admin UPT
        $responseA = $this->post(route('user.role.update', $pegawaiBidang), [
            'id_role' => $roleAdminUpt->id_role,
        ]);
        $responseA->assertSessionHasErrors('id_role');

        // 3. Pegawai UPT (id_skpd 6): buat akun otomatis Pegawai
        $pegawaiUpt = Pegawai::create([
            'nip' => '199912312024011003',
            'nama_pegawai' => 'Pegawai UPT Uji Role',
            'id_skpd' => 6,
        ]);
        $this->post(route('user.akun.store', $pegawaiUpt))->assertRedirect();
        $this->assertEquals($rolePegawai->id_role, $pegawaiUpt->fresh()->user->id_role);

        // Kasus B: Pegawai UPT ditolak jika diubah ke Admin Bidang
        $responseB = $this->post(route('user.role.update', $pegawaiUpt), [
            'id_role' => $roleAdminBidang->id_role,
        ]);
        $responseB->assertSessionHasErrors('id_role');

        // Kasus C: Pegawai UPT ditolak jika diubah ke Admin Aset
        $responseC = $this->post(route('user.role.update', $pegawaiUpt), [
            'id_role' => $roleAdminAset->id_role,
        ]);
        $responseC->assertSessionHasErrors('id_role');

        // Kasus D: Pegawai UPT diubah ke Admin UPT -> Berhasil
        $responseD = $this->post(route('user.role.update', $pegawaiUpt), [
            'id_role' => $roleAdminUpt->id_role,
        ]);
        $responseD->assertSessionHas('success');
        $this->assertEquals($roleAdminUpt->id_role, $pegawaiUpt->fresh()->user->id_role);
    }

    /**
     * 7. Perubahan SKPD yang tidak sesuai role ditolak.
     */
    public function test_07_perubahan_skpd_yang_tidak_sesuai_role_ditolak(): void
    {
        $roleAdminBidang = Role::where('nama_role', 'Admin Bidang')->firstOrFail();

        $pegawai = Pegawai::create([
            'nip' => '199912312024011004',
            'nama_pegawai' => 'Pegawai Admin Bidang Uji Pindah',
            'jabatan' => 'Kepala Seksi',
            'id_skpd' => 2, // Bidang
        ]);

        User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'username' => 'admin_bidang_pindah',
            'password' => bcrypt('password123'),
            'id_role' => $roleAdminBidang->id_role,
            'status_user' => 'aktif',
        ]);

        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        // Coba pindahkan SKPD ke UPT (id_skpd 6) tanpa mengubah role terlebih dahulu
        $ruangUpt = Ruangan::where('id_lokasi', 2)->value('id_ruangan');
        $response = $this->put(route('master-data.pegawai.update', $pegawai), [
            'nip' => $pegawai->nip,
            'nama_pegawai' => $pegawai->nama_pegawai,
            'jabatan' => $pegawai->jabatan,
            'id_skpd' => 6, // UPT
            'id_ruangan' => $ruangUpt,
        ]);

        $response->assertSessionHasErrors('id_skpd');

        $pegawai->refresh();
        $this->assertEquals(2, $pegawai->id_skpd, 'SKPD pegawai tidak boleh berubah bila timbul konflik role.');
    }

    /**
     * 8. Perubahan role tidak mengubah data identitas pegawai.
     */
    public function test_08_perubahan_role_tidak_mengubah_data_identitas_pegawai(): void
    {
        $rolePegawai = Role::where('nama_role', 'Pegawai')->firstOrFail();
        $roleAdminBidang = Role::where('nama_role', 'Admin Bidang')->firstOrFail();

        $pegawai = Pegawai::create([
            'nip' => '199912312024011005',
            'nama_pegawai' => 'Identitas Pegawai Tidak Boleh Berubah',
            'jabatan' => 'Analis Data Perkebunan',
            'id_skpd' => 2, // Bidang
        ]);

        $user = User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'username' => 'user_ubah_role',
            'password' => bcrypt('password123'),
            'id_role' => $rolePegawai->id_role,
            'status_user' => 'aktif',
        ]);

        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $response = $this->post(route('user.role.update', $pegawai), [
            'id_role' => $roleAdminBidang->id_role,
        ]);

        $response->assertRedirect(route('master-data.pegawai.show', ['pegawai' => $pegawai, 'tab' => 'tab-akun']));

        $pegawai->refresh();
        $user->refresh();

        // Role user berubah
        $this->assertEquals($roleAdminBidang->id_role, $user->id_role);

        // Identitas pegawai tetap utuh
        $this->assertEquals('199912312024011005', $pegawai->nip);
        $this->assertEquals('Identitas Pegawai Tidak Boleh Berubah', $pegawai->nama_pegawai);
        $this->assertEquals('Analis Data Perkebunan', $pegawai->jabatan);
        $this->assertEquals(2, $pegawai->id_skpd);
    }

    /**
     * 9. Edit Pegawai tidak merusak akun yang terhubung.
     */
    public function test_09_edit_pegawai_tidak_merusak_akun_yang_terhubung(): void
    {
        $rolePegawai = Role::where('nama_role', 'Pegawai')->firstOrFail();

        $pegawai = Pegawai::create([
            'nip' => '199912312024011006',
            'nama_pegawai' => 'Nama Sebelum Diedit',
            'jabatan' => 'Staf Sebelum',
            'id_skpd' => 1,
        ]);

        $user = User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'username' => 'username_tetap_aman',
            'password' => bcrypt('password123'),
            'id_role' => $rolePegawai->id_role,
            'status_user' => 'aktif',
        ]);

        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $ruangDinas = Ruangan::where('id_lokasi', 1)->value('id_ruangan');
        $response = $this->put(route('master-data.pegawai.update', $pegawai), [
            'nip' => $pegawai->nip,
            'nama_pegawai' => 'Nama Sesudah Diedit',
            'jabatan' => 'Staf Sesudah',
            'id_skpd' => 1,
            'id_ruangan' => $ruangDinas,
        ]);

        $response->assertRedirect(route('master-data.pegawai.show', $pegawai));

        $user->refresh();
        $pegawai->refresh();

        // Data pegawai terupdate
        $this->assertEquals('Nama Sesudah Diedit', $pegawai->nama_pegawai);
        $this->assertEquals('Staf Sesudah', $pegawai->jabatan);

        // Akun user tetap utuh dan terhubung
        $this->assertEquals($pegawai->id_pegawai, $user->id_pegawai);
        $this->assertEquals('username_tetap_aman', $user->username);
        $this->assertEquals($rolePegawai->id_role, $user->id_role);
        $this->assertEquals('aktif', $user->status_user);
    }

    /**
     * 10. Riwayat aktivitas tetap tampil.
     */
    public function test_10_riwayat_aktivitas_tetap_tampil(): void
    {
        $admin = $this->getAdminAset();

        $pegawai = Pegawai::create([
            'nip' => '199912312024011007',
            'nama_pegawai' => 'Pegawai Dengan Riwayat Log',
            'id_skpd' => 1,
        ]);

        ActivityLog::create([
            'log_type' => 'pegawai.created',
            'description' => 'Menambahkan data pegawai Pegawai Dengan Riwayat Log.',
            'subject_type' => Pegawai::class,
            'subject_id' => $pegawai->id_pegawai,
            'id_user' => $admin->id_user,
            'nama_aktor' => $admin->pegawai?->nama_pegawai ?? 'Admin',
            'old_values' => [],
            'new_values' => ['nama_pegawai' => 'Pegawai Dengan Riwayat Log'],
        ]);

        $this->actingAs($admin);

        $response = $this->get(route('master-data.pegawai.show', $pegawai));
        $response->assertStatus(200);

        $riwayatLogs = $response->viewData('riwayatLogs');
        $this->assertNotNull($riwayatLogs);
        $this->assertGreaterThanOrEqual(1, $riwayatLogs->count());

        $response->assertSee('Menambahkan data pegawai Pegawai Dengan Riwayat Log.');
        $response->assertSee('Riwayat Aktivitas Pegawai');
    }

    /**
     * 11. Pegawai dan Admin Bidang tetap tidak memperoleh akses yang dilarang.
     */
    public function test_11_pegawai_dan_admin_bidang_tidak_memperoleh_akses_manajemen_pegawai(): void
    {
        $pegawaiDinas = Pegawai::first();

        // 11.A: Akses oleh role Pegawai
        $userPegawai = $this->getPegawaiUser();
        $this->actingAs($userPegawai);

        $this->get(route('master-data.pegawai.index'))->assertStatus(403);
        $this->get(route('master-data.pegawai.show', $pegawaiDinas))->assertStatus(403);
        $this->get(route('master-data.pegawai.create'))->assertStatus(403);
        $this->post(route('master-data.pegawai.store'), [])->assertStatus(403);
        $this->post(route('user.akun.store', $pegawaiDinas), [])->assertStatus(403);

        // 11.B: Akses oleh role Admin Bidang
        $userBidang = $this->getAdminBidang();
        $this->actingAs($userBidang);

        $this->get(route('master-data.pegawai.index'))->assertStatus(403);
        $this->get(route('master-data.pegawai.show', $pegawaiDinas))->assertStatus(403);
        $this->get(route('master-data.pegawai.create'))->assertStatus(403);
        $this->post(route('master-data.pegawai.store'), [])->assertStatus(403);
        $this->post(route('user.akun.store', $pegawaiDinas), [])->assertStatus(403);
    }

    /**
     * 12. Admin UPT tetap hanya dapat mengakses pegawai dan akun dalam UPT-nya.
     */
    public function test_12_admin_upt_hanya_dapat_mengakses_pegawai_dan_akun_dalam_upt_nya(): void
    {
        $adminUpt = $this->getAdminUpt();
        $idSkpdUpt = $adminUpt->pegawai->id_skpd;
        $this->assertNotNull($idSkpdUpt);

        $this->actingAs($adminUpt);

        // Akses daftar pegawai UPT
        $responseIndex = $this->get(route('master-data.pegawai.index'));
        $responseIndex->assertStatus(200);

        $pegawaiList = $responseIndex->viewData('pegawaiList');
        foreach ($pegawaiList as $item) {
            $this->assertEquals($idSkpdUpt, $item->id_skpd, 'Admin UPT tidak boleh melihat pegawai di luar SKPD UPT miliknya.');
        }

        // Coba akses pegawai di luar UPT (misal SKPD 1: Sekretariat)
        $pegawaiDinasLain = Pegawai::where('id_skpd', 1)->firstOrFail();
        $this->get(route('master-data.pegawai.show', $pegawaiDinasLain))->assertStatus(403);
        $this->post(route('user.akun.store', $pegawaiDinasLain), ['id_role' => 8])->assertStatus(403);

        // Akses pegawai di dalam UPT-nya sendiri diperbolehkan
        $pegawaiDalamUpt = Pegawai::where('id_skpd', $idSkpdUpt)->firstOrFail();
        $this->get(route('master-data.pegawai.show', $pegawaiDalamUpt))->assertStatus(200);
    }

    /**
     * 13. Akun nonaktif tidak dapat melanjutkan akses setelah request berikutnya.
     */
    public function test_13_akun_nonaktif_tidak_dapat_melanjutkan_akses_setelah_request_berikutnya(): void
    {
        $pegawai = Pegawai::create([
            'nip' => '199912312024011008',
            'nama_pegawai' => 'User Sesi Nonaktif Test',
            'id_skpd' => 1,
        ]);

        $roleAdminAset = Role::where('nama_role', 'Admin Aset')->firstOrFail();

        $user = User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'username' => 'test_sesi_nonaktif',
            'password' => bcrypt('password123'),
            'id_role' => $roleAdminAset->id_role,
            'status_user' => 'aktif',
        ]);

        // Simulasikan login aktif
        $this->actingAs($user);
        $this->get(route('dashboard'))->assertStatus(200);

        // Akun kemudian dinonaktifkan oleh admin lain di background
        $user->update(['status_user' => 'nonaktif']);

        // Request berikutnya harus langsung diarahkan keluar dan sesi dibatalkan
        $responseNext = $this->get(route('dashboard'));
        $responseNext->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /**
     * 14. Modul lain tetap dapat membaca relasi pegawai dan user.
     */
    public function test_14_modul_lain_tetap_dapat_membaca_relasi_pegawai_dan_user(): void
    {
        $pegawai = Pegawai::with(['skpd', 'ruangan', 'user.role', 'pemegangAset', 'dokumenSppbi'])->firstOrFail();

        $this->assertInstanceOf(Pegawai::class, $pegawai);
        $this->assertTrue(method_exists($pegawai, 'skpd'));
        $this->assertTrue(method_exists($pegawai, 'ruangan'));
        $this->assertTrue(method_exists($pegawai, 'user'));
        $this->assertTrue(method_exists($pegawai, 'pemegangAset'));
        $this->assertTrue(method_exists($pegawai, 'dokumenSppbi'));

        $user = User::with(['role', 'pegawai.skpd'])->firstOrFail();
        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue(method_exists($user, 'pegawai'));
        $this->assertTrue(method_exists($user, 'role'));
        $this->assertInstanceOf(Pegawai::class, $user->pegawai);

        // Verifikasi timestamps Pegawai aktif dan terisi objek Carbon / string
        $this->assertTrue($pegawai->usesTimestamps());
    }

    /**
     * 15. Password bersifat opsional untuk seluruh pegawai termasuk NIP pendek (default: NIP).
     */
    public function test_15_nip_kurang_dari_8_karakter_wajib_password_manual(): void
    {
        $pegawai = Pegawai::create([
            'nip' => '12345',
            'nama_pegawai' => 'Pegawai NIP Pendek',
            'id_skpd' => 1,
        ]);

        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        // 1. Password kurang dari 8 karakter saat diisi manual -> gagal validasi min:8
        $responsePasswordPendek = $this->post(route('user.akun.store', $pegawai), [
            'password' => 'pendek',
        ]);
        $responsePasswordPendek->assertSessionHasErrors('password');
        $this->assertFalse($pegawai->user()->exists());

        // 2. Tanpa password (kosong) -> BERHASIL (menggunakan NIP sebagai password awal)
        $responseTanpaPassword = $this->post(route('user.akun.store', $pegawai), [
            'password' => '',
        ]);
        $responseTanpaPassword->assertRedirect(route('master-data.pegawai.show', ['pegawai' => $pegawai, 'tab' => 'tab-akun']));
        $pegawai->refresh();
        $this->assertTrue($pegawai->user()->exists());
        $this->assertEquals('12345', $pegawai->user->username);
        $rolePegawai = Role::where('nama_role', 'Pegawai')->firstOrFail();
        $this->assertEquals($rolePegawai->id_role, $pegawai->user->id_role);
    }

    /**
     * 16. Ubah Role via JSON request (modal index) berhasil dan mengembalikan JSON.
     */
    public function test_16_ubah_role_via_json_request_berhasil_dan_mengembalikan_json(): void
    {
        $rolePegawai = Role::where('nama_role', 'Pegawai')->firstOrFail();
        $roleAdminBidang = Role::where('nama_role', 'Admin Bidang')->firstOrFail();

        $pegawai = Pegawai::create([
            'nip' => '199912312024011099',
            'nama_pegawai' => 'Pegawai Uji Ubah Role JSON',
            'jabatan' => 'Staf Bidang',
            'id_skpd' => 2, // Bidang
        ]);

        $user = User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'username' => 'test_role_json',
            'password' => bcrypt('password123'),
            'id_role' => $rolePegawai->id_role,
            'status_user' => 'aktif',
        ]);

        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $response = $this->postJson(route('user.role.update', $pegawai), [
            'id_role' => $roleAdminBidang->id_role,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $user->refresh();
        $this->assertEquals($roleAdminBidang->id_role, $user->id_role);
    }

    /**
     * 17. Ubah Status Akun via JSON request (modal index) berhasil dan mengembalikan JSON.
     */
    public function test_17_ubah_status_akun_via_json_request_berhasil_dan_mengembalikan_json(): void
    {
        $rolePegawai = Role::where('nama_role', 'Pegawai')->firstOrFail();

        $pegawai = Pegawai::create([
            'nip' => '199912312024011098',
            'nama_pegawai' => 'Pegawai Uji Ubah Status JSON',
            'jabatan' => 'Staf Bidang',
            'id_skpd' => 2,
        ]);

        $user = User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'username' => 'test_status_json',
            'password' => bcrypt('password123'),
            'id_role' => $rolePegawai->id_role,
            'status_user' => 'aktif',
        ]);

        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        // Menonaktifkan akun
        $responseNonaktif = $this->postJson(route('user.status.update', $pegawai), [
            'status_user' => 'nonaktif',
        ]);
        $responseNonaktif->assertStatus(200);
        $responseNonaktif->assertJson([
            'success' => true,
        ]);

        $user->refresh();
        $this->assertEquals('nonaktif', $user->status_user);

        // Mengaktifkan kembali akun
        $responseAktif = $this->postJson(route('user.status.update', $pegawai), [
            'status_user' => 'aktif',
        ]);
        $responseAktif->assertStatus(200);
        $responseAktif->assertJson([
            'success' => true,
        ]);

        $user->refresh();
        $this->assertEquals('aktif', $user->status_user);
    }

    /**
     * 18. Edit data pegawai via AJAX (modal edit) berhasil dan mengembalikan JSON success.
     */
    public function test_18_edit_pegawai_via_ajax_update_berhasil(): void
    {
        $pegawai = Pegawai::create([
            'nip' => '199001012020011088',
            'nama_pegawai' => 'Nama Awal Pegawai Edit',
            'jabatan' => 'Jabatan Lama',
            'id_skpd' => 1,
        ]);

        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        // Edit endpoint mengembalikan JSON bila diminta via expectsJson()
        $responseEditJson = $this->getJson(route('master-data.pegawai.edit', $pegawai));
        $responseEditJson->assertStatus(200);
        $responseEditJson->assertJson([
            'success' => true,
            'pegawai' => [
                'id_pegawai' => $pegawai->id_pegawai,
                'nip' => '199001012020011088',
            ],
        ]);

        // Simpan pembaruan via PUT AJAX
        $ruangDinas = Ruangan::where('id_lokasi', 1)->value('id_ruangan');
        $responseUpdate = $this->putJson(route('master-data.pegawai.update', $pegawai), [
            'nip' => '199001012020011088',
            'nama_pegawai' => 'Nama Baru Pegawai Terupdate',
            'jabatan' => 'Jabatan Baru Terupdate',
            'id_skpd' => 1,
            'id_ruangan' => $ruangDinas,
        ]);

        $responseUpdate->assertStatus(200);
        $responseUpdate->assertJson([
            'success' => true,
        ]);

        $pegawai->refresh();
        $this->assertEquals('Nama Baru Pegawai Terupdate', $pegawai->nama_pegawai);
        $this->assertEquals('Jabatan Baru Terupdate', $pegawai->jabatan);

        // Log aktivitas pegawai.updated tercatat
        $this->assertTrue(
            ActivityLog::where('subject_type', Pegawai::class)
                ->where('subject_id', $pegawai->id_pegawai)
                ->where('log_type', 'pegawai.updated')
                ->exists()
        );
    }

    /**
     * 19. Edit pegawai via AJAX jika validasi gagal mengembalikan 422 JSON tanpa merusak data lama.
     */
    public function test_19_edit_pegawai_via_ajax_validation_error_returns_422(): void
    {
        $pegawai = Pegawai::create([
            'nip' => '199001012020011077',
            'nama_pegawai' => 'Pegawai Asli Sebelum Gagal Validasi',
            'jabatan' => 'Staf Bidang',
            'id_skpd' => 1,
        ]);

        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        // Nama pegawai dikosongkan (wajib diisi)
        $response = $this->putJson(route('master-data.pegawai.update', $pegawai), [
            'nip' => '199001012020011077',
            'nama_pegawai' => '',
            'id_skpd' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['nama_pegawai']);

        $pegawai->refresh();
        $this->assertEquals('Pegawai Asli Sebelum Gagal Validasi', $pegawai->nama_pegawai);
    }

    /**
     * 20. Buat akun via AJAX dari modal index berhasil dan akun berstatus aktif.
     */
    public function test_20_buat_akun_via_ajax_dari_index_berhasil(): void
    {
        $pegawai = Pegawai::create([
            'nip' => '199202022022021066',
            'nama_pegawai' => 'Pegawai Baru Buat Akun Index',
            'jabatan' => 'Staf Sekretariat',
            'id_skpd' => 1,
        ]);

        $rolePegawai = Role::where('nama_role', 'Pegawai')->firstOrFail();
        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $response = $this->postJson(route('user.akun.store', $pegawai), [
            'id_role' => $rolePegawai->id_role,
            'username' => 'user_baru_index',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $pegawai->refresh();
        $this->assertNotNull($pegawai->user);
        $this->assertEquals('user_baru_index', $pegawai->user->username);
        $this->assertEquals('aktif', $pegawai->user->status_user);
        $this->assertEquals($rolePegawai->id_role, $pegawai->user->id_role);

        // Log user.created tercatat
        $this->assertTrue(
            ActivityLog::where('subject_type', Pegawai::class)
                ->where('subject_id', $pegawai->id_pegawai)
                ->where('log_type', 'user.created')
                ->exists()
        );
    }

    /**
     * 21. Isolasi Scope Admin UPT P2BTP:
     * - Hanya melihat pegawai di UPT P2BTP (SKPD 6)
     * - Ditolak 403 saat mengakses pegawai UPT PSBP (SKPD 7) atau Sekretariat (SKPD 1)
     * - Ditolak saat mencoba mengubah data pegawai UPT lain via direct URL/AJAX
     */
    public function test_21_admin_upt_p2btp_terisolasi_dan_tidak_bisa_mengakses_upt_psbp_atau_dinas(): void
    {
        // SKPD 6 = UPT P2BTP, SKPD 7 = UPT PSBP, SKPD 1 = Sekretariat
        $adminUpt = $this->getAdminUpt(); // Berada di SKPD 6
        $this->assertEquals(6, $adminUpt->pegawai->id_skpd);

        // Buat pegawai di UPT PSBP (SKPD 7)
        $pegawaiPsbp = Pegawai::create([
            'nip' => '198505052010011077',
            'nama_pegawai' => 'Pegawai Asing UPT PSBP',
            'jabatan' => 'Staf PSBP',
            'id_skpd' => 7,
        ]);

        // Buat pegawai di UPT P2BTP sendiri (SKPD 6)
        $pegawaiP2btp = Pegawai::create([
            'nip' => '198505052010011066',
            'nama_pegawai' => 'Pegawai Internal UPT P2BTP',
            'jabatan' => 'Staf P2BTP',
            'id_skpd' => 6,
        ]);

        $this->actingAs($adminUpt);

        // 1. Index query hanya memuat pegawai di SKPD 6
        $responseIndex = $this->get(route('master-data.pegawai.index'));
        $responseIndex->assertStatus(200);
        $pegawaiList = $responseIndex->viewData('pegawaiList');
        foreach ($pegawaiList as $item) {
            $this->assertEquals(6, $item->id_skpd, "Admin UPT P2BTP melihat pegawai di luar SKPD 6: id_skpd={$item->id_skpd}");
        }

        // 2. Akses show ke pegawai UPT lain -> 403
        $this->get(route('master-data.pegawai.show', $pegawaiPsbp))->assertStatus(403);

        // 3. Akses edit ke pegawai UPT lain -> 403
        $this->get(route('master-data.pegawai.edit', $pegawaiPsbp))->assertStatus(403);
        $this->getJson(route('master-data.pegawai.edit', $pegawaiPsbp))->assertStatus(403);

        // 4. Update data pegawai UPT lain via PUT/AJAX -> 403
        $this->putJson(route('master-data.pegawai.update', $pegawaiPsbp), [
            'nip' => '198505052010011077',
            'nama_pegawai' => 'Coba Retas Ubah Nama',
            'id_skpd' => 6,
        ])->assertStatus(403);

        // 5. Buat akun untuk pegawai UPT lain -> 403
        $rolePegawai = Role::where('nama_role', 'Pegawai')->firstOrFail();
        $this->postJson(route('user.akun.store', $pegawaiPsbp), [
            'id_role' => $rolePegawai->id_role,
            'password' => 'password123',
        ])->assertStatus(403);

        // 6. Parameter tampering: Mengubah SKPD pegawai internal menjadi SKPD asing -> gagal validasi
        $this->put(route('master-data.pegawai.update', $pegawaiP2btp), [
            'nip' => '198505052010011066',
            'nama_pegawai' => 'Pegawai Internal UPT P2BTP',
            'id_skpd' => 7, // Coba dipindah ke UPT lain
        ])->assertSessionHasErrors('id_skpd');

        // 7. Akses ke pegawainya sendiri (SKPD 6) -> berhasil
        $this->get(route('master-data.pegawai.show', $pegawaiP2btp))->assertStatus(200);
    }

    /**
     * 22. Isolasi Scope Admin UPT PSBP (SKPD 7):
     * - Mengakses pegawai UPT P2BTP (SKPD 6) ditolak 403
     * - Mengelola pegawainya sendiri di UPT PSBP diizinkan
     */
    public function test_22_admin_upt_psbp_terisolasi_dan_tidak_bisa_mengakses_upt_p2btp(): void
    {
        $roleAdminUpt = Role::where('nama_role', 'Admin UPT')->firstOrFail();

        // Siapkan akun Admin UPT di UPT PSBP (SKPD 7)
        $pegawaiAdminPsbp = Pegawai::create([
            'nip' => '198808082012011007',
            'nama_pegawai' => 'Admin UPT PSBP Test',
            'jabatan' => 'Kepala Subbag TU PSBP',
            'id_skpd' => 7,
        ]);

        $adminPsbp = User::create([
            'id_pegawai' => $pegawaiAdminPsbp->id_pegawai,
            'username' => 'admin_psbp_test',
            'password' => bcrypt('password123'),
            'id_role' => $roleAdminUpt->id_role,
            'status_user' => 'aktif',
        ]);

        // Pegawai di UPT P2BTP (SKPD 6)
        $pegawaiP2btp = Pegawai::create([
            'nip' => '198909092013011006',
            'nama_pegawai' => 'Pegawai P2BTP Target Akses',
            'jabatan' => 'Staf P2BTP',
            'id_skpd' => 6,
        ]);

        // Pegawai di UPT PSBP (SKPD 7)
        $pegawaiPsbp = Pegawai::create([
            'nip' => '198909092013011007',
            'nama_pegawai' => 'Pegawai PSBP Internal',
            'jabatan' => 'Staf PSBP',
            'id_skpd' => 7,
        ]);

        $this->actingAs($adminPsbp);

        // 1. Cek detail pegawai P2BTP -> 403
        $this->get(route('master-data.pegawai.show', $pegawaiP2btp))->assertStatus(403);

        // 2. Cek edit pegawai P2BTP -> 403
        $this->get(route('master-data.pegawai.edit', $pegawaiP2btp))->assertStatus(403);

        // 3. Update pegawai P2BTP -> 403
        $this->putJson(route('master-data.pegawai.update', $pegawaiP2btp), [
            'nip' => '198909092013011006',
            'nama_pegawai' => 'Retas Update PSBP to P2BTP',
            'id_skpd' => 7,
        ])->assertStatus(403);

        // 4. Buat akun pegawai P2BTP -> 403
        $rolePegawai = Role::where('nama_role', 'Pegawai')->firstOrFail();
        $this->postJson(route('user.akun.store', $pegawaiP2btp), [
            'id_role' => $rolePegawai->id_role,
            'password' => 'password123',
        ])->assertStatus(403);

        // 5. Cek pegawai PSBP miliknya sendiri -> 200
        $this->get(route('master-data.pegawai.show', $pegawaiPsbp))->assertStatus(200);

        // 6. Update pegawai PSBP miliknya sendiri -> 200
        $ruangPsbp = Ruangan::where('id_lokasi', 3)->value('id_ruangan');
        $responseUpdate = $this->putJson(route('master-data.pegawai.update', $pegawaiPsbp), [
            'nip' => '198909092013011007',
            'nama_pegawai' => 'Pegawai PSBP Nama Diperbarui',
            'id_skpd' => 7,
            'id_ruangan' => $ruangPsbp,
        ]);
        $responseUpdate->assertStatus(200);
        $responseUpdate->assertJson(['success' => true]);
    }

    /**
     * Helper untuk membuat file Excel dummy data pegawai untuk testing.
     */
    private function buatExcelPegawai(array $rows, ?array $customHeaders = null): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Data Pegawai');

        $headers = $customHeaders ?? ['NIP', 'Nama Pegawai', 'Jabatan', 'Nama SKPD', 'Nama Ruangan'];
        $sheet->fromArray($headers, null, 'A1');

        $rowNum = 2;
        foreach ($rows as $row) {
            $colLetter = 'A';
            foreach ($row as $val) {
                $sheet->setCellValueExplicit($colLetter . $rowNum, (string) $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $colLetter++;
            }
            $rowNum++;
        }

        $writer = new Xlsx($spreadsheet);
        $tempPath = tempnam(sys_get_temp_dir(), 'peg_import_test_') . '.xlsx';
        $writer->save($tempPath);

        return new UploadedFile(
            $tempPath,
            'Template-Data-Pegawai.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    /**
     * 23. Modal Tambah dan Modal Edit menggunakan reusable partial yang sama dan ukuran max-w-xl.
     */
    public function test_23_modal_tambah_dan_edit_menggunakan_reusable_partial_dan_ukuran_max_w_xl(): void
    {
        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $response = $this->get(route('master-data.pegawai.index'));
        $response->assertStatus(200);

        // Kedua modal hadir di index
        $response->assertSee('id="modal-tambah-pegawai"', false);
        $response->assertSee('id="modal-edit-pegawai"', false);

        // Keduanya menggunakan ukuran ringkas max-w-xl (bukan max-w-2xl)
        $content = $response->getContent();
        $this->assertStringContainsString('max-w-xl', $content);

        // Tombol Tambah Pegawai pada header membuka modal
        $response->assertSee('id="btn-tambah-pegawai"', false);

        // Field pada form parsial konsisten
        $response->assertSee('tambah_nip', false);
        $response->assertSee('tambah_nama_pegawai', false);
        $response->assertSee('tambah_jabatan', false);
        $response->assertSee('tambah_id_skpd', false);
        $response->assertSee('tambah_lokasi', false);
        $response->assertSee('tambah_id_ruangan', false);

        $response->assertSee('edit_nip', false);
        $response->assertSee('edit_nama_pegawai', false);
        $response->assertSee('edit_jabatan', false);
        $response->assertSee('edit_id_skpd', false);
        $response->assertSee('edit_lokasi', false);
        $response->assertSee('edit_id_ruangan', false);

        // Tombol Batal dan Simpan hadir di kedua modal
        $response->assertSee('id="btn-submit-tambah-pegawai"', false);
        $response->assertSee('id="btn-submit-edit-pegawai"', false);
    }

    /**
     * 24. Tambah pegawai via AJAX (modal tambah) berhasil dan data tersimpan di DB.
     */
    public function test_24_tambah_pegawai_via_ajax_modal_berhasil(): void
    {
        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $ruangDinas = Ruangan::where('id_lokasi', 1)->value('id_ruangan');
        $response = $this->postJson(route('master-data.pegawai.store'), [
            'nip' => '199505052025011099',
            'nama_pegawai' => 'Pegawai Baru Dari Modal Tambah',
            'jabatan' => 'Analis Data',
            'id_skpd' => 1,
            'id_ruangan' => $ruangDinas,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('pegawai', [
            'nip' => '199505052025011099',
            'nama_pegawai' => 'Pegawai Baru Dari Modal Tambah',
            'jabatan' => 'Analis Data',
            'id_skpd' => 1,
        ]);
    }

    /**
     * 25. Tambah pegawai via AJAX jika validasi gagal mengembalikan 422 JSON.
     */
    public function test_25_tambah_pegawai_via_ajax_modal_validation_error_returns_422(): void
    {
        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $response = $this->postJson(route('master-data.pegawai.store'), [
            'nip' => '',
            'nama_pegawai' => '',
            'id_skpd' => 99999, // SKPD tidak ada
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['nip', 'nama_pegawai', 'id_skpd']);
    }

    /**
     * 26. Unduh template Excel pegawai berhasil dan memiliki header kolom baku.
     */
    public function test_26_download_template_excel_pegawai_berhasil_dan_header_sesuai(): void
    {
        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $response = $this->get(route('master-data.pegawai.template'));
        $response->assertStatus(200);
        $this->assertTrue(
            str_contains((string) $response->headers->get('content-disposition'), 'Template-Data-Pegawai.xlsx')
        );
    }

    /**
     * 27. Import Excel dengan seluruh field wajib dan opsional berhasil.
     */
    public function test_27_import_excel_berhasil_dengan_field_wajib_dan_opsional(): void
    {
        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $skpd = Skpd::where('id_skpd', 1)->firstOrFail();
        $ruangan = Ruangan::where('id_skpd', 1)->first() ?? Ruangan::whereNull('id_skpd')->firstOrFail();

        $rows = [
            ['199101012021011011', 'Pegawai Import Lengkap', 'Staf Kepegawaian', $skpd->nama_skpd, $ruangan->nama_ruangan],
            ['199101012021011012', 'Pegawai Import Tanpa Opsional', '', $skpd->nama_skpd, ''],
        ];

        $file = $this->buatExcelPegawai($rows);

        $response = $this->post(route('master-data.pegawai.import'), [
            'file' => $file,
            'mode' => 'skip',
        ]);

        $response->assertSessionHas('success');
        $response->assertSessionHas('import_summary');
        $summary = session('import_summary');
        $this->assertEquals(2, $summary['created']);
        $this->assertEquals(0, $summary['failed']);

        $this->assertDatabaseHas('pegawai', [
            'nip' => '199101012021011011',
            'nama_pegawai' => 'Pegawai Import Lengkap',
            'jabatan' => 'Staf Kepegawaian',
            'id_skpd' => $skpd->id_skpd,
            'id_ruangan' => $ruangan->id_ruangan,
        ]);

        $this->assertDatabaseHas('pegawai', [
            'nip' => '199101012021011012',
            'nama_pegawai' => 'Pegawai Import Tanpa Opsional',
            'jabatan' => null,
            'id_skpd' => $skpd->id_skpd,
            'id_ruangan' => null,
        ]);
    }

    /**
     * 28. Import Excel menolak baris dengan NIP kosong atau Nama kosong.
     */
    public function test_28_import_excel_tolak_nip_kosong_dan_nama_kosong(): void
    {
        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $skpd = Skpd::where('id_skpd', 1)->firstOrFail();

        $rows = [
            ['', 'Nama Tanpa NIP', 'Staf', $skpd->nama_skpd, ''],
            ['199202022022011013', '', 'Staf', $skpd->nama_skpd, ''],
        ];

        $file = $this->buatExcelPegawai($rows);

        $response = $this->post(route('master-data.pegawai.import'), [
            'file' => $file,
            'mode' => 'skip',
        ]);

        $response->assertSessionHas('error');
        $summary = session('import_summary');
        $this->assertEquals(0, $summary['created']);
        $this->assertEquals(2, $summary['failed']);

        $errors = implode(' ', $summary['errors']);
        $this->assertStringContainsString('NIP wajib diisi', $errors);
        $this->assertStringContainsString('Nama Pegawai wajib diisi', $errors);
    }

    /**
     * 29. Import Excel menolak SKPD yang tidak valid atau tidak terdaftar.
     */
    public function test_29_import_excel_tolak_skpd_tidak_valid(): void
    {
        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $rows = [
            ['199303032023011014', 'Pegawai SKPD Fiktif', 'Staf', 'SKPD Fiktif Antah Berantah', ''],
        ];

        $file = $this->buatExcelPegawai($rows);

        $response = $this->post(route('master-data.pegawai.import'), [
            'file' => $file,
            'mode' => 'skip',
        ]);

        $response->assertSessionHas('error');
        $summary = session('import_summary');
        $this->assertEquals(1, $summary['failed']);
        $this->assertStringContainsString('tidak ditemukan atau di luar wewenang Anda', $summary['errors'][0]);
    }

    /**
     * 30. Import Excel menolak ruangan yang tidak sesuai dengan SKPD / lokasinya.
     */
    public function test_30_import_excel_tolak_ruangan_tidak_sesuai_lokasi_skpd(): void
    {
        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        // SKPD 1 = Sekretariat (Kantor Dinas).
        // Buat ruangan khusus pada lokasi UPT PSBP (SKPD 7).
        $skpdPsbp = Skpd::where('id_skpd', 7)->firstOrFail();
        $ruanganAsing = Ruangan::create([
            'nama_ruangan' => 'Ruang Laboratorium Khusus PSBP',
            'id_skpd' => 7,
            'id_lokasi' => $skpdPsbp->id_lokasi,
        ]);

        $rows = [
            ['199404042024011015', 'Pegawai Ruang Salah', 'Staf', 'Sekretariat', $ruanganAsing->nama_ruangan],
        ];

        $file = $this->buatExcelPegawai($rows);

        $response = $this->post(route('master-data.pegawai.import'), [
            'file' => $file,
            'mode' => 'skip',
        ]);

        $response->assertSessionHas('error');
        $summary = session('import_summary');
        $this->assertEquals(1, $summary['failed']);
        $this->assertStringContainsString('Ruangan "Ruang Laboratorium Khusus PSBP" tidak ditemukan atau ambigu', $summary['errors'][0]);
    }

    /**
     * 31. Import Excel menangani duplikat sesuai mode (skip vs update).
     */
    public function test_31_import_excel_tangani_duplikat_sesuai_mode_skip_dan_update(): void
    {
        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $skpd = Skpd::where('id_skpd', 1)->firstOrFail();

        $pegawaiLama = Pegawai::create([
            'nip' => '199505052025011016',
            'nama_pegawai' => 'Nama Lama Pegawai Duplikat',
            'jabatan' => 'Jabatan Lama',
            'id_skpd' => 1,
        ]);

        $rows = [
            ['199505052025011016', 'Nama Terupdate Pegawai', 'Jabatan Terupdate', $skpd->nama_skpd, ''],
        ];

        // 1. Mode SKIP: Data lama tidak diubah
        $fileSkip = $this->buatExcelPegawai($rows);
        $this->post(route('master-data.pegawai.import'), [
            'file' => $fileSkip,
            'mode' => 'skip',
        ]);
        $pegawaiLama->refresh();
        $this->assertEquals('Nama Lama Pegawai Duplikat', $pegawaiLama->nama_pegawai);

        // 2. Mode UPDATE: Data lama diperbarui
        $fileUpdate = $this->buatExcelPegawai($rows);
        $this->post(route('master-data.pegawai.import'), [
            'file' => $fileUpdate,
            'mode' => 'update',
        ]);
        $pegawaiLama->refresh();
        $this->assertEquals('Nama Terupdate Pegawai', $pegawaiLama->nama_pegawai);
        $this->assertEquals('Jabatan Terupdate', $pegawaiLama->jabatan);
    }

    /**
     * 32. Import Excel mempertahankan format NIP sebagai string (angka nol di depan tidak hilang).
     */
    public function test_32_import_excel_pertahankan_format_nip_string_dan_angka_nol(): void
    {
        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $skpd = Skpd::where('id_skpd', 1)->firstOrFail();
        $nipDenganNol = '0199606062026011017'; // Dimulai dengan angka 0

        $rows = [
            [$nipDenganNol, 'Pegawai NIP Nol Depan', 'Staf IT', $skpd->nama_skpd, ''],
        ];

        $file = $this->buatExcelPegawai($rows);

        $response = $this->post(route('master-data.pegawai.import'), [
            'file' => $file,
            'mode' => 'skip',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('pegawai', [
            'nip' => $nipDenganNol,
            'nama_pegawai' => 'Pegawai NIP Nol Depan',
        ]);

        $peg = Pegawai::where('nip', $nipDenganNol)->firstOrFail();
        $this->assertSame($nipDenganNol, $peg->nip);
        $this->assertTrue(str_starts_with($peg->nip, '0'));
    }
}
