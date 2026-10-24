<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enum\AbsenceStatus;
use App\Models\Absence;
use Illuminate\Foundation\Http\FormRequest;

class DeleteAbsenceRequest extends FormRequest
{
    /**
     * Authorizes before validation (AbsencePolicy::delete).
     */
    public function authorize(): bool
    {
        $absence = $this->route('absence');

        return $absence instanceof Absence && $this->user()->can('delete', $absence);
    }

    /**
     * Deleting an already confirmed absence of someone else requires a reason (whoever removes their own need not explain anything).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Absence $absence */
        $absence = $this->route('absence');
        $mustExplain = $absence->status === AbsenceStatus::Confirmed && $absence->user_id !== $this->user()->id;

        return [
            'reason' => [$mustExplain ? 'required' : 'nullable', 'string', 'max:1000'],
        ];
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
