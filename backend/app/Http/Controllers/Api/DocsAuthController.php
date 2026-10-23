<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Sesi pengguna halaman dokumentasi API (cookie `daetsys_docs`).
 *
 * Alur: admin membuka `/docs/login` → POST /api/docs/login dengan
 * email + password → backend memvalidasi lewat AuthService::attempt
 * (audit trail ikut tercatat), lalu menyetel cookie terenkripsi yang
 * bepergian otomatis bersama navigasi browser ke `/docs`.
 *
 * Cookie hanya dipakai untuk gerbang `EnsureDocsAccess` — token
 * bearer personal access token yang terbit sementara akan langsung
 * dicabut agar tidak meninggalkan kredensial menggantung.
 */
class DocsAuthController extends Controller
{
    /** Nama cookie akses dokumentasi. */
    public const COOKIE_NAME = 'daetsys_docs';

    /** Masa berlaku cookie dalam menit. */
    public const COOKIE_MINUTES = 120;

    public function __construct(
        private readonly AuthService $auth,
        private readonly AuditService $audit,
    ) {}

    /**
     * POST /api/docs/login
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $result = $this->auth->attempt($validated['email'], $validated['password'], $request);

        /** @var User $user */
        $user = $result['user'];

        // Cookie docs tidak membutuhkan personal access token — cabut
        // token yang baru saja terbit agar tidak menggantung.
        $tokenId = (int) Str::before($result['token'], '|');
        $user->tokens()->where('id', $tokenId)->delete();

        if (! $user->hasRole(UserRole::Admin)) {
            $this->audit->log(
                ActivityAction::LoginFailed,
                user: $user,
                newValues: ['reason' => 'docs_access_forbidden'],
                request: $request,
            );

            return response()->json([
                'message' => 'Hanya admin yang dapat membuka dokumentasi API.',
            ], 403);
        }

        $payload = json_encode([
            'sub' => $user->id,
            'exp' => now()->addMinutes(self::COOKIE_MINUTES)->timestamp,
        ]);

        return response()->json([
            'message' => 'Login berhasil. Mengalihkan ke dokumentasi...',
        ])->cookie(Cookie::make(
            name: self::COOKIE_NAME,
            value: $payload,
            minutes: self::COOKIE_MINUTES,
        ));
    }

    /**
     * POST /api/docs/logout
     */
    public function logout(): JsonResponse
    {
        return response()->json([
            'message' => 'Sesi dokumentasi diakhiri.',
        ])->withCookie(Cookie::forget(self::COOKIE_NAME, '/'));
    }
}
