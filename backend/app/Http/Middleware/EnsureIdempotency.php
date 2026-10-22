<?php

namespace App\Http\Middleware;

use App\Exceptions\IdempotentReplay;
use App\Services\IdempotencyService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menegakkan idempotency pada request yang dapat dikirim ulang.
 *
 * Header `Idempotency-Key` bersifat opsional. Bila tidak dikirim, request
 * diproses seperti biasa.
 */
class EnsureIdempotency
{
    public function __construct(private readonly IdempotencyService $idempotency) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (! is_string($key) || trim($key) === '') {
            return $next($request);
        }

        $key = trim($key);
        $scope = $this->idempotency->scopeFor($request);
        $payload = $request->except(['password', 'password_confirmation']);

        try {
            $this->idempotency->reserve($key, $scope, $request->user(), $payload);
        } catch (IdempotentReplay $replay) {
            return response($replay->body(), $replay->status())
                ->header('Content-Type', 'application/json')
                ->header('Idempotent-Replayed', 'true');
        }

        try {
            $response = $next($request);
        } catch (\Throwable $exception) {
            $this->idempotency->release($key);

            throw $exception;
        }

        if ($response instanceof JsonResponse && $response->getStatusCode() < 400) {
            $this->idempotency->storeResponse($key, $response);
        } else {
            $this->idempotency->release($key);
        }

        return $response;
    }
}
