<?php

declare(strict_types=1);

namespace App\Actions\Gantt;

use App\Actions\Allocations\GenerateActivityAllocations;
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
    ) {}

    /**
     * Applica lo spostamento o il ridimensionamento calcolato da PlanActivityShift, ricalcolandolo al momento
     * (le date o i permessi potrebbero essere cambiati dopo l'anteprima).
     *
     * Si sposta tutto o niente: con un blocco si rifiuta. Le scadenze dei progetti oltrepassate si estendono solo
     * con `$confirmProjects`. Le quote spostate a mano di un'attività che si sposta di N giorni lavorativi si
     * spostano anch'esse di N giorni; poi le quote di ogni attività toccata si ricalcolano.
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
            }

            return $plan['changes'];
        });
    }

    /**
     * Sposta di `$days` giorni lavorativi le quote manuali; quelle che finiscono lo stesso giorno si sommano.
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

        // le quote automatiche si ricalcolano comunque: si tolgono anche loro per non andare in conflitto sul giorno di arrivo
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
