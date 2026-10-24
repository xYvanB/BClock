<?php

declare(strict_types=1);

namespace App\Actions\Absences;

use App\Actions\Allocations\RegenerateAllocations;
use App\Enum\AbsenceStatus;
use App\Models\Absence;
use App\Models\User;
use App\Notifications\AbsenceRemovedByHr;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DeleteAbsence
{
    public function __construct(private readonly RegenerateAllocations $allocations) {}

    /**
     * Elimina l'assenza. Se è confermata ed è l'HR a toglierla a un'altra persona, la persona riceve il motivo.
     *
     * @throws Throwable
     */
    public function handle(User $user, Absence $absence, ?string $reason = null): void
    {
        DB::transaction(function () use ($user, $absence, $reason): void {
            if ($absence->status === AbsenceStatus::Confirmed && $absence->user_id !== $user->id && $reason !== null) {
                $period = $absence->starts_on->format('d/m/Y').' – '.$absence->ends_on->format('d/m/Y');
                $absence->user->notify(new AbsenceRemovedByHr($period, $reason));
            }

            $wasConfirmed = $absence->status === AbsenceStatus::Confirmed;

            $absence->delete();

            if ($wasConfirmed) {
                $this->allocations->handle($absence->starts_on->toDateString(), $absence->ends_on->toDateString(), [$absence->user_id]);
            }
        });
    }
}
