<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enum\AbsenceStatus;
use App\Enum\AbsenceType;
use App\Models\Absence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Absence>
 */
class AbsenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => AbsenceType::Vacation,
            'starts_on' => '2026-11-09',
            'ends_on' => '2026-11-10',
            'note' => null,
            'status' => AbsenceStatus::Pending,
        ];
    }

    public function confirmed(?User $by = null): static
    {
        return $this->state(fn () => [
            'status' => AbsenceStatus::Confirmed,
            'confirmed_by' => $by?->id ?? User::factory()->hr(),
            'confirmed_at' => now(),
        ]);
    }
}
