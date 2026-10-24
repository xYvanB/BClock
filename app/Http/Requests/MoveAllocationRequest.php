<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ActivityAllocation;
use Illuminate\Foundation\Http\FormRequest;

class MoveAllocationRequest extends FormRequest
{
    /**
     * Authorizes before validation: moving an allocation is like editing the activity (ActivityPolicy).
     */
    public function authorize(): bool
    {
        $allocation = $this->route('allocation');

        return $allocation instanceof ActivityAllocation
            && $this->user()->can('update', $allocation->activity);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'extend_activity' => ['sometimes', 'boolean'],
            'extend_project' => ['sometimes', 'boolean'],
            'hours' => ['nullable', 'numeric', 'gt:0', 'max:999'],
        ];
    }

    /**
     * Hours to move: if missing, the whole allocation is moved.
     */
    public function hours(): ?float
    {
        return $this->filled('hours') ? (float) $this->input('hours') : null;
    }

    public function toDate(): string
    {
        return $this->string('date')->toString();
    }
}
