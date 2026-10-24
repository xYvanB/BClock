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
     * Le ferie coprono uno o più giorni interi; un permesso è in un solo giorno, con orario di inizio e fine.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $isLeave = $this->input('type') === AbsenceType::Leave->value;

        return [
            'type' => ['required', Rule::in(AbsenceType::values())],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', $isLeave ? 'same:starts_on' : 'after_or_equal:starts_on'],
            'starts_time' => [Rule::requiredIf($isLeave), 'nullable', 'date_format:H:i'],
            'ends_time' => [Rule::requiredIf($isLeave), 'nullable', 'date_format:H:i', $isLeave ? 'after:starts_time' : 'nullable'],
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
            'ends_on.same' => 'Un permesso è in un solo giorno.',
            'ends_time.after' => 'L\'ora di fine deve essere successiva all\'inizio.',
        ];
    }

    /**
     * Un permesso non supera la capacità giornaliera; le assenze della stessa persona non si sovrappongono
     * (quelle rifiutate non contano, e due permessi nello stesso giorno vanno bene se gli orari non si toccano).
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['type', 'starts_on', 'ends_on', 'starts_time', 'ends_time'])) {
                    return;
                }

                $isLeave = $this->input('type') === AbsenceType::Leave->value;
                $startsTime = $isLeave ? $this->string('starts_time')->toString() : null;
                $endsTime = $isLeave ? $this->string('ends_time')->toString() : null;

                if ($isLeave) {
                    $hours = (self::minutes($endsTime) - self::minutes($startsTime)) / 60;
                    $capacity = (float) $this->user()->daily_capacity;

                    if ($hours > $capacity) {
                        $validator->errors()->add('ends_time', "Un permesso non può superare le ore di una giornata ({$capacity}).");

                        return;
                    }
                }

                /** @var Absence|null $absence */
                $absence = $this->route('absence');

                $overlaps = Absence::query()
                    ->where('user_id', $this->user()->id)
                    ->where('status', '!=', AbsenceStatus::Rejected)
                    ->when($absence, fn ($query) => $query->whereKeyNot($absence->id))
                    ->whereDate('starts_on', '<=', $this->string('ends_on')->toString())
                    ->whereDate('ends_on', '>=', $this->string('starts_on')->toString())
                    ->get()
                    ->contains(fn (Absence $other): bool => ! $isLeave
                        || $other->type !== AbsenceType::Leave
                        || (self::minutes($startsTime) < self::minutes((string) $other->timeLabel($other->ends_time))
                            && self::minutes((string) $other->timeLabel($other->starts_time)) < self::minutes($endsTime)));

                if ($overlaps) {
                    $validator->errors()->add('starts_on', 'Hai già un\'assenza che si sovrappone a questo periodo.');
                }
            },
        ];
    }

    public function toDto(): AbsenceData
    {
        $type = AbsenceType::from($this->string('type')->toString());
        $isLeave = $type === AbsenceType::Leave;

        return new AbsenceData(
            type: $type,
            startsOn: $this->string('starts_on')->toString(),
            endsOn: $this->string('ends_on')->toString(),
            startsTime: $isLeave ? $this->string('starts_time')->toString() : null,
            endsTime: $isLeave ? $this->string('ends_time')->toString() : null,
            note: $this->filled('note') ? $this->string('note')->trim()->toString() : null,
        );
    }

    private static function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
