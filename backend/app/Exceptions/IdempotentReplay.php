<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Internal exception: menandakan bahwa request saat ini adalah pengulangan
 * dari request yang sama, sehingga respons lama harus dikembalikan.
 */
class IdempotentReplay extends RuntimeException
{
    public function __construct(
        private readonly int $status,
        private readonly string $body,
    ) {
        parent::__construct('Idempotent replay.');
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }
}
