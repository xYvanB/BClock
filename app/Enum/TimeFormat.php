<?php

declare(strict_types=1);

namespace App\Enum;

enum TimeFormat: string
{
    case Hours24 = '24h';
    case Hours12 = '12h';

    /**
     * Get all enum values as an array.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
