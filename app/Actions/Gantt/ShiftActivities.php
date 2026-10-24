<?php

declare(strict_types=1);

namespace App\Actions\Gantt;

use App\Actions\Allocations\GenerateActivityAllocations;
use App\Actions\Notifications\NotifyUsers;
use App\Enum\NotificationKind;
use App\Models\Activity;
use App\Models\ActivityAllocation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ShiftActivities
{
    public function __construct(
        private readonly PlanActivityShift $plan,
        private readonly GenerateActivityAllocations $allocations,
        private readonly NotifyUsers $notify,
    ) {}

    /**
     * Applies the move or resize computed by PlanActivityShift, recomputing it at the moment
     * (the dates or the leaves may have changed after the preview).
     *
     * All or nothing: with a blocker it is refused. Exceeded project due dates are extended only
     * with `$confirmProjects`. The manually moved allocations of an activity that moves by N working days
     * move by N days too; then the allocations of every touched activity are recalculated and its assignees (except whoever moves it)
     * receive a single notification per activity.
     *
     * @return list<array<string, mixed>> I cambi applicati
     *
     * @throws Throwable
     * @throws ValidationException
     */
    public function handle(User $user, Activity $activity, string $mode, string $startsOn, string $dueOn, bool $confirmProjects): array
    {
        return DB::transaction(function () use ($user, $activity, $mode, $startsOn, $dueOn, $confirmProjects): array {
            $plan = $this->plan->handle($user, $activity, $mode, $startsOn, $dueOn);

            if ($plan['blockers'] !== []) {
                throw ValidationException::withMessages(['activity' => implode(' ', $plan['blockers'])]);
            }

            if ($plan['changes'] === []) {
                throw ValidationException::withMessages(['activity' => 'Nessuna modifica da applicare.']);
            }

            if ($plan['projects'] !== [] && ! $confirmProjects) {
                throw ValidationException::withMessages(['confirm_projects' => 'Lo spostamento supera la scadenza di almeno un progetto: serve la conferma per estenderla.']);
            }

            foreach ($plan['projects'] as $extension) {
                Project::whereKey($extension['project_id'])->update(['due_on' => $extension['new_due_on']]);
            }

            foreach ($plan['changes'] as $change) {
                $moved = Activity::findOrFail($change['activity_id']);
                $moved->update(['starts_on' => $change['new_starts_on'], 'due_on' => $change['new_due_on']]);

                if ($change['shift_manual']) {
                    $this->shiftManualAllocations($moved, $plan['shift']);
                }

                $this->allocations->handle($moved->refresh());

                $this->notify->handle(
                    $moved->assignees,
                    NotificationKind::ActivityMoved,
                    "Date cambiate: «{$moved->name}» ora va dal {$moved->starts_on->format('d/m/Y')} al {$moved->due_on->format('d/m/Y')}.",
                    route('gantt.index'),
                    $user,
                );
            }

            return $plan['changes'];
        });
    }

    /**
     * Shifts the manual allocations by `$days` working days; those landing on the same day add up.
     */
    private function shiftManualAllocations(Activity $activity, int $days): void
    {
        $manual = $activity->allocations()->where('is_manual', true)->get();

        if ($manual->isEmpty()) {
            return;
        }

        $shifted = [];
        foreach ($manual as $allocation) {
            $key = $allocation->user_id.'|'.$this->plan->shiftWorkingDays($allocation->date->toDateString(), $days);
            $shifted[$key] = ($shifted[$key] ?? 0) + (int) round((float) $allocation->hours * 100);
        }

        // the automatic allocations are recalculated anyway: remove them too, so they do not collide on the target day
        $activity->allocations()->delete();

        foreach ($shifted as $key => $cents) {
            [$userId, $date] = explode('|', $key);
            ActivityAllocation::create([
                'activity_id' => $activity->id,
                'user_id' => (int) $userId,
                'date' => $date,
                'hours' => $cents / 100,
                'is_manual' => true,
            ]);
        }
    }
}
