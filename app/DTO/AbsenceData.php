<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\AbsenceType;

/**
 * Validated data to create or edit an absence. Times only for leaves.
 */
final readonly class AbsenceData
{
    public function __construct(
        public AbsenceType $type,
        public string $startsOn,
        public string $endsOn,
        public ?string $startsTime,
        public ?string $endsTime,
        public ?string $note,
    ) {}
}
