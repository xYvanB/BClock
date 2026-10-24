<?php

declare(strict_types=1);

namespace App\Enum;

enum TimeEntrySource: string
{
    /** @var string Inserita a mano */
    case Manual = 'manuale';

    /** @var string Generata dallo stop di un timer */
    case Timer = 'timer';

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
