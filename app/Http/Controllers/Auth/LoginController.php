<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $logger
    ) {
    }

    /**
     * Show the application's login form.
     */
    public function showLoginForm(Request $request): View
    {
        $remainingSeconds = 0;

        // 1. Periksa sisa waktu dari session rate_limit_until
        $until = session('rate_limit_until');
        if ($until && $until > time()) {
            $remainingSeconds = max($remainingSeconds, (int) ($until - time()));
        }

        // 2. Periksa sisa waktu berdasarkan IP klien
        $ipThrottleKey = 'login-ip|'.$request->ip();
        if (RateLimiter::tooManyAttempts($ipThrottleKey, 5)) {
            $remainingSeconds = max($remainingSeconds, RateLimiter::availableIn($ipThrottleKey));
        }

        // 3. Periksa sisa waktu berdasarkan username yang tersimpan
        $username = trim((string) old('username', session('rate_limit_username', '')));
        if ($username !== '') {
            $throttleKey = Str::transliterate(Str::lower($username).'|'.$request->ip());
            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                $remainingSeconds = max($remainingSeconds, RateLimiter::availableIn($throttleKey));
            }
        }

        // 4. Periksa flash rate_limit_seconds
        $flashSeconds = (int) session('rate_limit_seconds', 0);
        if ($flashSeconds > 0) {
            $remainingSeconds = max($remainingSeconds, $flashSeconds);
        }

        // Jika waktu rate limit sudah habis, bersihkan penanda session
        if ($remainingSeconds <= 0) {
            session()->forget(['rate_limit_until', 'rate_limit_username', 'rate_limit_seconds']);
            $remainingSeconds = 0;
        }

        return view('auth.login', [
            'rateLimitSeconds' => $remainingSeconds,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $username = trim($credentials['username']);
        $password = (string) $credentials['password'];
        $remember = $request->boolean('remember');

        // Pastikan batas Remember Me tepat 30 hari (30 * 24 * 60 = 43.200 menit)
        if (Auth::guard('web') instanceof SessionGuard) {
            Auth::guard('web')->setRememberDuration(43200);
        }

        // Kunci rate limit berbasis kombinasi username (dinormalisasi) + IP dan IP murni
        $throttleKey = Str::transliterate(Str::lower($username).'|'.$request->ip());
        $ipThrottleKey = 'login-ip|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5) || RateLimiter::tooManyAttempts($ipThrottleKey, 5)) {
            $seconds = max(
                RateLimiter::availableIn($throttleKey),
                RateLimiter::availableIn($ipThrottleKey)
            );
            if ($seconds < 1) {
                $seconds = 60;
            }

            session()->put('rate_limit_until', now()->addSeconds($seconds)->timestamp);
            session()->put('rate_limit_username', $username);
            session()->flash('rate_limit_seconds', $seconds);

            $this->logActivitySilently(
                'auth.failed',
                null,
                [],
                ['username' => $username, 'keterangan' => 'Terlalu banyak percobaan login. Akun dikunci sementara.'],
                'Login Gagal',
                null,
                $username
            );

            throw ValidationException::withMessages([
                'username' => 'Terlalu banyak percobaan login. Silakan tunggu 1 menit sebelum mencoba kembali.',
            ]);
        }

        // Evaluasi entitas user untuk audit internal
        $user = User::with('role', 'pegawai')->where('username', $username)->first();

        $loginSuccess = Auth::attempt([
            'username' => $username,
            'password' => $password,
            'status_user' => 'aktif',
        ], $remember);

        if (! $loginSuccess) {
            RateLimiter::hit($throttleKey, 60);
            RateLimiter::hit($ipThrottleKey, 60);

            $reason = 'Password salah';
            if (! $user) {
                $reason = 'Akun tidak ditemukan';
            } elseif ($user->status_user !== 'aktif') {
                $reason = 'Akun tidak aktif';
            }

            $this->logActivitySilently(
                'auth.failed',
                $user,
                [],
                ['username' => $username, 'keterangan' => $reason],
                'Login Gagal',
                null,
                $username
            );

            // Jika akun nonaktif dan password cocok, tampilkan pesan informatif akun nonaktif
            if ($user && $user->status_user !== 'aktif' && Hash::check($password, $user->password)) {
                throw ValidationException::withMessages([
                    'username' => 'Akun Anda telah dinonaktifkan. Silakan hubungi administrator.',
                ]);
            }

            // Jika setelah kegagalan ini batas 5 kali gagal tercapai:
            if (RateLimiter::tooManyAttempts($throttleKey, 5) || RateLimiter::tooManyAttempts($ipThrottleKey, 5)) {
                $seconds = max(
                    RateLimiter::availableIn($throttleKey),
                    RateLimiter::availableIn($ipThrottleKey)
                );
                if ($seconds < 1) {
                    $seconds = 60;
                }

                session()->put('rate_limit_until', now()->addSeconds($seconds)->timestamp);
                session()->put('rate_limit_username', $username);
                session()->flash('rate_limit_seconds', $seconds);
            }

            throw ValidationException::withMessages([
                'username' => 'Username atau Password yang Anda masukkan salah.',
            ]);
        }

        // Berhasil login: bersihkan pembatasan rate limit dan session penanda
        RateLimiter::clear($throttleKey);
        RateLimiter::clear($ipThrottleKey);
        session()->forget(['rate_limit_until', 'rate_limit_username', 'rate_limit_seconds']);

        $request->session()->regenerate();

        $authenticatedUser = Auth::user();

        if ($authenticatedUser instanceof User) {
            $this->logActivitySilently(
                'auth.login',
                $authenticatedUser,
                [],
                [
                    'username' => $authenticatedUser->username,
                    'role' => $authenticatedUser->role?->nama_role,
                    'remember' => $remember,
                ],
                'Login Berhasil'
            );
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request): RedirectResponse
    {
        $user = Auth::user();

        if ($user instanceof User) {
            $this->logActivitySilently(
                'auth.logout',
                $user,
                [],
                [
                    'username' => $user->username,
                    'role' => $user->role?->nama_role,
                ],
                'Logout'
            );
        }

        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Jalankan pencatatan log dengan try-catch agar kegagalan audit
     * tidak pernah menggagalkan proses bisnis autentikasi.
     */
    private function logActivitySilently(
        string $logType,
        ?User $subject = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $description = null,
        ?User $actor = null,
        ?string $actorName = null
    ): void {
        try {
            $this->logger->log(
                $logType,
                $subject,
                $oldValues,
                $newValues,
                $description,
                $actor,
                $actorName
            );
        } catch (\Throwable $e) {
            Log::error('Gagal mencatat log autentikasi: '.$e->getMessage(), [
                'log_type' => $logType,
                'username' => $actorName ?? $subject?->username,
            ]);
        }
    }
}
