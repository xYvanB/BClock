<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\AbsenceStatus;
use App\Enum\AbsenceType;
use App\Enum\Role;
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
        'starts_time',
        'ends_time',
        'note',
        'status',
        'rejection_reason',
        'confirmed_by',
        'confirmed_at',
        'area_head_confirmed_by',
        'area_head_confirmed_at',
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
            'area_head_confirmed_at' => 'datetime',
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

    /**
     * Employees of an area also need the approval of their area head.
     */
    public function requiresAreaHead(): bool
    {
        return $this->user->hasRole(Role::Employee) && $this->user->area_id !== null;
    }

    /**
     * Who gives the final confirmation: HR for everyone, except HR themselves, who receive it from the Boss.
     */
    public function finalApproverRole(): Role
    {
        return $this->user->hasRole(Role::Hr) ? Role::Boss : Role::Hr;
    }

    /**
     * Final confirmation given (by HR, or by the Boss if the absence is HR's).
     */
    public function isApprovedByHr(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function isApprovedByAreaHead(): bool
    {
        return $this->area_head_confirmed_at !== null;
    }

    /**
     * True when it has all the required approvals: from here it reduces capacity.
     */
    public function isFullyApproved(): bool
    {
        return $this->isApprovedByHr() && (! $this->requiresAreaHead() || $this->isApprovedByAreaHead());
    }

    /**
     * Hours of a leave (null for vacations), from the start and end times.
     */
    public function leaveHours(): ?float
    {
        if ($this->type !== AbsenceType::Leave || $this->starts_time === null || $this->ends_time === null) {
            return null;
        }

        return round((self::minutesOfDay($this->ends_time) - self::minutesOfDay($this->starts_time)) / 60, 2);
    }

    /**
     * Start/end time in H:i format (the database returns them with seconds).
     */
    public function timeLabel(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
    }

    /**
     * Readable period, for messages and notifications.
     */
    public function periodLabel(): string
    {
        $start = $this->starts_on->format('d/m/Y');

        if ($this->type === AbsenceType::Leave) {
            return "{$start} dalle {$this->timeLabel($this->starts_time)} alle {$this->timeLabel($this->ends_time)}";
        }

        return $this->starts_on->equalTo($this->ends_on) ? $start : "{$start} – {$this->ends_on->format('d/m/Y')}";
    }

    private static function minutesOfDay(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
