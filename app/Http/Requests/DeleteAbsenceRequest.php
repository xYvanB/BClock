<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enum\AbsenceStatus;
use App\Models\Absence;
use Illuminate\Foundation\Http\FormRequest;

class DeleteAbsenceRequest extends FormRequest
{
    /**
     * Autorizza prima della validazione (AbsencePolicy::delete).
     */
    public function authorize(): bool
    {
        $absence = $this->route('absence');

        return $absence instanceof Absence && $this->user()->can('delete', $absence);
    }

    /**
     * Per eliminare un'assenza già confermata serve un motivo.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Absence $absence */
        $absence = $this->route('absence');
        $mustExplain = $absence->status === AbsenceStatus::Confirmed;

        return [
            'reason' => [$mustExplain ? 'required' : 'nullable', 'string', 'max:1000'],
        ];
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->toString() : null;
    }
}
