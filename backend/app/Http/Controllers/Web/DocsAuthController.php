<?php

namespace App\Http\Controllers\Web;

use App\Enums\ActivityAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Sesi masuk halaman dokumentasi API — berbasis session Laravel
 * (`session('docs_admin_id')`), bukan cookie custom.
 *
 * Alur: admin membuka `/docs/login` → POST /docs/login dengan
 * email + password → backend memvalidasi lewat AuthService::attempt
 * (jejak audit ikut tercatat), menyimpan id admin ke session, dan
 * mengembalikan `redirect` yang dipakai halaman login menuju `/docs`.
 */
class DocsAuthController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly AuditService $audit,
    ) {}

    /**
     * POST /docs/login
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

        // Sesi docs memakai session, bukan bearer token — cabut token
        // yang baru saja terbit agar tidak menggantung.
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

        $request->session()->put('docs_admin_id', $user->id);

        return response()->json([
            'message' => 'Login berhasil. Mengalihkan ke dokumentasi...',
            'redirect' => '/docs',
        ]);
    }

    /**
     * POST /docs/logout
     */
    public function logout(Request $request): JsonResponse
    {
        $request->session()->forget('docs_admin_id');

        return response()->json([
            'message' => 'Sesi dokumentasi diakhiri.',
            'redirect' => '/',
        ]);
    }
}
