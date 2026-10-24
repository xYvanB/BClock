<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\TimerData;
use App\Enum\WorkStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartTimerRequest extends FormRequest
{
    /**
     * Ognuno può avviare timer (come registrare ore).
     */
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
            'activity_id' => [
                'required',
                'integer',
                Rule::exists('activities', 'id')->whereNot('status', WorkStatus::Archived->value),
                Rule::unique('timers', 'activity_id')->where('user_id', $this->user()->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'activity_id.unique' => 'Hai già un timer attivo su questa attività.',
        ];
    }

    public function toDto(): TimerData
    {
        return new TimerData(
            activityId: $this->integer('activity_id'),
            description: $this->filled('description') ? $this->string('description')->trim()->toString() : null,
        );
    }
}
