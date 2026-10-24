<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\TimeEntryData;
use App\Enum\WorkStatus;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTimeEntryRequest extends FormRequest
{
    /**
     * Autorizza prima della validazione (TimeEntryPolicy): su una registrazione esistente solo il proprietario.
     */
    public function authorize(): bool
    {
        $entry = $this->route('time_entry');

        return $entry instanceof TimeEntry ? $this->user()->can('update', $entry) : true;
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
            ],
            'date' => ['required', 'date_format:Y-m-d'],
            'started_at' => ['required', 'date_format:H:i'],
            'ended_at' => ['required', 'date_format:H:i', 'after:started_at'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function toDto(): TimeEntryData
    {
        return new TimeEntryData(
            activityId: $this->integer('activity_id'),
            date: CarbonImmutable::createFromFormat('Y-m-d', $this->string('date')->toString())->startOfDay(),
            startedAt: $this->string('started_at')->toString(),
            endedAt: $this->string('ended_at')->toString(),
            description: $this->filled('description') ? $this->string('description')->trim()->toString() : null,
        );
    }
}
