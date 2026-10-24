<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\DTO\WorkloadSettingsData;
use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;

class WorkloadSettingsRequest extends FormRequest
{
    /**
     * Autorizza prima della validazione (SettingPolicy).
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
            'tolerance_enabled' => ['required', 'boolean'],
            'tolerance_hours' => ['required', 'numeric', 'min:0.01', 'max:4'],
        ];
    }

    public function toDto(): WorkloadSettingsData
    {
        return new WorkloadSettingsData(
            toleranceEnabled: $this->boolean('tolerance_enabled'),
            toleranceHours: round((float) $this->input('tolerance_hours'), 2),
        );
    }
}
