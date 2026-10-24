<?php

declare(strict_types=1);

namespace App\Actions\Templates;

use App\DTO\ProjectTemplateData;
use App\Models\ProjectTemplate;
use Illuminate\Support\Facades\DB;
use Throwable;

final class UpdateProjectTemplate
{
    /**
     * Aggiorna il nome e sostituisce le attività del template in transazione.
     * I progetti già creati non cambiano: hanno le loro attività.
     *
     * @throws Throwable
     */
    public function handle(ProjectTemplate $template, ProjectTemplateData $data): ProjectTemplate
    {
        return DB::transaction(function () use ($template, $data): ProjectTemplate {
            $template->update(['name' => $data->name]);
            $template->replaceActivities($data->activities);

            return $template;
        });
    }
}
