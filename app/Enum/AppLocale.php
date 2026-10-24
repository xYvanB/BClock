<?php

declare(strict_types=1);

namespace App\Enum;

enum AppLocale: string
{
    case It = 'it';
    case En = 'en';

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
