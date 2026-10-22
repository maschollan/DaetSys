<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\IdempotentReplay;
use App\Models\IdempotencyKey;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Menangani request yang dapat dikirim ulang (retry) agar tidak menghasilkan
 * efek ganda.
 *
 * Alur:
 *  1. Client mengirim header `Idempotency-Key`.
 *  2. Key + fingerprint payload dicatat sebelum request dieksekusi.
 *  3. Bila request yang sama diulang, respons pertama dikembalikan apa adanya.
 *  4. Bila key yang sama dipakai dengan payload berbeda, request ditolak 409.
 */
class IdempotencyService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function fingerprint(string $scope, ?User $user, array $payload): string
    {
        ksort($payload);

        return hash('sha256', $scope.'|'.($user?->id ?? 'guest').'|'.json_encode($payload));
    }

    /**
     * Mencoba memesan key untuk request yang sedang berjalan.
     *
     * @param  array<string, mixed>  $payload
     */
    public function reserve(string $key, string $scope, ?User $user, array $payload): void
    {
        $fingerprint = $this->fingerprint($scope, $user, $payload);

        $existing = IdempotencyKey::query()->where('key', $key)->first();

        if ($existing !== null) {
            if (! hash_equals($existing->request_fingerprint, $fingerprint)) {
                throw BusinessRuleException::conflict(
                    'Idempotency key has already been used with a different payload.'
                );
            }

            if ($existing->response_body !== null) {
                throw new IdempotentReplay(
                    (int) $existing->response_status,
                    (string) $existing->response_body,
                );
            }

            throw BusinessRuleException::conflict(
                'A request with this idempotency key is still being processed.'
            );
        }

        try {
            IdempotencyKey::create([
                'key' => $key,
                'scope' => $scope,
                'request_fingerprint' => $fingerprint,
                'user_id' => $user?->id,
                'expires_at' => now()->addMinutes((int) config('daetsys.idempotency_ttl', 1440)),
            ]);
        } catch (QueryException $exception) {
            // Unique index bentrok: request paralel dengan key sama.
            throw BusinessRuleException::conflict(
                'A request with this idempotency key is already in progress.'
            );
        }
    }

    /**
     * Menyimpan respons sehingga percobaan berikutnya dapat di-replay.
     */
    public function storeResponse(string $key, JsonResponse $response): void
    {
        IdempotencyKey::query()
            ->where('key', $key)
            ->update([
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Melepas reservasi key ketika request gagal, agar client boleh mencoba lagi.
     */
    public function release(string $key): void
    {
        IdempotencyKey::query()
            ->where('key', $key)
            ->whereNull('response_body')
            ->delete();
    }

    public function scopeFor(Request $request): string
    {
        return $request->route()?->getName() ?? $request->method().' '.$request->path();
    }
}
