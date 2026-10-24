<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Tipo di assenza di una persona.
 */
enum AbsenceType: string
{
    case Vacation = 'ferie';
    case Leave = 'permesso';

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
