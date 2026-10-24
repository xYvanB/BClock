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
     * Ricalcola le quote delle attività ancora aperte che toccano l'intervallo (tutte, se non indicato),
     * eventualmente solo di quelle con almeno uno degli assegnatari indicati.
     * Serve quando cambia il calendario: un festivo o un'assenza confermata.
     *
     * @param  list<int>|null  $userIds
     * @return int Numero di attività ricalcolate
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
