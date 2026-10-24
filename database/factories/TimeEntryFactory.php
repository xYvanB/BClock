<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enum\TimeEntrySource;
use App\Models\Activity;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeEntry>
 */
class TimeEntryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'activity_id' => Activity::factory(),
            'date' => now()->toDateString(),
            'started_at' => '09:00:00',
            'ended_at' => '12:30:00',
            'duration_minutes' => 210,
            'description' => fake()->sentence(),
            'source' => TimeEntrySource::Manual,
        ];
    }
}
