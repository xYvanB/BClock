<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\AbsenceStatus;
use App\Enum\AbsenceType;
use Database\Factories\AbsenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Absence extends Model
{
    /** @use HasFactory<AbsenceFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'starts_on',
        'ends_on',
        'note',
        'status',
        'rejection_reason',
        'confirmed_by',
        'confirmed_at',
    ];

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'type' => AbsenceType::class,
            'status' => AbsenceStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'confirmed_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function isPending(): bool
    {
        return $this->status === AbsenceStatus::Pending;
    }
}
