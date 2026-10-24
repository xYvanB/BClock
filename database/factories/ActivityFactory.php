<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enum\WorkStatus;
use App\Models\Activity;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'starts_on' => now()->toDateString(),
            'due_on' => now()->addDays(14)->toDateString(),
            'planned_hours' => 16,
            'status' => WorkStatus::InProgress,
        ];
    }

    public function archived(): static
    {
        return $this->state(['status' => WorkStatus::Archived]);
    }
}
