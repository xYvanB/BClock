<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\WorkStatus;

/**
 * Validated data to create or edit an activity (dates in Y-m-d format).
 */
final readonly class ActivityData
{
    /**
     * @param  list<int>  $assigneeIds
     * @param  list<int>|null  $dependencyIds  Activities it depends on (end → start); null leaves the dependencies as they were
     */
    public function __construct(
        public int $projectId,
        public string $name,
        public ?string $description,
        public string $startsOn,
        public string $dueOn,
        public float $plannedHours,
        public WorkStatus $status,
        public array $assigneeIds,
        public ?array $dependencyIds = null,
    ) {}
}
