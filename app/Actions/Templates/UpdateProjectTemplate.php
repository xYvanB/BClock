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
     * Updates the name and replaces the template's activities in a transaction.
     * Projects already created do not change: they have their own activities.
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
