<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Share of an activity's hours on one day, for one person.
 * `is_manual` marks an allocation moved by hand in the calendar: regeneration respects it.
 */
class ActivityAllocation extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'activity_id',
        'user_id',
        'date',
        'hours',
        'is_manual',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'hours' => 'decimal:2',
            'is_manual' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
