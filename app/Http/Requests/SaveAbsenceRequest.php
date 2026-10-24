<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Allocations\CalculateAvailability;
use App\DTO\AbsenceData;
use App\Enum\AbsenceStatus;
use App\Enum\AbsenceType;
use App\Models\Absence;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveAbsenceRequest extends FormRequest
{
    /**
     * Authorizes before validation (AbsencePolicy): anyone enters their own absences,
     * only their own pending ones can be edited.
     */
    public function authorize(): bool
    {
        $absence = $this->route('absence');

        return $absence instanceof Absence
            ? $this->user()->can('update', $absence)
            : true;
    }

    /**
     * Vacations cover one or more whole days; a leave is within a single day, with a start and end time.
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
     * A leave does not exceed the daily capacity; a person's absences do not overlap
     * (rejected ones do not count, and two leaves on the same day are fine if their times do not touch).
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['type', 'starts_on', 'ends_on', 'starts_time', 'ends_time'])) {
                    return;
                }

                $isLeave = $this->input('type') === AbsenceType::Leave->value;

                // a leave cannot fall on a non-working day, a vacation must have at least one working day
                $workingDays = app(CalculateAvailability::class)
                    ->handle([$this->user()->id], $this->string('starts_on')->toString(), $this->string('ends_on')->toString())['working_days'];

                if ($isLeave && $workingDays === []) {
                    $validator->errors()->add('starts_on', 'Il '.CarbonImmutable::parse($this->string('starts_on')->toString())->format('d/m/Y').' non è un giorno lavorativo (weekend, festività o chiusura aziendale).');

                    return;
                }

                if (! $isLeave && $workingDays === []) {
                    $validator->errors()->add('starts_on', 'Il periodo scelto non contiene giorni lavorativi (weekend, festività o chiusure aziendali).');

                    return;
                }

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
