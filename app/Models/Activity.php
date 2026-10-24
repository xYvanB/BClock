<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\WorkStatus;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'name',
        'description',
        'starts_on',
        'due_on',
        'planned_hours',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'due_on' => 'date',
            'planned_hours' => 'decimal:2',
            'status' => WorkStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'activity_assignees');
    }

    /**
     * Activities this one depends on (end → start).
     *
     * @return BelongsToMany<Activity, $this>
     */
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'activity_dependencies', 'activity_id', 'depends_on_id');
    }

    /**
     * Activities that depend on this one (the inverse of `dependencies`).
     *
     * @return BelongsToMany<Activity, $this>
     */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'activity_dependencies', 'depends_on_id', 'activity_id');
    }

    /**
     * True if making `$activityId` depend on the activities `$dependencyIds` creates a cycle: that is, if at least one of them
     * already depends, directly or not, on `$activityId`.
     *
     * @param  list<int>  $dependencyIds
     */
    public static function wouldCreateCycle(int $activityId, array $dependencyIds): bool
    {
        $dependentsOf = DB::table('activity_dependencies')->get(['activity_id', 'depends_on_id'])
            ->groupBy('depends_on_id')
            ->map(fn ($rows) => $rows->pluck('activity_id')->all());

        $seen = [];
        $queue = [$activityId];

        while ($queue !== []) {
            $current = array_pop($queue);

            foreach ($dependentsOf->get($current, []) as $dependent) {
                if (isset($seen[$dependent])) {
                    continue;
                }

                $seen[$dependent] = true;
                $queue[] = $dependent;
            }
        }

        foreach ($dependencyIds as $id) {
            if ($id === $activityId || isset($seen[$id])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return HasMany<ActivityAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(ActivityAllocation::class);
    }

    /**
     * @return HasMany<Timer, $this>
     */
    public function timers(): HasMany
    {
        return $this->hasMany(Timer::class);
    }

    /**
     * @return HasMany<TimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }
}
