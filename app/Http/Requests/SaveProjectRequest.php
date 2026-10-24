<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\ProjectData;
use App\Enum\Role;
use App\Enum\WorkStatus;
use App\Models\Activity;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\Setting;
use App\Models\TemplateActivity;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class SaveProjectRequest extends FormRequest
{
    /**
     * Autorizza prima della validazione (ProjectPolicy), così chi non può riceve 403 e non errori di campo.
     */
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            ? $this->user()->can('update', $project)
            : $this->user()->can('create', Project::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $project = $this->route('project');

        return [
            ...$this->templateRules($project instanceof Project),
            // Il cliente si sceglie alla creazione e poi non cambia: i referenti dipendono da lui.
            'client_id' => $project instanceof Project
                ? ['sometimes', Rule::in([$project->client_id])]
                : ['required', 'integer', Rule::exists('clients', 'id')],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'due_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'planned_hours' => ['required', 'numeric', 'min:0', 'max:99999'],
            'status' => ['required', $this->statusRule()],
            'member_ids' => ['present', 'array'],
            'member_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
            'contact_ids' => ['present', 'array'],
            'contact_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('client_contacts', 'id')->where('client_id', $this->targetClientId() ?? 0),
            ],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $template = $this->selectedTemplate();

                if ($template === null || $validator->errors()->hasAny(['starts_on', 'due_on'])
                    || ! is_string($this->input('starts_on')) || ! is_string($this->input('due_on'))) {
                    return;
                }

                $lastDay = (int) $template->activities->max('end_day');
                $end = TemplateActivity::dateOfWorkingDay(CarbonImmutable::parse($this->input('starts_on')), $lastDay, Setting::workingWeekdays(), Holiday::closedDates());

                if ($end->toDateString() > $this->input('due_on')) {
                    $validator->errors()->add('template_id', "Le attività del template finiscono il {$end->format('d/m/Y')}, oltre la scadenza del progetto: allunga la scadenza o scegli un altro template.");
                }
            },
            function (Validator $validator): void {
                $project = $this->route('project');

                if (! $project instanceof Project || $validator->errors()->has('due_on') || ! is_string($this->input('due_on'))) {
                    return;
                }

                $lastActivityDue = $project->activities()->max('due_on');

                if ($lastActivityDue !== null && $this->input('due_on') < substr((string) $lastActivityDue, 0, 10)) {
                    $validator->errors()->add('due_on', 'La scadenza non può precedere quella delle sue attività ('.date('d/m/Y', (int) strtotime((string) $lastActivityDue)).').');
                }
            },
        ];
    }

    public function toDto(): ProjectData
    {
        $template = $this->selectedTemplate();

        return new ProjectData(
            clientId: $this->targetClientId() ?? 0,
            name: $this->string('name')->trim()->toString(),
            description: $this->filled('description') ? $this->string('description')->trim()->toString() : null,
            startsOn: $this->string('starts_on')->toString(),
            dueOn: $this->string('due_on')->toString(),
            plannedHours: (float) $this->input('planned_hours'),
            status: WorkStatus::from($this->string('status')->toString()),
            memberIds: array_values(array_map('intval', $this->input('member_ids', []))),
            contactIds: array_values(array_map('intval', $this->input('contact_ids', []))),
            templateId: $template?->id,
            templateAssignees: $template === null ? [] : $this->templateAssignees(),
        );
    }

    /**
     * Il template si sceglie solo alla creazione e solo da chi può creare attività (l'HR no).
     * Ogni attività del template richiede almeno un assegnatario, con le regole di SaveActivityRequest.
     *
     * @return array<string, array<int, mixed>>
     */
    private function templateRules(bool $isUpdate): array
    {
        if ($isUpdate || ! $this->user()->can('create', Activity::class)) {
            return ['template_id' => ['prohibited'], 'template_assignees' => ['prohibited']];
        }

        $rules = [
            'template_id' => ['nullable', 'integer', Rule::exists('project_templates', 'id')],
            'template_assignees' => ['nullable', 'array'],
        ];

        $template = $this->selectedTemplate();

        if ($template === null) {
            return $rules;
        }

        $assignees = Rule::exists('users', 'id');

        if ($this->user()->hasRole(Role::AreaHead)) {
            $assignees->where('area_id', $this->user()->area_id);
        }

        foreach ($template->activities as $activity) {
            $rules["template_assignees.$activity->id"] = ['required', 'array', 'min:1'];
            $rules["template_assignees.$activity->id.*"] = ['integer', 'distinct', $assignees];
        }

        return $rules;
    }

    private function selectedTemplate(): ?ProjectTemplate
    {
        $id = $this->input('template_id');

        if ($this->route('project') instanceof Project || ! is_numeric($id)) {
            return null;
        }

        return ProjectTemplate::with('activities')->find((int) $id);
    }

    /**
     * @return array<int, list<int>>
     */
    private function templateAssignees(): array
    {
        $assignees = [];

        foreach ((array) $this->input('template_assignees', []) as $templateActivityId => $userIds) {
            $assignees[(int) $templateActivityId] = array_values(array_map('intval', (array) $userIds));
        }

        return $assignees;
    }

    /**
     * "Archiviato" si imposta solo con l'azione Archivia (che archivia anche le attività):
     * dal form è ammesso soltanto per un progetto già archiviato, così si può comunque modificarlo.
     */
    private function statusRule(): Enum
    {
        $project = $this->route('project');
        $alreadyArchived = $project instanceof Project && $project->status === WorkStatus::Archived;

        return $alreadyArchived ? Rule::enum(WorkStatus::class) : Rule::enum(WorkStatus::class)->except(WorkStatus::Archived);
    }

    private function targetClientId(): ?int
    {
        $project = $this->route('project');

        return $project instanceof Project ? $project->client_id : $this->integer('client_id');
    }
}
