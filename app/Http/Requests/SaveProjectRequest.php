<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\ProjectData;
use App\Enum\WorkStatus;
use App\Models\Project;
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
        );
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
