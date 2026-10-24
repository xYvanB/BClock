<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Activity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShiftActivityRequest extends FormRequest
{
    /**
     * Authorizes before validation: moving an activity is like editing it (ActivityPolicy).
     */
    public function authorize(): bool
    {
        $activity = $this->route('activity');

        return $activity instanceof Activity && $this->user()->can('update', $activity);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['move', 'resize_start', 'resize_end'])],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'due_on' => ['required', 'date_format:Y-m-d'],
            'confirm_projects' => ['sometimes', 'boolean'],
            'project_id' => ['sometimes', 'nullable', 'integer'],
            'area_id' => ['sometimes', 'nullable', 'integer'],
            'user_id' => ['sometimes', 'nullable', 'integer'],
            'status' => ['sometimes', 'nullable', 'string'],
            'zoom' => ['sometimes', 'nullable', Rule::in(['day', 'week', 'month'])],
        ];
    }

    /**
     * Filters and zoom to keep after the move.
     *
     * @return array<string, mixed>
     */
    public function view(): array
    {
        return array_filter($this->only(['project_id', 'area_id', 'user_id', 'status', 'zoom']), fn ($value) => $value !== null && $value !== '');
    }
}
