<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Aplikasi ini API-only dan tidak memiliki route bernama "login".
        // Callback bawaan Laravel `fn () => route('login')` akan melempar
        // RouteNotFoundException saat browser mengakses endpoint terlindungi
        // tanpa header Accept: application/json. Kembalikan null agar
        // Authenticate melempar AuthenticationException yang ditangani
        // handler di bawah.
        $middleware->redirectGuestsTo(fn () => null);

        $middleware->alias([
            'active.user' => EnsureUserIsActive::class,
            'role' => EnsureRole::class,
            'idempotent' => EnsureIdempotency::class,
        ]);

        // Setiap request API terlindungi memverifikasi bahwa akun masih aktif
        // dan mencabut token yang sudah tidak berlaku.
        $middleware->appendToGroup('api', EnsureUserIsActive::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Format error API yang konsisten untuk seluruh endpoint.
        $exceptions->render(function (Throwable $e, Request $request) {
            // Autentikasi gagal selalu dijawab 401 JSON, termasuk akses
            // browser (Accept bukan application/json), karena tidak ada
            // halaman login server-rendered pada aplikasi API-only.
            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'Unauthenticated.',
                ], 401);
            }

            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof BusinessRuleException) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], $e->status());
            }

            if ($e instanceof AuthorizationException) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'This action is unauthorized.',
                ], 403);
            }

            if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
                return response()->json([
                    'message' => 'Resource not found.',
                ], 404);
            }

            if ($e instanceof HttpExceptionInterface) {
                return response()->json([
                    'message' => $e->getMessage() ?: 'Request failed.',
                ], $e->getStatusCode());
            }

            return null;
        });
    })->create();
