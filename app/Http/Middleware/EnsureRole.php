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

        $roleName = $user->role?->nama_role;

        if (! in_array($roleName, $roles, true)) {
            abort(403, 'Anda tidak memiliki hak akses untuk menu ini.');
        }

        return $next($request);
    }
}
