<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginCheckTest extends TestCase
{
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
