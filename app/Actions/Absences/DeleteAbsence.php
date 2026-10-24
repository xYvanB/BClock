<?php

declare(strict_types=1);

namespace App\Actions\Absences;

use App\Actions\Allocations\RegenerateAllocations;
use App\Actions\Notifications\NotifyUsers;
use App\Enum\AbsenceStatus;
use App\Enum\NotificationKind;
use App\Models\Absence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DeleteAbsence
{
    public function __construct(
        private readonly RegenerateAllocations $allocations,
        private readonly NotifyUsers $notify,
    ) {}

    /**
     * Elimina l'assenza. Se è confermata ed è l'HR a toglierla a un'altra persona, la persona riceve il motivo.
     *
     * @throws Throwable
     */
    public function handle(User $user, Absence $absence, ?string $reason = null): void
    {
        DB::transaction(function () use ($user, $absence, $reason): void {
            if ($absence->status === AbsenceStatus::Confirmed && $absence->user_id !== $user->id && $reason !== null) {
                $this->notify->handle(
                    [$absence->user],
                    NotificationKind::AbsenceDecision,
                    "{$user->fullName()} ha eliminato la tua assenza confermata ({$absence->periodLabel()}): «{$reason}».",
                    route('absences.index'),
                    $user,
                );
            }

            $wasConfirmed = $absence->status === AbsenceStatus::Confirmed;

            $absence->delete();

            if ($wasConfirmed) {
                $this->allocations->handle($absence->starts_on->toDateString(), $absence->ends_on->toDateString(), [$absence->user_id]);
            }
        });
    }
}
