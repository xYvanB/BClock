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
     * Attività da cui questa dipende (fine → inizio).
     *
     * @return BelongsToMany<Activity, $this>
     */
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'activity_dependencies', 'activity_id', 'depends_on_id');
    }

    /**
     * @return HasMany<ActivityAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(ActivityAllocation::class);
    }

    /**
     * @return HasMany<TimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }
}
