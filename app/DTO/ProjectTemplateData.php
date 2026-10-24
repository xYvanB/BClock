<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Validated data to create or edit a project template.
 */
final readonly class ProjectTemplateData
{
    /**
     * @param  list<TemplateActivityData>  $activities
     */
    public function __construct(
        public string $name,
        public array $activities,
    ) {}
}
