<?php

declare(strict_types=1);

namespace App\Actions\Absences;

use App\Actions\Allocations\RegenerateAllocations;
use App\Actions\Notifications\NotifyUsers;
use App\DTO\AbsenceData;
use App\Enum\AbsenceStatus;
use App\Enum\NotificationKind;
use App\Enum\Role;
use App\Models\Absence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SaveAbsence
{
    /**
     * Creates an absence for the person (pending; the Boss's absences are confirmed at once, nobody approves them) or updates
     * the given one, in a transaction. Editing it clears the approvals already given. A new request from an employee
     * notifies their area head and HR; one from HR notifies the Boss.
     */
    public function __construct(
        private readonly RegenerateAllocations $allocations,
        private readonly NotifyUsers $notify,
    ) {}

    /**
     * @throws Throwable
     */
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

                // the Boss's absence stays confirmed, so the allocations follow the new dates
                if ($user->hasRole(Role::Boss)) {
                    $this->allocations->handle(min($previous[0], $data->startsOn), max($previous[1], $data->endsOn), [$user->id]);
                }

                if (! $user->hasRole(Role::Boss)) {
                    $this->notifyApprovers($user, $absence, 'ha modificato la richiesta di');
                }

                return $absence;
            }

            if ($user->hasRole(Role::Boss)) {
                $absence = Absence::create($attributes + ['user_id' => $user->id, 'status' => AbsenceStatus::Confirmed, 'confirmed_at' => now()]);
                $this->allocations->handle($data->startsOn, $data->endsOn, [$user->id]);

                return $absence;
            }

            $absence = Absence::create($attributes + ['user_id' => $user->id, 'status' => AbsenceStatus::Pending]);

            $this->notifyApprovers($user, $absence, 'ha chiesto');

            return $absence;
        });
    }

    /**
     * Notifies who has to approve: the employee's area head and whoever gives the final confirmation (HR, or the Boss for HR).
     */
    private function notifyApprovers(User $user, Absence $absence, string $verb): void
    {
        $approvers = User::where('role', $absence->finalApproverRole())->get();

        if ($absence->requiresAreaHead()) {
            $approvers = $approvers->merge(User::where('role', Role::AreaHead)->where('area_id', $user->area_id)->get());
        }

        $this->notify->handle(
            $approvers,
            NotificationKind::AbsenceRequested,
            "{$user->fullName()} {$verb} {$absence->type->value} ({$absence->periodLabel()}): serve la tua approvazione.",
            route('absences.index'),
            $user,
        );
    }
}
