<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Allocations\CalculateDailyLoad;
use App\Enum\AbsenceStatus;
use App\Enum\Role;
use App\Enum\WorkStatus;
use App\Models\Absence;
use App\Models\ActivityAllocation;
use App\Models\Area;
use App\Models\Holiday;
use App\Models\Setting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    /**
     * Workload calendar, per person and per day, in weekly or monthly view.
     * The Boss and HR see everyone (HR read-only), the area head their area, the Employee only themselves.
     */
    public function index(Request $request, CalculateDailyLoad $dailyLoad): Response
    {
        $user = $request->user();
        $view = $request->query('view') === 'month' ? 'month' : 'week';
        $anchor = $this->anchorDate($request->query('date'));

        $start = $view === 'month' ? $anchor->startOfMonth() : $anchor->startOfWeek(CarbonInterface::MONDAY);
        $end = $view === 'month' ? $anchor->endOfMonth() : $anchor->endOfWeek(CarbonInterface::SUNDAY);
        $from = $start->toDateString();
        $to = $end->toDateString();

        $canFilterArea = $user->hasRole(Role::Boss, Role::Hr);
        $areaId = $canFilterArea && $request->integer('area_id') > 0 ? $request->integer('area_id') : null;

        $people = $this->visiblePeople($user, $areaId)->get();
        $userIds = $people->pluck('id')->all();

        $load = $userIds === [] ? [] : $dailyLoad->handle($userIds, $from, $to);
        $items = $this->itemsByUserAndDay($user, $userIds, $from, $to);
        $absences = $this->absencesByUserAndDay($userIds, $from, $to);

        $holidays = Holiday::query()
            ->whereBetween('date', [$from, $to])
            ->get()
            ->mapWithKeys(fn (Holiday $holiday): array => [$holiday->date->toDateString() => $holiday->name]);

        $today = $this->today()->toDateString();
        $weekdays = Setting::workingWeekdays();
        $days = collect(CarbonPeriod::create($start, $end))->map(fn ($day): array => [
            'date' => $day->toDateString(),
            'is_off' => ! in_array($day->dayOfWeekIso, $weekdays, true),
            'holiday' => $holidays->get($day->toDateString()),
            'is_today' => $day->toDateString() === $today,
        ])->values();

        return Inertia::render('calendar/index', [
            'view' => $view,
            'date' => $anchor->toDateString(),
            'from' => $from,
            'to' => $to,
            'previous' => ($view === 'month' ? $anchor->subMonthNoOverflow() : $anchor->subWeek())->toDateString(),
            'next' => ($view === 'month' ? $anchor->addMonthNoOverflow() : $anchor->addWeek())->toDateString(),
            'area_id' => $areaId,
            'areas' => $canFilterArea ? Area::orderBy('name')->get(['id', 'name']) : [],
            'days' => $days,
            'people' => $people->map(fn (User $person): array => [
                'id' => $person->id,
                'name' => $person->fullName(),
                'area_name' => $person->area?->name,
                'is_self' => $person->id === $user->id,
                'days' => collect($load[$person->id] ?? [])->map(fn (array $cell, string $day): array => [
                    'capacity' => $cell['capacity'],
                    'load' => $cell['load'],
                    'level' => $cell['level']?->value,
                    'absence' => $absences[$person->id][$day] ?? null,
                    'items' => $items[$person->id][$day] ?? [],
                ]),
            ])->values(),
        ]);
    }

    /**
     * @return Builder<User>
     */
    private function visiblePeople(User $user, ?int $areaId): Builder
    {
        $query = User::query()->with('area:id,name')->orderBy('surname')->orderBy('name');

        return match (true) {
            $user->hasRole(Role::Boss, Role::Hr) => $query->when($areaId, fn ($q) => $q->where('area_id', $areaId)),
            $user->hasRole(Role::AreaHead) && $user->area_id !== null => $query->where(fn ($q) => $q->where('area_id', $user->area_id)->orWhere('id', $user->id)),
            default => $query->whereKey($user->id),
        };
    }

    /**
     * Allocations of open activities, per person and day (those at 0 hours, left by a move, are not shown).
     * `can_move` says whether the viewer can move them, that is whether they can edit the activity.
     *
     * @param  list<int>  $userIds
     * @return array<int, array<string, list<array<string, mixed>>>>
     */
    private function itemsByUserAndDay(User $viewer, array $userIds, string $from, string $to): array
    {
        $items = [];
        $canMove = [];

        ActivityAllocation::query()
            ->with(['activity.project:id,name,due_on', 'activity.assignees:id,area_id'])
            ->where('hours', '>', 0)
            ->whereIn('user_id', $userIds)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->whereHas('activity', fn ($query) => $query->whereNotIn('status', [WorkStatus::Done, WorkStatus::Archived]))
            ->orderBy('date')
            ->get()
            ->each(function (ActivityAllocation $allocation) use (&$items, $viewer, &$canMove): void {
                $canMove[$allocation->activity_id] ??= $viewer->can('update', $allocation->activity);

                $items[$allocation->user_id][$allocation->date->toDateString()][] = [
                    'allocation_id' => $allocation->id,
                    'can_move' => $canMove[$allocation->activity_id],
                    'activity_starts_on' => $allocation->activity->starts_on->toDateString(),
                    'activity_due_on' => $allocation->activity->due_on->toDateString(),
                    'project_due_on' => $allocation->activity->project->due_on->toDateString(),
                    'activity_id' => $allocation->activity_id,
                    'activity' => $allocation->activity->name,
                    'project' => $allocation->activity->project->name,
                    'hours' => (float) $allocation->hours,
                ];
            });

        return $items;
    }

    /**
     * Confirmed absences, per person and day: "vacation" or "leave".
     *
     * @param  list<int>  $userIds
     * @return array<int, array<string, string>>
     */
    private function absencesByUserAndDay(array $userIds, string $from, string $to): array
    {
        $result = [];

        Absence::query()
            ->whereIn('user_id', $userIds)
            ->where('status', AbsenceStatus::Confirmed)
            ->whereDate('starts_on', '<=', $to)
            ->whereDate('ends_on', '>=', $from)
            ->get()
            ->each(function (Absence $absence) use (&$result, $from, $to): void {
                $first = max($absence->starts_on->toDateString(), $from);
                $last = min($absence->ends_on->toDateString(), $to);

                foreach (CarbonPeriod::create($first, $last) as $day) {
                    $result[$absence->user_id][$day->toDateString()] = $absence->type->value;
                }
            });

        return $result;
    }

    private function anchorDate(mixed $value): CarbonImmutable
    {
        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

            // a nonexistent date (e.g. 2026-02-31) rolls over to the next month: in that case fall back to today
            if ($date !== false && $date->toDateString() === $value) {
                return $date;
            }
        }

        return $this->today();
    }

    /**
     * Today in the company's local timezone (the application works in UTC).
     */
    private function today(): CarbonImmutable
    {
        return CarbonImmutable::today(config('app.local_timezone'));
    }
}
