<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\HolidayKind;

/**
 * Validated data to create or edit a holiday.
 */
final readonly class HolidayData
{
    /**
     * @param  string|null  $until  Last day of a closure period (only at creation): every working day
     *                              between `$date` and `$until` becomes a closure day
     */
    public function __construct(
        public string $date,
        public string $name,
        public HolidayKind $kind = HolidayKind::Holiday,
        public ?string $until = null,
    ) {}
}
