<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Dati validati per creare o modificare un template di progetto.
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
