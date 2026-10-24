<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\ProjectTemplateData;
use App\DTO\TemplateActivityData;
use App\Enum\Role;
use App\Models\ProjectTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveProjectTemplateRequest extends FormRequest
{
    /**
     * Autorizza prima della validazione (ProjectTemplatePolicy), così chi non può riceve 403 e non errori di campo.
     */
    public function authorize(): bool
    {
        $template = $this->route('projectTemplate');

        return $template instanceof ProjectTemplate
            ? $this->user()->can('update', $template)
            : $this->user()->can('create', ProjectTemplate::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'activities' => ['required', 'array', 'min:1'],
            'activities.*.name' => ['required', 'string', 'max:150'],
            'activities.*.description' => ['nullable', 'string', 'max:2000'],
            'activities.*.planned_hours' => ['required', 'numeric', 'min:0', 'max:99999'],
            'activities.*.start_day' => ['required', 'integer', 'min:1', 'max:3650'],
            'activities.*.end_day' => ['required', 'integer', 'min:1', 'max:3650'],
            'activities.*.suggested_area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')],
            'activities.*.suggested_role' => ['nullable', Rule::enum(Role::class)],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('activities', []) as $index => $activity) {
                    if (! is_array($activity) || $validator->errors()->has("activities.$index.end_day")) {
                        continue;
                    }

                    if (is_numeric($activity['start_day'] ?? null) && is_numeric($activity['end_day'] ?? null)
                        && (int) $activity['end_day'] < (int) $activity['start_day']) {
                        $validator->errors()->add("activities.$index.end_day", "L'ultimo giorno non può precedere il primo.");
                    }
                }
            },
        ];
    }

    public function toDto(): ProjectTemplateData
    {
        return new ProjectTemplateData(
            name: $this->string('name')->trim()->toString(),
            activities: array_values(array_map(
                fn (array $activity): TemplateActivityData => new TemplateActivityData(
                    name: trim((string) $activity['name']),
                    description: filled($activity['description'] ?? null) ? trim((string) $activity['description']) : null,
                    plannedHours: (float) $activity['planned_hours'],
                    startDay: (int) $activity['start_day'],
                    endDay: (int) $activity['end_day'],
                    suggestedAreaId: filled($activity['suggested_area_id'] ?? null) ? (int) $activity['suggested_area_id'] : null,
                    suggestedRole: filled($activity['suggested_role'] ?? null) ? Role::from((string) $activity['suggested_role']) : null,
                ),
                $this->input('activities', []),
            )),
        );
    }
}
