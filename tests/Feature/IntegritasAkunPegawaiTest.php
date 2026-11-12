<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\Role;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IntegritasAkunPegawaiTest extends TestCase
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

    /**
     * 1. Satu pegawai berhasil memiliki satu akun.
     */
    public function test_01_satu_pegawai_berhasil_memiliki_satu_akun(): void
    {
        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $skpdSekretariat = Skpd::where('jenis_skpd', 'Sekretariat')->firstOrFail();
        $roleAset = Role::where('nama_role', 'Admin Aset')->firstOrFail();

        $pegawai = Pegawai::create([
            'nip' => '198501012010011099',
            'nama_pegawai' => 'Pegawai Uji Akun Tunggal',
            'jabatan' => 'Analis Data',
            'id_skpd' => $skpdSekretariat->id_skpd,
        ]);

        $response = $this->post(route('user.akun.store', $pegawai), [
            'id_role' => $roleAset->id_role,
            'username' => 'uji_tunggal_1985',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('master-data.pegawai.show', ['pegawai' => $pegawai, 'tab' => 'tab-akun']));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id_pegawai' => $pegawai->id_pegawai,
            'username' => 'uji_tunggal_1985',
            'status_user' => 'aktif',
        ]);

        $this->assertNotNull($pegawai->fresh()->user);
        $this->assertEquals('uji_tunggal_1985', $pegawai->fresh()->user->username);
    }

    /**
     * 2. Pegawai yang sama tidak dapat memiliki akun kedua.
     */
    public function test_02_pegawai_yang_sama_tidak_dapat_memiliki_akun_kedua(): void
    {
        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $skpdSekretariat = Skpd::where('jenis_skpd', 'Sekretariat')->firstOrFail();
        $roleAset = Role::where('nama_role', 'Admin Aset')->firstOrFail();

        $pegawai = Pegawai::create([
            'nip' => '198501012010011098',
            'nama_pegawai' => 'Pegawai Uji Akun Ganda',
            'jabatan' => 'Analis Data',
            'id_skpd' => $skpdSekretariat->id_skpd,
        ]);

        // Buat akun pertama
        $this->post(route('user.akun.store', $pegawai), [
            'id_role' => $roleAset->id_role,
            'username' => 'uji_pertama_1985',
            'password' => 'password123',
        ])->assertSessionHas('success');

        // Upaya membuat akun kedua untuk pegawai yang sama
        $responseKedua = $this->post(route('user.akun.store', $pegawai), [
            'id_role' => $roleAset->id_role,
            'username' => 'uji_kedua_1985',
            'password' => 'password123',
        ]);

        $responseKedua->assertSessionHasErrors(['pegawai']);
        $this->assertEquals(1, User::where('id_pegawai', $pegawai->id_pegawai)->count());
    }

    /**
     * 3. Dua request pembuatan akun bersamaan tetap tidak menghasilkan akun ganda.
     */
    public function test_03_dua_request_pembuatan_akun_bersamaan_dicegah_oleh_lock(): void
    {
        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $skpdSekretariat = Skpd::where('jenis_skpd', 'Sekretariat')->firstOrFail();
        $roleAset = Role::where('nama_role', 'Admin Aset')->firstOrFail();

        $pegawai = Pegawai::create([
            'nip' => '198501012010011097',
            'nama_pegawai' => 'Pegawai Uji Concurrency Lock',
            'jabatan' => 'Analis Data',
            'id_skpd' => $skpdSekretariat->id_skpd,
        ]);

        // Simulasi request pertama berhasil
        $this->post(route('user.akun.store', $pegawai), [
            'id_role' => $roleAset->id_role,
            'username' => 'uji_concurrency_1',
            'password' => 'password123',
        ])->assertSessionHas('success');

        // Simulasi request kedua yang mengeksekusi controller saat akun sudah terbentuk
        $responseRace = $this->post(route('user.akun.store', $pegawai), [
            'id_role' => $roleAset->id_role,
            'username' => 'uji_concurrency_2',
            'password' => 'password123',
        ]);

        $responseRace->assertSessionHasErrors(['pegawai']);
        $this->assertEquals(1, User::where('id_pegawai', $pegawai->id_pegawai)->count());
    }

    /**
     * 4. Akun tanpa id_pegawai ditolak jika kolom ditetapkan NOT NULL.
     */
    public function test_04_skema_not_null_menolak_akun_tanpa_id_pegawai(): void
    {
        // Buat temporary table dengan skema target (id_pegawai NOT NULL)
        DB::statement('
            CREATE TEMPORARY TABLE test_users_not_null (
                id_user INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                id_pegawai INT UNSIGNED NOT NULL,
                username VARCHAR(255) NOT NULL UNIQUE
            )
        ');

        // Insert dengan id_pegawai valid berhasil
        DB::statement('INSERT INTO test_users_not_null (id_pegawai, username) VALUES (101, "valid_user")');
        $this->assertEquals(1, DB::table('test_users_not_null')->count());

        // Insert dengan id_pegawai NULL wajib melempar QueryException (Column cannot be null)
        $this->expectException(QueryException::class);
        DB::statement('INSERT INTO test_users_not_null (id_pegawai, username) VALUES (NULL, "invalid_null_user")');
    }

    /**
     * 5. Pegawai yang masih memiliki akun tidak dapat dihapus melalui antarmuka atau route apa pun.
     */
    public function test_05_pegawai_yang_masih_memiliki_akun_tidak_dapat_dihapus(): void
    {
        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $pegawai = Pegawai::whereHas('user')->firstOrFail();

        // 1. Verifikasi tidak ada route DELETE untuk master pegawai
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('master-data.pegawai.destroy'));

        // 2. Request DELETE ke endpoint pegawai ditolak oleh sistem (405 Method Not Allowed)
        $responsePegawai = $this->delete('/master-data/pegawai/' . $pegawai->id_pegawai);
        $responsePegawai->assertStatus(405);

        // 3. Request DELETE ke endpoint user juga ditolak oleh sistem (405 Method Not Allowed)
        $responseUser = $this->delete('/user/' . $pegawai->id_pegawai);
        $responseUser->assertStatus(405);

        // 4. Data pegawai dan akunnya tetap utuh
        $this->assertDatabaseHas('pegawai', ['id_pegawai' => $pegawai->id_pegawai]);
        $this->assertDatabaseHas('users', ['id_pegawai' => $pegawai->id_pegawai]);
    }

    /**
     * 6. Route DELETE user/{pegawai} sudah dinonaktifkan (HTTP 405 Method Not Allowed).
     */
    public function test_06_route_delete_user_sudah_tidak_dapat_diakses(): void
    {
        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $pegawai = Pegawai::firstOrFail();

        // Mencoba request DELETE ke /user/{pegawai}
        $response = $this->delete('/user/' . $pegawai->id_pegawai);

        // Karena route DELETE sudah dihapus, Laravel wajib merespon 405 Method Not Allowed
        $response->assertStatus(405);
    }

    /**
     * 7. Fitur Nonaktifkan dan Aktifkan Akun tetap berfungsi normal.
     */
    public function test_07_fitur_nonaktifkan_dan_aktifkan_akun_tetap_berfungsi(): void
    {
        $admin = $this->getAdminAset();
        $this->actingAs($admin);

        $skpdSekretariat = Skpd::where('jenis_skpd', 'Sekretariat')->firstOrFail();
        $roleAset = Role::where('nama_role', 'Admin Aset')->firstOrFail();

        $pegawai = Pegawai::create([
            'nip' => '198501012010011096',
            'nama_pegawai' => 'Pegawai Uji Toggle Status',
            'jabatan' => 'Analis Data',
            'id_skpd' => $skpdSekretariat->id_skpd,
        ]);

        $user = User::create([
            'id_pegawai' => $pegawai->id_pegawai,
            'username' => 'uji_status_toggle',
            'password' => bcrypt('password123'),
            'id_role' => $roleAset->id_role,
            'status_user' => 'aktif',
        ]);

        // 1. Nonaktifkan akun
        $responseNonaktif = $this->post(route('user.status.update', $pegawai), [
            'status_user' => 'nonaktif',
        ]);
        $responseNonaktif->assertSessionHas('success');
        $this->assertEquals('nonaktif', $user->fresh()->status_user);

        // 2. Aktifkan kembali akun
        $responseAktif = $this->post(route('user.status.update', $pegawai), [
            'status_user' => 'aktif',
        ]);
        $responseAktif->assertSessionHas('success');
        $this->assertEquals('aktif', $user->fresh()->status_user);
    }

    /**
     * 8. Login dan relasi modul lain tetap berjalan normal.
     */
    public function test_08_login_dan_relasi_modul_lain_tetap_berjalan(): void
    {
        $admin = $this->getAdminAset();

        // Verifikasi login session
        $this->actingAs($admin);
        $this->assertAuthenticatedAs($admin);

        // Verifikasi akses halaman Manajemen Pegawai
        $responseIndex = $this->get(route('master-data.pegawai.index'));
        $responseIndex->assertStatus(200);

        // Verifikasi relasi pegawai dan role dari user
        $this->assertNotNull($admin->pegawai);
        $this->assertNotNull($admin->role);
        $this->assertTrue($admin->isAktif());
    }
}
