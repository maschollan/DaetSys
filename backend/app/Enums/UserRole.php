<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Officer = 'officer';
    case Auditor = 'auditor';

    /**
     * Role yang diizinkan mengubah data operasional (aset, lokasi, transfer).
     *
     * @return list<string>
     */
    public static function operational(): array
    {
        return [self::Admin->value, self::Officer->value];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
