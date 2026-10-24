<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProjectTemplate;
use App\Models\TemplateActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TemplateActivity>
 */
class TemplateActivityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_template_id' => ProjectTemplate::factory(),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'planned_hours' => 8,
            'start_day' => 1,
            'end_day' => 3,
            'position' => 1,
            'suggested_area_id' => null,
            'suggested_role' => null,
        ];
    }
}
