<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     *
     * Memastikan user yang terautentikasi memiliki status 'aktif'.
     * Bila akun dinonaktifkan saat sedang login, sesi langsung dihentikan
     * pada request berikutnya dan pengguna diarahkan kembali ke login.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isAktif()) {
            try {
                app(\App\Services\ActivityLogger::class)->log(
                    'auth.logout',
                    $user,
                    [],
                    [
                        'username' => $user->username,
                        'keterangan' => 'Sesi diputus otomatis karena akun dinonaktifkan',
                    ],
                    'Logout Otomatis (Akun Nonaktif)'
                );
            } catch (\Throwable) {
                // Abaikan jika logger gagal agar penghentian sesi tetap berjalan tuntas
            }

            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Akun Anda telah dinonaktifkan. Silakan hubungi administrator.',
                ], 403);
            }

            return redirect()->route('login')
                ->withErrors(['username' => 'Akun Anda telah dinonaktifkan. Silakan hubungi administrator.']);
        }

        return $next($request);
    }
}

