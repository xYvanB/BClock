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
            'starts_time' => null,
            'ends_time' => null,
            'note' => null,
            'status' => AbsenceStatus::Pending,
        ];
    }

    /**
     * A leave of a few hours on the same day.
     */
    public function leave(string $date = '2026-11-09', string $from = '09:00', string $to = '11:00'): static
    {
        return $this->state([
            'type' => AbsenceType::Leave,
            'starts_on' => $date,
            'ends_on' => $date,
            'starts_time' => $from,
            'ends_time' => $to,
        ]);
    }

    /**
     * Confirmed by HR (and, for employees of an area, also approved by the area head).
     */
    public function confirmed(?User $by = null): static
    {
        return $this->state(fn () => [
            'status' => AbsenceStatus::Confirmed,
            'confirmed_by' => $by?->id ?? User::factory()->hr(),
            'confirmed_at' => now(),
        ]);
    }
}
