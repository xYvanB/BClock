<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Actions\Allocations\CalculateAvailability;
use App\Models\TimeEntry;
use App\Models\User;

final class DayHoursReport
{
    public function __construct(private readonly CalculateAvailability $availability) {}

    /**
     * Ore registrate in un giorno, per persona, con il totale rispetto alla capacità del giorno (festivi, ferie e
     * permessi confermati compresi). Senza persone indicate mostra quelle visibili che quel giorno hanno registrazioni.
     * Le persone non visibili a `$viewer` sono ignorate.
     *
     * @param  list<int>  $userIds
     * @return array{
     *     day: string,
     *     people: list<array{id: int, name: string, capacity_hours: float, total_minutes: int, entries: list<array{id: int, activity: string, project: string, started_at: string, ended_at: string, minutes: int, description: string|null}>}>,
     *     options: list<array{id: int, name: string}>
     * }
     */
    public function handle(User $viewer, string $day, array $userIds): array
    {
        $visible = User::query()->visibleTo($viewer)->orderBy('surname')->orderBy('name')->get();

        $entries = TimeEntry::query()
            ->visibleTo($viewer)
            ->with('activity.project:id,name')
            ->whereDate('date', $day)
            ->when($userIds !== [], fn ($query) => $query->whereIn('time_entries.user_id', $userIds))
            ->orderBy('started_at')
            ->get()
            ->groupBy('user_id');

        $shown = $visible->filter(fn (User $user): bool => $userIds === []
            ? $entries->has($user->id)
            : in_array($user->id, $userIds, true));

        $capacity = $shown->isEmpty() ? [] : $this->availability->handle($shown->pluck('id')->all(), $day, $day)['hours'];

        return [
            'day' => $day,
            'people' => $shown->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->fullName(),
                'capacity_hours' => $capacity[$user->id][$day] ?? 0.0,
                'total_minutes' => (int) $entries->get($user->id, collect())->sum('duration_minutes'),
                'entries' => $entries->get($user->id, collect())->map(fn (TimeEntry $entry): array => [
                    'id' => $entry->id,
                    'activity' => $entry->activity->name,
                    'project' => $entry->activity->project->name,
                    'started_at' => substr($entry->started_at, 0, 5),
                    'ended_at' => substr($entry->ended_at, 0, 5),
                    'minutes' => $entry->duration_minutes,
                    'description' => $entry->description,
                ])->values()->all(),
            ])->values()->all(),
            'options' => $visible->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->fullName()])->values()->all(),
        ];
    }
}
