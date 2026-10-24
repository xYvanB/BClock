<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enum\Role;
use App\Models\Area;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'surname' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'role' => Role::Employee,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function boss(): static
    {
        return $this->state(['role' => Role::Boss]);
    }

    public function hr(): static
    {
        return $this->state(['role' => Role::Hr]);
    }

    public function areaHead(?Area $area = null): static
    {
        return $this->state(fn () => [
            'role' => Role::AreaHead,
            'area_id' => $area?->id ?? Area::factory(),
        ]);
    }

    public function employee(?Area $area = null): static
    {
        return $this->state(fn () => [
            'role' => Role::Employee,
            'area_id' => $area?->id,
        ]);
    }
}
