<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\ActivityData;
use App\Enum\Role;
use App\Enum\WorkStatus;
use App\Models\Activity;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveActivityRequest extends FormRequest
{
    /**
     * Autorizza prima della validazione (ActivityPolicy), così chi non può riceve 403 e non errori di campo.
     */
    public function authorize(): bool
    {
        $activity = $this->route('activity');

        return $activity instanceof Activity
            ? $this->user()->can('update', $activity)
            : $this->user()->can('create', Activity::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $assignees = Rule::exists('users', 'id');

        // Il Capo area assegna solo persone della propria area.
        if ($this->user()->hasRole(Role::AreaHead)) {
            $assignees->where('area_id', $this->user()->area_id);
        }

        return [
            'project_id' => $this->route('activity') instanceof Activity
                ? ['sometimes', Rule::in([$this->route('activity')->project_id])]
                : ['required', 'integer', Rule::exists('projects', 'id')->whereNot('status', WorkStatus::Archived->value)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'due_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'planned_hours' => ['required', 'numeric', 'min:0', 'max:99999'],
            'status' => ['required', Rule::enum(WorkStatus::class)],
            'assignee_ids' => ['required', 'array', 'min:1'],
            'assignee_ids.*' => ['integer', 'distinct', $assignees],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $project = $this->targetProject();

                if ($project === null || $validator->errors()->has('due_on') || ! is_string($this->input('due_on'))) {
                    return;
                }

                if ($this->input('due_on') > $project->due_on->toDateString()) {
                    $validator->errors()->add('due_on', "La scadenza non può superare quella del progetto ({$project->due_on->format('d/m/Y')}).");
                }
            },
        ];
    }

    public function toDto(): ActivityData
    {
        $activity = $this->route('activity');

        return new ActivityData(
            projectId: $activity instanceof Activity ? $activity->project_id : $this->integer('project_id'),
            name: $this->string('name')->trim()->toString(),
            description: $this->filled('description') ? $this->string('description')->trim()->toString() : null,
            startsOn: $this->string('starts_on')->toString(),
            dueOn: $this->string('due_on')->toString(),
            plannedHours: (float) $this->input('planned_hours'),
            status: WorkStatus::from($this->string('status')->toString()),
            assigneeIds: array_values(array_map('intval', $this->input('assignee_ids', []))),
        );
    }

    private function targetProject(): ?Project
    {
        $activity = $this->route('activity');

        return $activity instanceof Activity
            ? $activity->project
            : Project::find($this->input('project_id'));
    }
}
