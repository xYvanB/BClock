<?php

declare(strict_types=1);

namespace App\Actions\Allocations;

use App\Enum\WorkStatus;
use App\Models\Activity;
use Illuminate\Support\Facades\DB;
use Throwable;

final class RegenerateAllocations
{
    public function __construct(private readonly GenerateActivityAllocations $generate) {}

    /**
     * Recalculates the allocations of still-open activities that touch the range (all of them, if not given),
     * optionally only those with at least one of the given assignees.
     * Needed when the calendar changes: a holiday or a confirmed absence.
     *
     * @param  list<int>|null  $userIds
     * @return int Number of activities recalculated
     *
     * @throws Throwable
     */
    public function handle(?string $from = null, ?string $to = null, ?array $userIds = null): int
    {
        return DB::transaction(function () use ($from, $to, $userIds): int {
            $activities = Activity::query()
                ->whereNotIn('status', [WorkStatus::Done, WorkStatus::Archived])
                ->when($from !== null, fn ($query) => $query->whereDate('due_on', '>=', $from))
                ->when($to !== null, fn ($query) => $query->whereDate('starts_on', '<=', $to))
                ->when($userIds !== null, fn ($query) => $query->whereHas('assignees', fn ($assignees) => $assignees->whereIn('users.id', $userIds)))
                ->get();

            foreach ($activities as $activity) {
                $this->generate->handle($activity);
            }

            return $activities->count();
        });
    }
}
