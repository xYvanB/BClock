<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Enum\Role;
use App\Models\Area;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final class WorkedHoursReport
{
    /**
     * Ore lavorate: persone × periodi (giorno, settimana da lunedì o mese) nell'intervallo, con i totali.
     * Si possono filtrare per persona e per area; mostra solo chi `$viewer` può vedere e ha ore nel periodo.
     * I periodi ai bordi sono tagliati sull'intervallo richiesto.
     *
     * @return array{
     *     granularity: string,
     *     buckets: list<array{label: string, from: string, to: string}>,
     *     rows: list<array{id: int, name: string, minutes: list<int>, total: int}>,
     *     column_totals: list<int>,
     *     grand_total: int,
     *     people: list<array{id: int, name: string}>,
     *     areas: list<array{id: int, name: string}>
     * }
     */
    public function handle(User $viewer, string $from, string $to, string $granularity, ?int $userId, ?int $areaId): array
    {
        $granularity = in_array($granularity, ['day', 'week', 'month'], true) ? $granularity : 'week';
        $buckets = $this->buckets($from, $to, $granularity);

        $people = User::query()->visibleTo($viewer)->orderBy('surname')->orderBy('name')->get();

        $perDay = TimeEntry::query()
            ->visibleTo($viewer)
            ->whereBetween('time_entries.date', [$from, $to])
            ->when($userId, fn (Builder $query, int $id) => $query->where('time_entries.user_id', $id))
            ->when($areaId, fn (Builder $query, int $id) => $query->whereIn('time_entries.user_id', User::query()->where('area_id', $id)->select('id')))
            ->groupBy('time_entries.user_id', 'time_entries.date')
            ->selectRaw('time_entries.user_id as user_id, time_entries.date as day, SUM(time_entries.duration_minutes) as minutes')
            ->get()
            ->groupBy('user_id');

        $rows = [];
        $columnTotals = array_fill(0, count($buckets), 0);

        foreach ($people->filter(fn (User $person): bool => $perDay->has($person->id)) as $person) {
            $cells = array_fill(0, count($buckets), 0);

            foreach ($perDay[$person->id] as $day) {
                $date = CarbonImmutable::parse($day->day)->toDateString();

                foreach ($buckets as $index => $bucket) {
                    if ($date >= $bucket['from'] && $date <= $bucket['to']) {
                        $cells[$index] += (int) $day->minutes;
                        $columnTotals[$index] += (int) $day->minutes;
                    }
                }
            }

            $rows[] = ['id' => $person->id, 'name' => $person->fullName(), 'minutes' => $cells, 'total' => array_sum($cells)];
        }

        return [
            'granularity' => $granularity,
            'buckets' => $buckets,
            'rows' => $rows,
            'column_totals' => $columnTotals,
            'grand_total' => array_sum($columnTotals),
            'people' => $people->map(fn (User $person): array => ['id' => $person->id, 'name' => $person->fullName()])->values()->all(),
            'areas' => $viewer->hasRole(Role::Boss, Role::Hr)
                ? Area::orderBy('name')->get(['id', 'name'])->map(fn (Area $area): array => ['id' => $area->id, 'name' => $area->name])->all()
                : [],
        ];
    }

    /**
     * @return list<array{label: string, from: string, to: string}>
     */
    private function buckets(string $from, string $to, string $granularity): array
    {
        $buckets = [];
        $start = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);

        for ($cursor = $start; $cursor <= $end;) {
            $last = match ($granularity) {
                'day' => $cursor,
                'week' => $cursor->endOfWeek(CarbonInterface::SUNDAY),
                default => $cursor->endOfMonth(),
            };
            $last = $last > $end ? $end : $last;

            $buckets[] = [
                'label' => match ($granularity) {
                    'day' => $cursor->format('d/m'),
                    'week' => $cursor->format('d/m').' – '.$last->format('d/m'),
                    default => $cursor->format('m/Y'),
                },
                'from' => $cursor->toDateString(),
                'to' => $last->toDateString(),
            ];

            $cursor = $last->addDay();
        }

        return $buckets;
    }
}
