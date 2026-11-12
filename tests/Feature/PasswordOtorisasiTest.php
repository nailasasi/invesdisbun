<?php

namespace Tests\Feature;

use App\Http\Controllers\TanahController;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\Validator;
use ReflectionClassConstant;
use Tests\TestCase;

/**
 * Tahap A — Password Otorisasi (step-up re-auth) pada modul Tanah &
 * Pemanfaatan/Retribusi.
 *
 * Pengujian di kelas ini SENGAJA tidak menyentuh database:
 *  - mekanisme rule `current_password:web` diuji langsung, dan
 *  - endpoint `tanah.store` diuji pada jalur PENOLAKAN (validasi gagal
 *    sebelum menyentuh DB).
 *
 * Jalur SUKSES (yang butuh tabel users/role/tanah) diuji terpisah pada
 * kelas yang di-skip bila driver SQLite tidak tersedia.
 */
class PasswordOtorisasiTest extends TestCase
{
    private const PASSWORD_BENAR = 'rahasia123';

    private const RULES = ['password_otorisasi' => ['required', 'current_password:web']];

    private function userTerotentikasi(string $namaRole = 'Admin Aset'): User
    {
        $user = new User([
            'username' => 'admin',
            'password' => self::PASSWORD_BENAR,
            'status_user' => 'aktif',
        ]);

        // Relasi di-set manual agar tidak memicu query database.
        $user->setRelation('role', new Role(['nama_role' => $namaRole]));

        return $user;
    }

    // ------------------------------------------------------------------
    // ATURAN: password kosong / salah / benar
    // ------------------------------------------------------------------

    public function test_password_kosong_ditolak(): void
    {
        $this->actingAs($this->userTerotentikasi());

        $validator = Validator::make(['password_otorisasi' => null], self::RULES);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password_otorisasi', $validator->errors()->toArray());
    }

    public function test_password_salah_ditolak(): void
    {
        $this->actingAs($this->userTerotentikasi());

        $validator = Validator::make(['password_otorisasi' => 'password-salah'], self::RULES);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password_otorisasi', $validator->errors()->toArray());
    }

    public function test_password_benar_diterima(): void
    {
        $this->actingAs($this->userTerotentikasi());

        $validator = Validator::make(['password_otorisasi' => self::PASSWORD_BENAR], self::RULES);

        $this->assertFalse($validator->fails());
    }

    // ------------------------------------------------------------------
    // ENDPOINT: verifikasi dilakukan di server (bukan hanya di form)
    // ------------------------------------------------------------------

    public function test_endpoint_store_tanpa_password_ditolak(): void
    {
        $this->actingAs($this->userTerotentikasi());

        $response = $this->from(route('tanah.create'))->post(route('tanah.store'), [
            'kib' => '01.01.01.01.001',
        ]);

        $response->assertRedirect(route('tanah.create'));
        $response->assertSessionHasErrors('password_otorisasi');
    }

    /**
     * Request langsung ke endpoint (bypass form) tetap wajib lolos verifikasi
     * password server-side. Password salah -> ditolak.
     */
    public function test_endpoint_langsung_dengan_password_salah_ditolak(): void
    {
        $this->actingAs($this->userTerotentikasi());

        $response = $this->from(route('tanah.create'))->post(route('tanah.store'), [
            'kib' => '01.01.01.01.002',
            'password_otorisasi' => 'password-salah',
        ]);

        $response->assertRedirect(route('tanah.create'));
        $response->assertSessionHasErrors('password_otorisasi');
    }

    /**
     * Password kosong juga tidak boleh dilewatkan langsung ke endpoint.
     */
    public function test_endpoint_langsung_dengan_password_kosong_ditolak(): void
    {
        $this->actingAs($this->userTerotentikasi());

        $response = $this->from(route('tanah.create'))->post(route('tanah.store'), [
            'kib' => '01.01.01.01.003',
            'password_otorisasi' => '',
        ]);

        $response->assertRedirect(route('tanah.create'));
        $response->assertSessionHasErrors('password_otorisasi');
    }

    // ------------------------------------------------------------------
    // OTORISASI ROLE TETAP BERLAKU (password tidak menggantikan izin)
    // ------------------------------------------------------------------

    public function test_role_tidak_berwenang_tetap_ditolak_meski_password_benar(): void
    {
        $this->actingAs($this->userTerotentikasi('Pegawai'));

        $response = $this->from(route('tanah.create'))->post(route('tanah.store'), [
            'kib' => '01.01.01.01.004',
            'password_otorisasi' => self::PASSWORD_BENAR,
        ]);

        $response->assertForbidden();
    }

    // ------------------------------------------------------------------
    // PASSWORD TIDAK DISIMPAN / DI-FLASH
    // ------------------------------------------------------------------

    public function test_password_tidak_di_flash_ke_session_input_lama(): void
    {
        $this->actingAs($this->userTerotentikasi());

        $this->from(route('tanah.create'))->post(route('tanah.store'), [
            'kib' => '01.01.01.01.005',
            'password_otorisasi' => 'password-salah',
        ]);

        // Field non-sensitif tetap dipulihkan...
        $this->assertSame('01.01.01.01.005', session('_old_input.kib'));
        // ...tetapi password tidak pernah ada di input lama / session.
        $this->assertNull(session('_old_input.password_otorisasi'));
    }

    public function test_activity_logger_menyaring_password_otorisasi(): void
    {
        $hasil = app(ActivityLogger::class)->sanitize([
            'kib' => '01.01.01',
            'password_otorisasi' => 'rahasia123',
        ]);

        $this->assertArrayNotHasKey('password_otorisasi', $hasil);
        $this->assertArrayHasKey('kib', $hasil);
    }

    // ------------------------------------------------------------------
    // REGRESI: Import 19 kolom tidak berubah
    // ------------------------------------------------------------------

    public function test_template_import_tanah_tetap_19_kolom(): void
    {
        $ref = new ReflectionClassConstant(TanahController::class, 'KOLOM_TEMPLATE');

        $this->assertCount(19, $ref->getValue());
    }
}
