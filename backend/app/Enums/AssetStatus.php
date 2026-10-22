<?php

namespace App\Enums;

enum AssetStatus: string
{
    case Available = 'available';
    case InTransfer = 'in_transfer';
    case Maintenance = 'maintenance';
    case Missing = 'missing';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Status yang boleh dipilih langsung oleh pengguna saat membuat atau
     * memperbarui aset. `in_transfer` dikecualikan karena hanya boleh diset
     * oleh workflow transfer.
     *
     * @return list<string>
     */
    public static function assignable(): array
    {
        return [
            self::Available->value,
            self::Maintenance->value,
            self::Missing->value,
        ];
    }
}
