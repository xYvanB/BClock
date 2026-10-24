<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\HolidayData;
use App\Enum\HolidayKind;
use App\Models\Holiday;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveHolidayRequest extends FormRequest
{
    /**
     * Authorizes before validation (HolidayPolicy), so whoever cannot gets 403 and not field errors.
     */
    public function authorize(): bool
    {
        $holiday = $this->route('holiday');

        return $holiday instanceof Holiday
            ? $this->user()->can('update', $holiday)
            : $this->user()->can('create', Holiday::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Holiday|null $holiday */
        $holiday = $this->route('holiday');

        return [
            'date' => ['required', 'date_format:Y-m-d', Rule::unique('holidays', 'date')->ignore($holiday?->id)],
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['sometimes', Rule::enum(HolidayKind::class)],
            'until' => $holiday instanceof Holiday ? ['prohibited'] : ['nullable', 'date_format:Y-m-d', 'after:date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.unique' => 'Esiste già un giorno non lavorativo in questa data.',
            'until.after' => 'La fine del periodo deve essere dopo il primo giorno.',
            'until.prohibited' => 'Il periodo si può indicare solo creando una chiusura.',
        ];
    }

    /**
     * A closure period lasts at most three months.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['date', 'until']) || ! $this->filled('until')) {
                    return;
                }

                if (CarbonImmutable::parse($this->string('date')->toString())->addDays(92)->toDateString() < $this->string('until')->toString()) {
                    $validator->errors()->add('until', 'Un periodo può durare al massimo tre mesi.');
                }
            },
        ];
    }

    public function toDto(): HolidayData
    {
        return new HolidayData(
            date: $this->string('date')->toString(),
            name: $this->string('name')->trim()->toString(),
            kind: HolidayKind::tryFrom($this->string('kind')->toString()) ?? HolidayKind::Holiday,
            until: $this->filled('until') ? $this->string('until')->toString() : null,
        );
    }
}
