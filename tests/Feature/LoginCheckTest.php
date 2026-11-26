<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginCheckTest extends TestCase
{
    protected string $testNip;

    protected function setUp(): void
    {
        parent::setUp();

        // Driver sqlite tidak tersedia di environment ini; test memakai
        // MySQL nyata (data asli, tanpa writes permanen).
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabase(),
        ]);

        DB::beginTransaction();

        $this->testNip = '9' . str_pad((string) random_int(10000000, 99999999), 8, '0') . substr(uniqid(), -8);
        $roleAdminAset = Role::firstOrCreate(['nama_role' => 'Admin Aset']);
        $pegawai = Pegawai::firstOrCreate(
            ['nip' => $this->testNip],
            ['nama_pegawai' => 'Admin Aset Uji', 'jabatan' => 'Admin Aset']
        );
        User::firstOrCreate(
            ['username' => $this->testNip],
            [
                'id_pegawai' => $pegawai->id_pegawai,
                'id_role' => $roleAdminAset->id_role,
                'password' => 'admin123',
                'status_user' => 'aktif',
            ]
        );
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        if (!empty($this->testNip)) {
            User::where('username', $this->testNip)->forceDelete();
            Pegawai::where('nip', $this->testNip)->forceDelete();
        }
        parent::tearDown();
    }

    private function namaDatabase(): string
    {
        return $this->namaDatabasePengujian();
    }

    public function test_login_admin_aset_dengan_nip(): void
    {
        $user = User::where('username', $this->testNip)->first();
        $this->assertNotNull($user, 'User Admin Aset harus ada');

        $response = $this->post('/login', [
            'username' => $this->testNip,
            'password' => 'admin123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_hash_valid(): void
    {
        $user = User::where('username', $this->testNip)->first();
        $this->assertTrue(Hash::check('admin123', $user->password), 'Hash admin123 harus valid');
    }
}
