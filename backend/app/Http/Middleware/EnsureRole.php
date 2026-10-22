<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi akses ke sekumpulan route berdasarkan role pengguna.
 *
 * Dipakai untuk gerbang route-level (mis. manajemen pengguna hanya untuk
 * admin). Otorisasi tingkat objek tetap ditangani oleh Policy.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        $userRole = $user?->role?->name;

        if ($userRole === null || ! in_array($userRole, $roles, true)) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        return $next($request);
    }
}
