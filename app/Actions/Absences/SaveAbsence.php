<?php

declare(strict_types=1);

namespace App\Actions\Absences;

use App\Actions\Allocations\RegenerateAllocations;
use App\DTO\AbsenceData;
use App\Enum\AbsenceStatus;
use App\Enum\Role;
use App\Models\Absence;
use App\Models\User;
use App\Notifications\AbsenceRequested;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class SaveAbsence
{
    /**
     * Crea un'assenza della persona (in attesa; quelle del Capo sono subito confermate, nessuno le approva) o aggiorna
     * quella indicata, in transazione. Modificarla azzera le approvazioni già date. Una nuova richiesta di un dipendente
     * avvisa il suo Capo area, una dell'HR avvisa il Capo.
     *
     * @throws Throwable
     */
    public function __construct(private readonly RegenerateAllocations $allocations) {}

    public function handle(User $user, AbsenceData $data, ?Absence $absence = null): Absence
    {
        return DB::transaction(function () use ($user, $data, $absence): Absence {
            $attributes = [
                'type' => $data->type,
                'starts_on' => $data->startsOn,
                'ends_on' => $data->endsOn,
                'starts_time' => $data->startsTime,
                'ends_time' => $data->endsTime,
                'note' => $data->note,
            ];

            if ($absence !== null) {
                $previous = [$absence->starts_on->toDateString(), $absence->ends_on->toDateString()];

                $absence->update($attributes + [
                    'confirmed_by' => null,
                    'confirmed_at' => $user->hasRole(Role::Boss) ? now() : null,
                    'area_head_confirmed_by' => null,
                    'area_head_confirmed_at' => null,
                ]);

                // l'assenza del Capo resta confermata, quindi le quote seguono le nuove date
                if ($user->hasRole(Role::Boss)) {
                    $this->allocations->handle(min($previous[0], $data->startsOn), max($previous[1], $data->endsOn), [$user->id]);
                }

                return $absence;
            }

            if ($user->hasRole(Role::Boss)) {
                $absence = Absence::create($attributes + ['user_id' => $user->id, 'status' => AbsenceStatus::Confirmed, 'confirmed_at' => now()]);
                $this->allocations->handle($data->startsOn, $data->endsOn, [$user->id]);

                return $absence;
            }

            $absence = Absence::create($attributes + ['user_id' => $user->id, 'status' => AbsenceStatus::Pending]);

            // chi deve approvare viene avvisato: il Capo area del dipendente e, per le assenze dell'HR, il Capo
            if ($absence->requiresAreaHead()) {
                Notification::send(
                    User::where('role', Role::AreaHead)->where('area_id', $user->area_id)->get(),
                    new AbsenceRequested($absence),
                );
            }

            if ($user->hasRole(Role::Hr)) {
                Notification::send(User::where('role', Role::Boss)->get(), new AbsenceRequested($absence));
            }

            return $absence;
        });
    }
}
