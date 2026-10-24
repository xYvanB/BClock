<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Actions\Allocations\GenerateActivityAllocations;
use App\DTO\ProjectData;
use App\Enum\WorkStatus;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\TemplateActivity;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

final class CreateProject
{
    public function __construct(private readonly GenerateActivityAllocations $allocations) {}

    /**
     * Crea un progetto con membri e referenti in transazione; se è dato un template crea anche
     * le sue attività (date dai giorni lavorativi relativi, assegnatari scelti nell'anteprima).
     *
     * @throws Throwable
     */
    public function handle(ProjectData $data): Project
    {
        return DB::transaction(function () use ($data): Project {
            $project = Project::create([
                'client_id' => $data->clientId,
                'project_template_id' => $data->templateId,
                'name' => $data->name,
                'description' => $data->description,
                'starts_on' => $data->startsOn,
                'due_on' => $data->dueOn,
                'planned_hours' => $data->plannedHours,
                'status' => $data->status,
            ]);

            $project->members()->sync($data->memberIds);
            $project->contacts()->sync($data->contactIds);

            if ($data->templateId !== null) {
                $this->createActivitiesFromTemplate($project, ProjectTemplate::findOrFail($data->templateId), $data->templateAssignees);
            }

            return $project;
        });
    }

    /**
     * @param  array<int, list<int>>  $assigneesByTemplateActivity
     */
    private function createActivitiesFromTemplate(Project $project, ProjectTemplate $template, array $assigneesByTemplateActivity): void
    {
        $start = CarbonImmutable::parse($project->starts_on);

        foreach ($template->activities as $templateActivity) {
            $activity = $project->activities()->create([
                'name' => $templateActivity->name,
                'description' => $templateActivity->description,
                'starts_on' => TemplateActivity::dateOfWorkingDay($start, $templateActivity->start_day),
                'due_on' => TemplateActivity::dateOfWorkingDay($start, $templateActivity->end_day),
                'planned_hours' => $templateActivity->planned_hours,
                'status' => WorkStatus::Todo,
            ]);

            $activity->assignees()->sync($assigneesByTemplateActivity[$templateActivity->id] ?? []);
            $this->allocations->handle($activity);
        }
    }
}
