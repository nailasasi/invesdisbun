<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginCheckTest extends TestCase
{
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

    public function test_login_admin_aset_dengan_nip(): void
    {
        $user = User::where('username', '111111111111111111')->first();
        $this->assertNotNull($user, 'User Admin Aset harus ada');

        $response = $this->post('/login', [
            'username' => '111111111111111111',
            'password' => 'admin123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_hash_valid(): void
    {
        $user = User::where('username', '111111111111111111')->first();
        $this->assertTrue(Hash::check('admin123', $user->password), 'Hash admin123 harus valid');
    }
}
