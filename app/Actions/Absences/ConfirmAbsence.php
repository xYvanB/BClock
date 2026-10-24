<?php

declare(strict_types=1);

namespace App\Actions\Absences;

use App\Actions\Allocations\RegenerateAllocations;
use App\Enum\AbsenceStatus;
use App\Enum\Role;
use App\Models\Absence;
use App\Models\User;
use App\Notifications\AbsenceConfirmedByHr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class ConfirmAbsence
{
    public function __construct(private readonly RegenerateAllocations $allocations) {}

    /**
     * Conferma la richiesta: da qui in poi riduce la capacità giornaliera della persona.
     * Se l'HR conferma una propria assenza, il Capo viene avvisato.
     *
     * @throws Throwable
     */
    public function handle(User $hr, Absence $absence): Absence
    {
        return DB::transaction(function () use ($hr, $absence): Absence {
            $absence->update([
                'status' => AbsenceStatus::Confirmed,
                'confirmed_by' => $hr->id,
                'confirmed_at' => now(),
            ]);

            $this->allocations->handle($absence->starts_on->toDateString(), $absence->ends_on->toDateString(), [$absence->user_id]);

            if ($absence->user_id === $hr->id) {
                Notification::send(User::where('role', Role::Boss)->get(), new AbsenceConfirmedByHr($absence));
            }

            return $absence;
        });
    }
}
