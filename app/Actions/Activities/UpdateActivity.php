<?php

declare(strict_types=1);

namespace App\Actions\Activities;

use App\Actions\Allocations\GenerateActivityAllocations;
use App\Actions\Notifications\NotifyUsers;
use App\DTO\ActivityData;
use App\Enum\NotificationKind;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

final class UpdateActivity
{
    public function __construct(
        private readonly GenerateActivityAllocations $allocations,
        private readonly NotifyUsers $notify,
    ) {}

    /**
     * Updates the activity and its assignees in a transaction. The project does not change. Whoever is added receives the
     * assignment notification; whoever stays assigned receives the moved-dates one (if the dates changed). The actor is not notified.
     *
     * @throws Throwable
     */
    public function handle(User $actor, Activity $activity, ActivityData $data): Activity
    {
        return DB::transaction(function () use ($actor, $activity, $data): Activity {
            $before = [$activity->starts_on->toDateString(), $activity->due_on->toDateString()];

            $activity->update([
                'name' => $data->name,
                'description' => $data->description,
                'starts_on' => $data->startsOn,
                'due_on' => $data->dueOn,
                'planned_hours' => $data->plannedHours,
                'status' => $data->status,
            ]);

            $changes = $activity->assignees()->sync($data->assigneeIds);

            if ($data->dependencyIds !== null) {
                $activity->dependencies()->sync($data->dependencyIds);
            }

            $this->allocations->handle($activity);

            $this->notify->handle(
                User::whereIn('id', $changes['attached'])->get(),
                NotificationKind::TaskAssigned,
                'Ti è stato assegnato «:name».',
                ['name' => $activity->name],
                route('calendar.index'),
                $actor,
            );

            $this->notify->handle(
                User::whereIn('id', $changes['detached'])->get(),
                NotificationKind::TaskAssigned,
                'Non sei più assegnato a «:name».',
                ['name' => $activity->name],
                route('calendar.index'),
                $actor,
            );

            $activity->refresh();

            if ($before !== [$activity->starts_on->toDateString(), $activity->due_on->toDateString()]) {
                $this->notify->handle(
                    User::whereIn('id', $data->assigneeIds)->whereNotIn('id', $changes['attached'])->get(),
                    NotificationKind::ActivityMoved,
                    'Date cambiate: «:name» ora va dal :from al :to (era dal :oldFrom al :oldTo).',
                    [
                        'name' => $activity->name,
                        'from' => $activity->starts_on->format('d/m/Y'),
                        'to' => $activity->due_on->format('d/m/Y'),
                        'oldFrom' => $this->format($before[0]),
                        'oldTo' => $this->format($before[1]),
                    ],
                    route('calendar.index'),
                    $actor,
                );
            }

            return $activity;
        });
    }

    private function format(string $date): string
    {
        return Carbon::parse($date)->format('d/m/Y');
    }
}
