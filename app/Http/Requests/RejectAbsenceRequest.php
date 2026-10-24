<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Absence;
use Illuminate\Foundation\Http\FormRequest;

class RejectAbsenceRequest extends FormRequest
{
    /**
     * Autorizza prima della validazione (AbsencePolicy::reject).
     */
    public function authorize(): bool
    {
        $absence = $this->route('absence');

        return $absence instanceof Absence && $this->user()->can('reject', $absence);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function reason(): string
    {
        return $this->string('reason')->trim()->toString();
    }
}
