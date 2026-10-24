<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\WorkStatus;

/**
 * Validated data to create or edit a project (dates in Y-m-d format).
 */
final readonly class ProjectData
{
    /**
     * @param  list<int>  $memberIds
     * @param  list<int>  $contactIds
     * @param  array<int, list<int>>  $templateAssignees  assignees by template activity id
     */
    public function __construct(
        public int $clientId,
        public string $name,
        public ?string $description,
        public string $startsOn,
        public string $dueOn,
        public float $plannedHours,
        public WorkStatus $status,
        public array $memberIds,
        public array $contactIds,
        public ?int $templateId = null,
        public array $templateAssignees = [],
    ) {}
}
