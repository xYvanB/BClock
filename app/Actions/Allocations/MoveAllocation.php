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
     * Moves the allocation of one day to another working day, for the same person and the same activity.
     *
     * Only that allocation moves, not the activity. Beyond the activity due date the confirmation ($extendActivity) is required
     * and the activity due date is extended to the new day; beyond the project due date $extendProject is also required
     * and the project due date is extended too. Before the activity start it is not allowed.
     * Whoever does not own the allocation notifies the owner. Only some hours can be moved (`$hours`, at most those of the allocation): the rest stays where it is.
     * If the target day already has an allocation of the same activity, the hours add up. A source day
     * that is emptied stays as a manual allocation of 0 hours, so a regeneration does not fill it again.
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
                throw ValidationException::withMessages(['date' => __('Un\'attività completata o archiviata non si può più spostare.')]);
            }

            if ($toDate === $allocation->date->toDateString()) {
                throw ValidationException::withMessages(['date' => __('La quota è già in questo giorno.')]);
            }

            if ((float) $allocation->hours <= 0) {
                throw ValidationException::withMessages(['date' => __('In questo giorno non ci sono ore da spostare.')]);
            }

            if (! in_array($toDate, $this->availability->handle([$allocation->user_id], $toDate, $toDate)['working_days'], true)) {
                throw ValidationException::withMessages(['date' => __('Il giorno scelto non è lavorativo (weekend o festivo).')]);
            }

            if ($toDate < $activity->starts_on->toDateString()) {
                throw ValidationException::withMessages(['date' => __('Non puoi spostare la quota prima dell\'inizio dell\'attività.')]);
            }

            $beyondActivity = $toDate > $activity->due_on->toDateString();
            $beyondProject = $toDate > $project->due_on->toDateString();

            if ($beyondActivity && ! $extendActivity) {
                throw ValidationException::withMessages(['extend_activity' => __('Il giorno scelto è oltre la scadenza dell\'attività: serve la conferma per estenderla.')]);
            }

            if ($beyondProject && ! $extendProject) {
                throw ValidationException::withMessages(['extend_project' => __('Il giorno scelto è oltre la scadenza del progetto: serve la conferma per estenderla.')]);
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
                throw ValidationException::withMessages(['hours' => __('Indica da 0,01 a :max ore.', ['max' => rtrim(rtrim(number_format($available, 2, ',', ''), '0'), ',')])]);
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
                ':name ha spostato le tue ore di «:activity» dal :from al :to.',
                ['name' => $actor->fullName(), 'activity' => $activity->name, 'from' => $from, 'to' => $target->date->format('d/m/Y')],
                route('calendar.index'),
                $actor,
            );

            return $target;
        });
    }
}
