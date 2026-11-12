<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureUserIsActive;

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
        ]);

        // `kode_inventaris` sengaja dikecualikan dari trim otomatis. Tanpa ini
        // input spasi-saja ("   ") diubah menjadi "" lalu null sehingga tampak
        // seperti field kosong dan memicu kode otomatis. Dengan dikecualikan,
        // nilai asli sampai ke controller dan bisa ditolak sebagai kode invalid.
        $middleware->trimStrings(except: ['kode_inventaris']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Password otorisasi (konfirmasi ulang password login sebelum aksi
        // sensitif) dan foto bukti (data URL berukuran besar) TIDAK PERNAH
        // di-flash ke session saat validasi gagal, agar tidak tersimpan
        // sebagai teks biasa di session/old input.
        $exceptions->dontFlash('password_otorisasi', 'foto_bukti_otentikasi');

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
