<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\AbsenceData;
use App\Enum\AbsenceStatus;
use App\Enum\AbsenceType;
use App\Models\Absence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveAbsenceRequest extends FormRequest
{
    /**
     * Autorizza prima della validazione (AbsencePolicy): chiunque inserisce le proprie assenze,
     * si modificano solo le proprie in attesa.
     */
    public function authorize(): bool
    {
        $absence = $this->route('absence');

        return $absence instanceof Absence
            ? $this->user()->can('update', $absence)
            : true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(AbsenceType::values())],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ends_on.after_or_equal' => 'La fine non può essere prima dell\'inizio.',
        ];
    }

    /**
     * Le assenze della stessa persona non possono sovrapporsi.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['starts_on', 'ends_on'])) {
                    return;
                }

                /** @var Absence|null $absence */
                $absence = $this->route('absence');

                $overlaps = Absence::query()
                    ->where('user_id', $this->user()->id)
                    ->where('status', '!=', AbsenceStatus::Rejected)
                    ->when($absence, fn ($query) => $query->whereKeyNot($absence->id))
                    ->whereDate('starts_on', '<=', $this->string('ends_on')->toString())
                    ->whereDate('ends_on', '>=', $this->string('starts_on')->toString())
                    ->exists();

                if ($overlaps) {
                    $validator->errors()->add('starts_on', 'Hai già un\'assenza che si sovrappone a questo periodo.');
                }
            },
        ];
    }

    public function toDto(): AbsenceData
    {
        return new AbsenceData(
            type: AbsenceType::from($this->string('type')->toString()),
            startsOn: $this->string('starts_on')->toString(),
            endsOn: $this->string('ends_on')->toString(),
            note: $this->filled('note') ? $this->string('note')->trim()->toString() : null,
        );
    }
}
