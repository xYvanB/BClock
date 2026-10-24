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
     * I dipendenti di un'area hanno bisogno anche dell'approvazione del loro Capo area.
     */
    public function requiresAreaHead(): bool
    {
        return $this->user->hasRole(Role::Employee) && $this->user->area_id !== null;
    }

    /**
     * Chi dà la conferma finale: l'HR per tutti, tranne che per l'HR stessa, che la riceve dal Capo.
     */
    public function finalApproverRole(): Role
    {
        return $this->user->hasRole(Role::Hr) ? Role::Boss : Role::Hr;
    }

    /**
     * Conferma finale data (dall'HR, o dal Capo se l'assenza è dell'HR).
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
     * Vero quando ha tutte le approvazioni richieste: da qui riduce la capacità.
     */
    public function isFullyApproved(): bool
    {
        return $this->isApprovedByHr() && (! $this->requiresAreaHead() || $this->isApprovedByAreaHead());
    }

    /**
     * Ore di un permesso (null per le ferie), dall'orario di inizio e di fine.
     */
    public function leaveHours(): ?float
    {
        if ($this->type !== AbsenceType::Leave || $this->starts_time === null || $this->ends_time === null) {
            return null;
        }

        return round((self::minutesOfDay($this->ends_time) - self::minutesOfDay($this->starts_time)) / 60, 2);
    }

    /**
     * Orario di inizio/fine nel formato H:i (il database li restituisce con i secondi).
     */
    public function timeLabel(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
    }

    /**
     * Periodo leggibile, per messaggi e notifiche.
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
