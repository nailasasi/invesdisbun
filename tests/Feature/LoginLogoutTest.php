<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Pegawai;
use App\Models\Role;
use App\Models\Skpd;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginLogoutTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => $this->namaDatabasePengujian(),
        ]);
        RateLimiter::clear('login-ip|127.0.0.1');
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        RateLimiter::clear('login-ip|127.0.0.1');
        parent::tearDown();
    }

    private function buatUserTest(string $roleName = 'Admin Aset', string $status = 'aktif'): User
    {
        $role = Role::where('nama_role', $roleName)->first();
        if (! $role) {
            $role = Role::create(['nama_role' => $roleName]);
        }

        $jenisSkpd = 'UPT';
        if ($roleName === 'Admin Bidang') {
            $jenisSkpd = 'Bidang';
        }

        $skpd = Skpd::where('jenis_skpd', $jenisSkpd)->first();
        if (! $skpd) {
            $skpd = Skpd::create([
                'nama_skpd' => 'SKPD Test ' . $jenisSkpd . ' ' . uniqid(),
                'jenis_skpd' => $jenisSkpd,
            ]);
        }

        $pegawai = Pegawai::create([
            'nama_pegawai' => 'Test Pegawai ' . $roleName . ' ' . uniqid(),
            'nip' => '999' . rand(100000, 999999) . rand(10, 99),
            'id_skpd' => $skpd->id_skpd,
            'jabatan' => 'Staff Test',
        ]);

        return User::create([
            'username' => 'test_' . strtolower(str_replace(' ', '_', $roleName)) . '_' . uniqid(),
            'password' => 'secret123',
            'status_user' => $status,
            'id_role' => $role->id_role,
            'id_pegawai' => $pegawai->id_pegawai,
        ]);
    }

    /**
     * 1. Login Berhasil:
     * - Username dan password benar
     * - Sesi terbentuk
     * - Redirect ke dashboard
     * - Log auth.login tercatat di activity_logs
     */
    public function test_login_berhasil_mencatat_aktivitas_dan_redirect_ke_dashboard(): void
    {
        $user = $this->buatUserTest('Admin Aset');

        $response = $this->post(route('login.attempt'), [
            'username' => $user->username,
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        // Periksa activity_logs
        $log = ActivityLog::where('log_type', 'auth.login')
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id_user)
            ->latest('id_activity_log')
            ->first();

        $this->assertNotNull($log, 'Log auth.login harus tercatat di activity_logs');
        $this->assertEquals('Login Berhasil', $log->description);
        $this->assertEquals($user->id_user, $log->id_user);
        $this->assertEquals($user->pegawai->nama_pegawai, $log->nama_aktor);
        $this->assertNotNull($log->ip_address);
        $this->assertArrayNotHasKey('password', $log->new_values ?? []);
    }

    /**
     * 2. Login Gagal — Password Salah:
     * - Kredensial ditolak
     * - Tetap di halaman login dengan pesan kesalahan
     * - Sesi tidak terbentuk
     * - Log auth.failed tercatat
     * - Password tidak tersimpan di log
     */
    public function test_login_gagal_password_salah_mencatat_aktivitas_tanpa_bocorkan_password(): void
    {
        $user = $this->buatUserTest('Admin Aset');
        $plainWrongPassword = 'wrongpassword123';

        $response = $this->from(route('login'))->post(route('login.attempt'), [
            'username' => $user->username,
            'password' => $plainWrongPassword,
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['username' => 'Username atau Password yang Anda masukkan salah.']);
        $this->assertGuest();

        $log = ActivityLog::where('log_type', 'auth.failed')
            ->where('nama_aktor', $user->username)
            ->latest('id_activity_log')
            ->first();

        $this->assertNotNull($log, 'Log auth.failed harus tercatat saat password salah');
        $this->assertEquals('Login Gagal', $log->description);
        $this->assertEquals('Password salah', $log->new_values['keterangan'] ?? null);
        $this->assertEquals($user->username, $log->new_values['username'] ?? null);

        // Pastikan password tidak pernah tersimpan di log
        $rawLog = json_encode($log->toArray());
        $this->assertStringNotContainsString($plainWrongPassword, $rawLog);
    }

    /**
     * 3. Login Gagal — Akun Tidak Ditemukan:
     * - Username acak tidak ada di database
     * - Pesan kesalahan umum
     * - Log auth.failed tercatat
     */
    public function test_login_gagal_akun_tidak_ditemukan_mencatat_aktivitas(): void
    {
        $dummyUsername = 'pengguna_fiktif_' . uniqid();

        $response = $this->from(route('login'))->post(route('login.attempt'), [
            'username' => $dummyUsername,
            'password' => 'apapun123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['username' => 'Username atau Password yang Anda masukkan salah.']);
        $this->assertGuest();

        $log = ActivityLog::where('log_type', 'auth.failed')
            ->where('nama_aktor', $dummyUsername)
            ->latest('id_activity_log')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('Akun tidak ditemukan', $log->new_values['keterangan'] ?? null);
    }

    /**
     * 4. Login Gagal — Akun Nonaktif:
     * - Status user nonaktif
     * - Login ditolak dengan pesan informatif
     * - Log auth.failed tercatat
     */
    public function test_login_gagal_akun_nonaktif_pesan_informatif_dan_mencatat_aktivitas(): void
    {
        $user = $this->buatUserTest('Admin Aset', 'nonaktif');

        $response = $this->from(route('login'))->post(route('login.attempt'), [
            'username' => $user->username,
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['username' => 'Akun Anda telah dinonaktifkan. Silakan hubungi administrator.']);
        $this->assertGuest();

        $log = ActivityLog::where('log_type', 'auth.failed')
            ->where('nama_aktor', $user->username)
            ->latest('id_activity_log')
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals('Akun tidak aktif', $log->new_values['keterangan'] ?? null);
    }

    /**
     * 5. Remember Me — Aktif:
     * - Remember me dicentang
     * - Durasi remember duration guard adalah 43.200 menit (30 hari)
     * - Cookie remember terbentuk di response
     */
    public function test_remember_me_aktif_menghasilkan_cookie_30_hari(): void
    {
        $user = $this->buatUserTest('Admin Aset');

        // Pastikan guard remember duration diset ke 43.200 menit via reflection
        $reflection = new \ReflectionProperty(Auth::guard('web'), 'rememberDuration');
        $this->assertEquals(43200, $reflection->getValue(Auth::guard('web')));

        $response = $this->post(route('login.attempt'), [
            'username' => $user->username,
            'password' => 'secret123',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $recallerName = Auth::guard('web')->getRecallerName();
        $response->assertCookie($recallerName);

        // Periksa expiry cookie remember me (sekitar 30 hari = 2.592.000 detik)
        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === $recallerName);

        $this->assertNotNull($cookie);
        $durationSeconds = $cookie->getExpiresTime() - time();
        // Toleransi perbedaan beberapa detik
        $this->assertGreaterThan(2591000, $durationSeconds);
        $this->assertLessThanOrEqual(2592000, $durationSeconds);
    }

    /**
     * 6. Remember Me — Tidak Aktif:
     * - Login tanpa mencentang remember
     * - Cookie remember tidak dikirimkan
     */
    public function test_remember_me_tidak_aktif_tanpa_cookie_remember(): void
    {
        $user = $this->buatUserTest('Admin Aset');

        $response = $this->post(route('login.attempt'), [
            'username' => $user->username,
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $recallerName = Auth::guard('web')->getRecallerName();
        $cookie = collect($response->headers->getCookies())
            ->first(fn ($c) => $c->getName() === $recallerName);

        $this->assertNull($cookie, 'Cookie remember me tidak boleh dikirimkan bila remember tidak dicentang');
    }

    /**
     * 7. Rate Limiting:
     * - 5 kali percobaan gagal berturut-turut
     * - Percobaan ke-6 ditolak dengan kunci 1 menit
     * - Tidak menampilkan error 429 standar
     * - Old input username dipertahankan
     */
    public function test_rate_limiting_5_kali_gagal_terkunci_1_menit_tanpa_error_429(): void
    {
        $username = 'brute_target_' . uniqid();

        for ($i = 1; $i <= 5; $i++) {
            $resp = $this->from(route('login'))->post(route('login.attempt'), [
                'username' => $username,
                'password' => 'salah' . $i,
            ]);
            $resp->assertRedirect(route('login'));
            $resp->assertSessionHasErrors(['username' => 'Username atau Password yang Anda masukkan salah.']);
        }

        // Percobaan ke-6: harus dikunci dengan pesan ramah pengguna dan HTTP 302 (bukan 429)
        $resp6 = $this->from(route('login'))->post(route('login.attempt'), [
            'username' => $username,
            'password' => 'salah6',
        ]);

        $this->assertNotEquals(429, $resp6->getStatusCode());
        $resp6->assertRedirect(route('login'));
        $resp6->assertSessionHasErrors([
            'username' => 'Terlalu banyak percobaan login. Silakan tunggu 1 menit sebelum mencoba kembali.',
        ]);
        $resp6->assertSessionHas('_old_input.username', $username);

        // Periksa activity_logs mencatat percobaan terkunci
        $lockLog = ActivityLog::where('log_type', 'auth.failed')
            ->where('nama_aktor', $username)
            ->latest('id_activity_log')
            ->first();

        $this->assertNotNull($lockLog);
        $this->assertStringContainsString('Terlalu banyak percobaan login', $lockLog->new_values['keterangan'] ?? '');
    }

    /**
     * 7b. Perbaikan UI Rate Limit Login:
     * - Setelah 5 kali gagal, tombol Masuk dinonaktifkan
     * - Tampil countdown "Coba lagi dalam X detik"
     * - Nilai countdown dan status tombol disabled dirender di HTML
     */
    public function test_rate_limit_ui_menonaktifkan_tombol_dan_menampilkan_countdown_saat_terkunci(): void
    {
        $username = 'brute_ui_' . uniqid();

        for ($i = 1; $i <= 5; $i++) {
            $this->from(route('login'))->post(route('login.attempt'), [
                'username' => $username,
                'password' => 'salah' . $i,
            ]);
        }

        // Kunjungi halaman login
        $page = $this->get(route('login'));
        $page->assertOk();

        // Tombol login memiliki atribut disabled
        $page->assertSee('id="btn-login-submit"', false);
        $page->assertSee('disabled', false);

        // Menampilkan teks countdown
        $page->assertSee('Coba lagi dalam', false);
        $page->assertSee('detik', false);
        $page->assertSee('rate-limit-countdown-box', false);
    }

    /**
     * 7c. Refresh Halaman Mempertahankan Sisa Waktu Rate Limit Backend:
     */
    public function test_rate_limit_ui_mempertahankan_sisa_waktu_saat_halaman_di_refresh(): void
    {
        $username = 'brute_refresh_' . uniqid();

        for ($i = 1; $i <= 5; $i++) {
            $this->from(route('login'))->post(route('login.attempt'), [
                'username' => $username,
                'password' => 'salah' . $i,
            ]);
        }

        // Refresh halaman login pertama kali
        $page1 = $this->get(route('login'));
        $page1->assertOk();
        $page1->assertSee('Coba lagi dalam');

        // Simulasi refresh halaman kedua kali (tetap terkunci mengikuti sisa waktu backend)
        $page2 = $this->get(route('login'));
        $page2->assertOk();
        $page2->assertSee('Coba lagi dalam');
        $page2->assertSee('disabled', false);
    }

    /**
     * 8. Logout:
     * - User terautentikasi melakukan logout
     * - Sesi dihancurkan, pengguna menjadi guest
     * - Log auth.logout tercatat dengan identitas pengguna
     */
    public function test_logout_menghancurkan_sesi_dan_mencatat_aktivitas_dengan_identitas(): void
    {
        $user = $this->buatUserTest('Admin Aset');

        $this->actingAs($user);
        $this->assertAuthenticatedAs($user);

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();

        $log = ActivityLog::where('log_type', 'auth.logout')
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id_user)
            ->latest('id_activity_log')
            ->first();

        $this->assertNotNull($log, 'Log auth.logout harus tercatat');
        $this->assertEquals('Logout', $log->description);
        $this->assertEquals($user->id_user, $log->id_user);
        $this->assertEquals($user->pegawai->nama_pegawai, $log->nama_aktor);
    }

    /**
     * 9. Perlindungan Cache Browser (Anti-Cache Headers):
     * - Halaman protected (memerlukan auth) harus mengirimkan header anti-cache:
     *   Cache-Control: no-store, no-cache, must-revalidate, max-age=0
     *   Pragma: no-cache
     *   Expires: 0
     */
    public function test_perlindungan_cache_browser_anti_cache_headers_pada_halaman_auth(): void
    {
        $user = $this->buatUserTest('Admin Aset');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $response->assertHeader('Pragma', 'no-cache');
        $response->assertHeader('Expires', '0');
    }

    /**
     * 10. Halaman login publik tidak terganggu anti-cache middleware:
     */
    public function test_halaman_login_publik_dapat_diakses_bebas(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Selamat Datang');
        $response->assertSee('ⓘ Berlaku 30 hari');
    }

    /**
     * 11. Penonaktifan Akun Saat Sesi Berjalan:
     * - Sesi langsung diputus pada request berikutnya
     * - Pengguna diarahkan ke login dengan notifikasi akun dinonaktifkan
     */
    public function test_penonaktifan_akun_saat_sesi_berjalan_langsung_memutus_akses(): void
    {
        $user = $this->buatUserTest('Admin Aset');

        $this->actingAs($user);
        $this->assertAuthenticatedAs($user);

        // Akun dinonaktifkan oleh administrator
        $user->update(['status_user' => 'nonaktif']);

        // Request berikutnya ke route berotentikasi
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['username' => 'Akun Anda telah dinonaktifkan. Silakan hubungi administrator.']);
        $this->assertGuest();
    }

    /**
     * 12. Hak Akses Empat Role:
     * - Admin Aset
     * - Admin Bidang
     * - Admin UPT
     * - Pegawai
     */
    public function test_akses_dashboard_dan_pembagian_hak_akses_empat_role(): void
    {
        $roles = [
            'Admin Aset',
            'Admin Bidang',
            'Admin UPT',
            'Pegawai',
        ];

        foreach ($roles as $roleName) {
            $user = $this->buatUserTest($roleName);

            $response = $this->actingAs($user)->get(route('dashboard'));
            $response->assertOk();
            $this->assertAuthenticatedAs($user);

            // Verifikasi header anti-cache pada setiap role
            $cacheControl = (string) $response->headers->get('Cache-Control');
            $this->assertStringContainsString('no-store', $cacheControl);
            $this->assertStringContainsString('no-cache', $cacheControl);
            $this->assertStringContainsString('must-revalidate', $cacheControl);
            $this->assertStringContainsString('max-age=0', $cacheControl);
            $response->assertHeader('Pragma', 'no-cache');
            $response->assertHeader('Expires', '0');
        }
    }
}
