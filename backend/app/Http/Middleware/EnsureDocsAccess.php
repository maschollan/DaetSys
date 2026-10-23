<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang akses dokumentasi API (/docs, /docs/api.json).
 *
 * Dokumentasi memuat detail internal endpoint, sehingga pada production
 * hanya dapat dibuka oleh admin aktif melalui token Bearer Sanctum yang
 * valid (mis. via curl/Postman), atau seluruh publik bila
 * `SCRAMBLE_DOCS_PUBLIC=true` (opt-in eksplisit untuk demo).
 *
 * Permintaan dari IP lokal (127.0.0.1, ::1) diizinkan hanya di
 * environment `local` agar pengembangan tetap nyaman.
 */
class EnsureDocsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('scramble.public_docs') === true) {
            return $next($request);
        }

        if (in_array($request->ip(), ['127.0.0.1', '::1'], true) && App::environment('local')) {
            return $next($request);
        }

        /** @var User|null $user */
        $user = auth('sanctum')->user();

        if ($user?->hasRole(UserRole::Admin) && $user->is_active) {
            return $next($request);
        }

        return response()->json([
            'message' => 'Dokumentasi API hanya dapat diakses oleh admin melalui token Bearer yang valid.',
        ], 403);
    }
}
