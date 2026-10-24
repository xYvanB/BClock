<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\DTO\PreferencesData;
use App\Enum\AppLocale;
use App\Enum\Palette;
use App\Enum\TimeFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', Rule::enum(AppLocale::class)],
            'time_format' => ['required', Rule::enum(TimeFormat::class)],
            'palette' => ['required', Rule::enum(Palette::class)],
        ];
    }

    public function toDto(): PreferencesData
    {
        return new PreferencesData(
            locale: AppLocale::from($this->string('locale')->toString()),
            timeFormat: TimeFormat::from($this->string('time_format')->toString()),
            palette: Palette::from($this->string('palette')->toString()),
        );
    }
}
