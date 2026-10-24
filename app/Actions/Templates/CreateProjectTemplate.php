<?php

declare(strict_types=1);

namespace App\Actions\Templates;

use App\DTO\ProjectTemplateData;
use App\Models\ProjectTemplate;
use Illuminate\Support\Facades\DB;
use Throwable;

final class CreateProjectTemplate
{
    /**
     * Crea un template con le sue attività in transazione.
     *
     * @throws Throwable
     */
    public function handle(ProjectTemplateData $data): ProjectTemplate
    {
        return DB::transaction(function () use ($data): ProjectTemplate {
            $template = ProjectTemplate::create(['name' => $data->name]);

            $template->replaceActivities($data->activities);

            return $template;
        });
    }
}
