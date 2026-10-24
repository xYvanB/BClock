<?php

declare(strict_types=1);

namespace App\Actions\Activities;

use App\Actions\Allocations\GenerateActivityAllocations;
use App\Actions\Notifications\NotifyUsers;
use App\DTO\ActivityData;
use App\Enum\NotificationKind;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

final class CreateActivity
{
    public function __construct(
        private readonly GenerateActivityAllocations $allocations,
        private readonly NotifyUsers $notify,
    ) {}

    /**
     * Creates an activity with its assignees in a transaction; the assignees (except the creator) receive a notification.
     *
     * @throws Throwable
     */
    public function handle(User $actor, ActivityData $data): Activity
    {
        return DB::transaction(function () use ($actor, $data): Activity {
            $activity = Activity::create([
                'project_id' => $data->projectId,
                'name' => $data->name,
                'description' => $data->description,
                'starts_on' => $data->startsOn,
                'due_on' => $data->dueOn,
                'planned_hours' => $data->plannedHours,
                'status' => $data->status,
            ]);

            $activity->assignees()->sync($data->assigneeIds);

            if ($data->dependencyIds !== null) {
                $activity->dependencies()->sync($data->dependencyIds);
            }

            $this->allocations->handle($activity);

            $this->notify->handle(
                User::whereIn('id', $data->assigneeIds)->get(),
                NotificationKind::TaskAssigned,
                'Ti è stato assegnato «:name».',
                ['name' => $activity->name],
                route('calendar.index'),
                $actor,
            );

            return $activity;
        });
    }
}
