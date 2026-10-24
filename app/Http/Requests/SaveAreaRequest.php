<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\AreaData;
use App\Models\Area;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveAreaRequest extends FormRequest
{
    /**
     * Autorizza prima della validazione (AreaPolicy), così chi non può riceve 403 e non errori di campo.
     */
    public function authorize(): bool
    {
        $area = $this->route('area');

        return $area instanceof Area
            ? $this->user()->can('update', $area)
            : $this->user()->can('create', Area::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Area|null $area */
        $area = $this->route('area');

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('areas', 'name')->ignore($area?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function toDto(): AreaData
    {
        return new AreaData(
            name: $this->string('name')->trim()->toString(),
            description: $this->filled('description') ? $this->string('description')->trim()->toString() : null,
        );
    }
}
