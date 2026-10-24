<?php

declare(strict_types=1);

namespace App\Actions\Allocations;

use App\Actions\Notifications\NotifyUsers;
use App\Enum\NotificationKind;
use App\Enum\WorkStatus;
use App\Models\ActivityAllocation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class MoveAllocation
{
    public function __construct(
        private readonly CalculateAvailability $availability,
        private readonly NotifyUsers $notify,
    ) {}

    /**
     * Sposta la quota di un giorno su un altro giorno lavorativo, per la stessa persona e la stessa attività.
     *
     * Si sposta solo quella quota, non l'attività. Oltre la scadenza dell'attività serve la conferma ($extendActivity)
     * e la scadenza dell'attività si allunga fino al nuovo giorno; oltre la scadenza del progetto serve anche
     * $extendProject e si allunga pure quella del progetto. Prima dell'inizio dell'attività non si può.
     * Chi non è il proprietario della quota lo avvisa con una notifica. Si possono spostare solo alcune ore (`$hours`, al massimo quelle della quota): il resto resta dov'è.
     * Se nel giorno di arrivo c'è già una quota della stessa attività, le ore si sommano. Un giorno di partenza
     * svuotato resta come quota manuale a 0 ore, così una rigenerazione non lo riempie di nuovo.
     *
     * @throws Throwable
     * @throws ValidationException
     */
    public function handle(User $actor, ActivityAllocation $allocation, string $toDate, bool $extendActivity, bool $extendProject, ?float $hours = null): ActivityAllocation
    {
        return DB::transaction(function () use ($actor, $allocation, $toDate, $extendActivity, $extendProject, $hours): ActivityAllocation {
            $activity = $allocation->activity()->with('project')->firstOrFail();
            $project = $activity->project;

            if (in_array($activity->status, [WorkStatus::Done, WorkStatus::Archived], true)) {
                throw ValidationException::withMessages(['date' => 'Un\'attività completata o archiviata non si può più spostare.']);
            }

            if ($toDate === $allocation->date->toDateString()) {
                throw ValidationException::withMessages(['date' => 'La quota è già in questo giorno.']);
            }

            if ((float) $allocation->hours <= 0) {
                throw ValidationException::withMessages(['date' => 'In questo giorno non ci sono ore da spostare.']);
            }

            if (! in_array($toDate, $this->availability->handle([$allocation->user_id], $toDate, $toDate)['working_days'], true)) {
                throw ValidationException::withMessages(['date' => 'Il giorno scelto non è lavorativo (weekend o festivo).']);
            }

            if ($toDate < $activity->starts_on->toDateString()) {
                throw ValidationException::withMessages(['date' => 'Non puoi spostare la quota prima dell\'inizio dell\'attività.']);
            }

            $beyondActivity = $toDate > $activity->due_on->toDateString();
            $beyondProject = $toDate > $project->due_on->toDateString();

            if ($beyondActivity && ! $extendActivity) {
                throw ValidationException::withMessages(['extend_activity' => 'Il giorno scelto è oltre la scadenza dell\'attività: serve la conferma per estenderla.']);
            }

            if ($beyondProject && ! $extendProject) {
                throw ValidationException::withMessages(['extend_project' => 'Il giorno scelto è oltre la scadenza del progetto: serve la conferma per estenderla.']);
            }

            if ($beyondActivity) {
                $activity->update(['due_on' => $toDate]);
            }

            if ($beyondProject) {
                $project->update(['due_on' => $toDate]);
            }

            $available = (float) $allocation->hours;
            $moved = $hours === null ? $available : round($hours, 2);

            if ($moved <= 0 || $moved > $available) {
                throw ValidationException::withMessages(['hours' => 'Indica da 0,01 a '.rtrim(rtrim(number_format($available, 2, ',', ''), '0'), ',').' ore.']);
            }

            $target = ActivityAllocation::query()->firstOrNew([
                'activity_id' => $allocation->activity_id,
                'user_id' => $allocation->user_id,
                'date' => $toDate,
            ]);
            $target->hours = round((float) ($target->exists ? $target->hours : 0) + $moved, 2);
            $target->is_manual = true;
            $target->save();

            $from = $allocation->date->format('d/m/Y');
            $allocation->update(['hours' => round($available - $moved, 2), 'is_manual' => true]);

            $this->notify->handle(
                [$allocation->user],
                NotificationKind::QuotaMoved,
                "{$actor->fullName()} ha spostato le tue ore di «{$activity->name}» dal {$from} al ".$target->date->format('d/m/Y').'.',
                route('calendar.index'),
                $actor,
            );

            return $target;
        });
    }
}
