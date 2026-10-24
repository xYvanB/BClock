<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\Role;
use App\Enum\TimeEntrySource;
use Database\Factories\TimeEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    /** @use HasFactory<TimeEntryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'activity_id',
        'date',
        'started_at',
        'ended_at',
        'duration_minutes',
        'description',
        'source',
    ];

    /**
     * Entries `$viewer` sees in the dashboard: all for the Boss and HR, those of the people of their area
     * for the area head (only their own if they have no area), their own for the Employee.
     *
     * @param  Builder<TimeEntry>  $query
     */
    public function scopeVisibleTo(Builder $query, User $viewer): void
    {
        match (true) {
            $viewer->hasRole(Role::Boss, Role::Hr) => null,
            $viewer->hasRole(Role::AreaHead) && $viewer->area_id !== null => $query->whereIn('time_entries.user_id', User::query()->where('area_id', $viewer->area_id)->select('id')),
            default => $query->where('time_entries.user_id', $viewer->id),
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'duration_minutes' => 'integer',
            'source' => TimeEntrySource::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
