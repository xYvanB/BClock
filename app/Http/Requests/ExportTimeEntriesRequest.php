<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enum\Role;
use Illuminate\Foundation\Http\FormRequest;

class ExportTimeEntriesRequest extends FormRequest
{
    /**
     * The CSV export is for the Boss, area head (their area) and HR; not for the Employee.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole(Role::Boss, Role::AreaHead, Role::Hr);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
