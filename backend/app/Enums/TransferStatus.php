<?php

namespace App\Enums;

enum TransferStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Handover = 'handover';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Status pada saat transfer masih memegang aset.
     *
     * @return list<string>
     */
    public static function active(): array
    {
        return [
            self::Pending->value,
            self::Approved->value,
            self::Handover->value,
        ];
    }

    /**
     * Status final: transfer sudah selesai dan aset kembali bebas.
     *
     * @return list<string>
     */
    public static function terminal(): array
    {
        return [
            self::Rejected->value,
            self::Completed->value,
            self::Cancelled->value,
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Transisi status yang diizinkan. Kunci adalah status saat ini,
     * nilainya daftar status tujuan yang valid.
     *
     * @return array<string, list<string>>
     */
    public static function transitions(): array
    {
        return [
            self::Pending->value => [
                self::Approved->value,
                self::Rejected->value,
                self::Cancelled->value,
            ],
            self::Approved->value => [
                self::Handover->value,
                self::Cancelled->value,
            ],
            self::Handover->value => [
                self::Completed->value,
            ],
            self::Rejected->value => [],
            self::Completed->value => [],
            self::Cancelled->value => [],
        ];
    }

    /**
     * Apakah status saat ini boleh berpindah ke status tujuan tertentu.
     */
    public function canTransitionTo(self $target): bool
    {
        $allowed = self::transitions()[$this->value] ?? [];

        return in_array($target->value, $allowed, true);
    }
}
