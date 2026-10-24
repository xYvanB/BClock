<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Quota di ore di un'attività su un giorno, per una persona.
 * `is_manual` indica una quota spostata a mano nel calendario: la rigenerazione la rispetta.
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
