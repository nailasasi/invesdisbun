<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\PreventBackHistory;
use App\Services\ActivityLogger;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            EnsureUserIsActive::class,
        ]);

        $middleware->alias([
            'auth' => Authenticate::class,
            'guest' => RedirectIfAuthenticated::class,
            'role' => EnsureRole::class,
            'prevent-back-history' => PreventBackHistory::class,
        ]);

        // `kode_inventaris` sengaja dikecualikan dari trim otomatis. Tanpa ini
        // input spasi-saja ("   ") diubah menjadi "" lalu null sehingga tampak
        // seperti field kosong dan memicu kode otomatis. Dengan dikecualikan,
        // nilai asli sampai ke controller dan bisa ditolak sebagai kode invalid.
        $middleware->trimStrings(except: ['kode_inventaris']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Tangani pembatasan rate limit saat login agar tidak menampilkan HTTP 429 bawaan
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('login') || $request->routeIs('login.attempt')) {
                $username = trim((string) $request->input('username', ''));

                $seconds = 60;
                if (method_exists($e, 'getHeaders')) {
                    $headers = $e->getHeaders();
                    $seconds = (int) ($headers['Retry-After'] ?? 60);
                }
                if ($seconds < 1) {
                    $seconds = 60;
                }

                session()->put('rate_limit_until', now()->addSeconds($seconds)->timestamp);
                session()->put('rate_limit_username', $username);
                session()->flash('rate_limit_seconds', $seconds);

                try {
                    app(ActivityLogger::class)->log(
                        'auth.failed',
                        null,
                        [],
                        ['username' => $username, 'keterangan' => 'Terlalu banyak percobaan login. Silakan tunggu 1 menit sebelum mencoba kembali.'],
                        'Login Gagal',
                        null,
                        $username ?: null
                    );
                } catch (\Throwable) {
                    // Abaikan jika logger gagal agar tidak mengganggu alur respon
                }

                return redirect()->route('login')
                    ->withInput($request->except('password'))
                    ->withErrors(['username' => 'Terlalu banyak percobaan login. Silakan tunggu 1 menit sebelum mencoba kembali.']);
            }
        });

        // Password otorisasi (konfirmasi ulang password login sebelum aksi
        // sensitif) dan foto bukti (data URL berukuran besar) TIDAK PERNAH
        // di-flash ke session saat validasi gagal, agar tidak tersimpan
        // sebagai teks biasa di session/old input.
        $exceptions->dontFlash('password_otorisasi', 'foto_bukti_otentikasi');

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
