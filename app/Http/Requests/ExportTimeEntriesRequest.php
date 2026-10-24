<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enum\Role;
use Illuminate\Foundation\Http\FormRequest;

class ExportTimeEntriesRequest extends FormRequest
{
    /**
     * L'export CSV è di Capo, Capo area (la sua area) e HR; il Dipendente no.
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
