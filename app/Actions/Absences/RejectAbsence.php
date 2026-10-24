<?php

declare(strict_types=1);

namespace App\Actions\Absences;

use App\Enum\AbsenceStatus;
use App\Models\Absence;
use Illuminate\Support\Facades\DB;
use Throwable;

final class RejectAbsence
{
    /**
     * Rifiuta la richiesta con un motivo; non riduce la capacità.
     *
     * @throws Throwable
     */
    public function handle(Absence $absence, string $reason): Absence
    {
        return DB::transaction(function () use ($absence, $reason): Absence {
            $absence->update([
                'status' => AbsenceStatus::Rejected,
                'rejection_reason' => $reason,
            ]);

            return $absence;
        });
    }
}
