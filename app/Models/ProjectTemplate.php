<?php

declare(strict_types=1);

namespace App\Models;

use App\DTO\TemplateActivityData;
use Database\Factories\ProjectTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectTemplate extends Model
{
    /** @use HasFactory<ProjectTemplateFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * The template's activities in the chosen order.
     *
     * @return HasMany<TemplateActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(TemplateActivity::class)->orderBy('position');
    }

    /**
     * Replaces all the activities with the given ones, in the received order (no other table references them).
     *
     * @param  list<TemplateActivityData>  $activities
     */
    public function replaceActivities(array $activities): void
    {
        $this->activities()->delete();

        foreach ($activities as $position => $activity) {
            $this->activities()->create([
                'name' => $activity->name,
                'description' => $activity->description,
                'planned_hours' => $activity->plannedHours,
                'start_day' => $activity->startDay,
                'end_day' => $activity->endDay,
                'position' => $position + 1,
                'suggested_area_id' => $activity->suggestedAreaId,
                'suggested_role' => $activity->suggestedRole,
            ]);
        }
    }

    /**
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }
}
