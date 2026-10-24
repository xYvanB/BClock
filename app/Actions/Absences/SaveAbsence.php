<?php

declare(strict_types=1);

namespace App\Actions\Absences;

use App\DTO\AbsenceData;
use App\Enum\AbsenceStatus;
use App\Models\Absence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SaveAbsence
{
    /**
     * Crea un'assenza della persona (sempre in attesa) o aggiorna quella indicata, in transazione.
     *
     * @throws Throwable
     */
    public function handle(User $user, AbsenceData $data, ?Absence $absence = null): Absence
    {
        return DB::transaction(function () use ($user, $data, $absence): Absence {
            $attributes = [
                'type' => $data->type,
                'starts_on' => $data->startsOn,
                'ends_on' => $data->endsOn,
                'note' => $data->note,
            ];

            if ($absence === null) {
                return Absence::create($attributes + ['user_id' => $user->id, 'status' => AbsenceStatus::Pending]);
            }

            $absence->update($attributes);

            return $absence;
        });
    }
}
