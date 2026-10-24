<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Why a day is non-working, besides the weekend.
 */
enum HolidayKind: string
{
    /** @var string Public holiday (Christmas, All Saints…) */
    case Holiday = 'festivo';

    /** @var string Extra company closure, not tied to a holiday */
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
