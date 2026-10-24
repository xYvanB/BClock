<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

class WorkingWeekdaysRequest extends FormRequest
{
    /**
     * Authorizes before validation (SettingPolicy).
     */
    public function authorize(): bool
    {
        return $this->user()->can('manage', Setting::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'weekdays.required' => __('Scegli almeno un giorno lavorativo.'),
            'weekdays.min' => __('Scegli almeno un giorno lavorativo.'),
        ];
    }

    /**
     * @return list<int>
     */
    public function weekdays(): array
    {
        return array_values(array_map('intval', $this->input('weekdays', [])));
    }
}
