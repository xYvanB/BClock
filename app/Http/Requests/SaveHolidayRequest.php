<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\HolidayData;
use App\Models\Holiday;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveHolidayRequest extends FormRequest
{
    /**
     * Autorizza prima della validazione (HolidayPolicy), così chi non può riceve 403 e non errori di campo.
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.unique' => 'Esiste già un festivo in questa data.',
        ];
    }

    public function toDto(): HolidayData
    {
        return new HolidayData(
            date: $this->string('date')->toString(),
            name: $this->string('name')->trim()->toString(),
        );
    }
}
