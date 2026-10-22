<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Support\Str;

/**
 * Pembuatan token acak untuk QR Code aset.
 *
 * Token memakai `random_bytes` sehingga tidak dapat ditebak dan tidak
 * membocorkan informasi sensitif (hanya identifier acak).
 */
class QrTokenService
{
    public const TOKEN_LENGTH = 40;

    /**
     * Menghasilkan token QR yang dijamin belum dipakai aset lain.
     */
    public function generate(): string
    {
        do {
            $token = Str::random(self::TOKEN_LENGTH);
        } while (Asset::withTrashed()->where('qr_token', $token)->exists());

        return $token;
    }
}
