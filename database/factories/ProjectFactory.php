<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enum\WorkStatus;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 month', '+1 week');

        return [
            'client_id' => Client::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'starts_on' => $start->format('Y-m-d'),
            'due_on' => (clone $start)->modify('+2 months')->format('Y-m-d'),
            'planned_hours' => 40,
            'status' => WorkStatus::InProgress,
        ];
    }
}
