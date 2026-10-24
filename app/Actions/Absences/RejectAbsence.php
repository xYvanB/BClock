<?php

declare(strict_types=1);

namespace App\Actions\Absences;

use App\Actions\Notifications\NotifyUsers;
use App\Enum\AbsenceStatus;
use App\Enum\NotificationKind;
use App\Models\Absence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

final class RejectAbsence
{
    public function __construct(private readonly NotifyUsers $notify) {}

    /**
     * Rejects the request with a reason (which reaches the person); it does not reduce capacity.
     *
     * @throws Throwable
     */
    public function handle(User $rejecter, Absence $absence, string $reason): Absence
    {
        return DB::transaction(function () use ($rejecter, $absence, $reason): Absence {
            $absence->update([
                'status' => AbsenceStatus::Rejected,
                'rejection_reason' => $reason,
            ]);

            $this->notify->handle(
                [$absence->user],
                NotificationKind::AbsenceDecision,
                ':name ha rifiutato la tua richiesta di :type (:period): «:reason».',
                ['name' => $rejecter->fullName(), 'type' => $absence->type->value, 'period' => $absence->periodLabel(), 'reason' => $reason],
                route('absences.index'),
                $rejecter,
            );

            return $absence;
        });
    }
}
