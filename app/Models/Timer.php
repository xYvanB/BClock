<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TimerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Timer extends Model
{
    /** @use HasFactory<TimerFactory> */
    use HasFactory;

    /**
     * Dopo quanti minuti un timer ancora attivo viene segnalato come dimenticato (solo avviso, nessuno stop automatico).
     */
    public const int WARNING_AFTER_MINUTES = 480;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'activity_id',
        'description',
        'started_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
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
