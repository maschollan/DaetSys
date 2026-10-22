<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Pelanggaran aturan bisnis yang tidak boleh dianggap sebagai error input.
 *
 * Berbeda dengan `ValidationException`, exception ini murni-soal aturan
 * domain (mis. aset sedang ditransfer, status transfer tidak sesuai) dan
 * selalu dipetakan ke kode HTTP eksplisit.
 */
class BusinessRuleException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        string $message,
        private readonly int $status = 422,
        private readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function conflict(string $message, array $errors = [], ?Throwable $previous = null): self
    {
        return new self($message, 409, $errors, $previous);
    }

    public static function forbidden(string $message = 'This action is unauthorized.'): self
    {
        return new self($message, 403);
    }

    public static function unprocessable(string $message, array $errors = []): self
    {
        return new self($message, 422, $errors);
    }
}
