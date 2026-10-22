<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjamin akun yang sedang aktif tetap bisa dipakai, dan mencabut token
 * milik akun yang sudah dinonaktifkan.
 *
 * Menempel pada grup `api`, sehingga setiap endpoint terlindungi akan
 * melewati pemeriksaan ini.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->is_active) {
            $user->tokens()->delete();

            return response()->json([
                'message' => 'Your account has been deactivated.',
            ], 403);
        }

        return $next($request);
    }
}
