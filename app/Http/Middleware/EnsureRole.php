<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Allow only authenticated users whose role is in the allowed list.
     *
     * Usage: ->middleware('role:Admin Aset,Admin Bidang')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403, 'Unauthorized.');
        }

        if (! $user->isAktif()) {
            abort(403, 'Akun Anda telah dinonaktifkan. Silakan hubungi administrator.');
        }

        $roleName = $user->role?->nama_role;

        // Trim kedua sisi: spasi ganda di data role tidak boleh membuat
        // user sah kehilangan akses (atau sebaliknya lolos salah).
        $cocok = is_string($roleName)
            && in_array(trim($roleName), array_map('trim', $roles), true);

        if (! $cocok) {
            abort(403, 'Anda tidak memiliki hak akses untuk menu ini.');
        }

        return $next($request);
    }
}
