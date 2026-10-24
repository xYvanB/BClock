<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\DTO\UserData;
use App\Enum\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveUserRequest extends FormRequest
{
    /**
     * Autorizza prima della validazione (UserPolicy), così chi non può riceve 403 e non errori di campo.
     */
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User
            ? $this->user()->can('update', $target)
            : $this->user()->can('create', User::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User|null $target */
        $target = $this->route('user');
        $assignable = array_map(fn (Role $role) => $role->value, $this->user()->role->assignableRoles());

        // Nessuno cambia il proprio ruolo: evita auto-promozioni e il blocco dell'unico Capo.
        if ($target?->is($this->user())) {
            $assignable = [$target->role->value];
        }

        return [
            'name' => ['required', 'string', 'max:120'],
            'surname' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($target?->id)],
            'role' => ['required', Rule::in($assignable)],
            'area_id' => [
                Rule::requiredIf(fn () => $this->input('role') === Role::AreaHead->value),
                'nullable',
                'integer',
                Rule::exists('areas', 'id'),
            ],
            'daily_capacity' => ['required', 'numeric', 'min:0.5', 'max:24'],
        ];
    }

    /**
     * Il Capo area opera solo sulla propria area: l'area è forzata e non modificabile.
     */
    public function toDto(): UserData
    {
        $actor = $this->user();

        return new UserData(
            name: $this->string('name')->trim()->toString(),
            surname: $this->string('surname')->trim()->toString(),
            email: mb_strtolower($this->string('email')->trim()->toString()),
            role: Role::from($this->string('role')->toString()),
            areaId: $actor->hasRole(Role::AreaHead) ? $actor->area_id : ($this->filled('area_id') ? $this->integer('area_id') : null),
            dailyCapacity: (float) $this->input('daily_capacity'),
        );
    }
}
