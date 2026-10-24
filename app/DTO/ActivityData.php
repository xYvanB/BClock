<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\WorkStatus;

/**
 * Dati validati per creare o modificare un'attività (date nel formato Y-m-d).
 */
final readonly class ActivityData
{
    /**
     * @param  list<int>  $assigneeIds
     * @param  list<int>|null  $dependencyIds  Attività da cui dipende (fine → inizio); null lascia le dipendenze com'erano
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
