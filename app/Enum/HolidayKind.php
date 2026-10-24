<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Perché un giorno è non lavorativo, oltre al weekend.
 */
enum HolidayKind: string
{
    /** @var string Festività (Natale, Ognissanti…) */
    case Holiday = 'festivo';

    /** @var string Chiusura extra dell'azienda, non legata a una festività */
    case Closure = 'chiusura';

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
