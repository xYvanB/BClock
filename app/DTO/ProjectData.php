<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\WorkStatus;

/**
 * Dati validati per creare o modificare un progetto (date nel formato Y-m-d).
 */
final readonly class ProjectData
{
    /**
     * @param  list<int>  $memberIds
     * @param  list<int>  $contactIds
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
    ) {}
}
