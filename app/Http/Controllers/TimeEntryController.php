<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\TimeEntries\CreateTimeEntry;
use App\Actions\TimeEntries\DeleteTimeEntry;
use App\Actions\TimeEntries\UpdateTimeEntry;
use App\Enum\WorkStatus;
use App\Http\Requests\SaveTimeEntryRequest;
use App\Models\Activity;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class TimeEntryController extends Controller
{
    /**
     * My hours: entries grouped by day, with filters on period, project and activity.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'project_id' => ['nullable', 'integer'],
            'activity_id' => ['nullable', 'integer'],
        ]);

        $from = $filters['from'] ?? CarbonImmutable::now()->startOfWeek()->toDateString();
        $to = $filters['to'] ?? CarbonImmutable::now()->endOfWeek()->toDateString();

        $entries = TimeEntry::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('date', [$from, $to])
            ->when($filters['activity_id'] ?? null, fn ($q, $id) => $q->where('activity_id', $id))
            ->when($filters['project_id'] ?? null, fn ($q, $id) => $q->whereHas('activity', fn ($a) => $a->where('project_id', $id)))
            ->with('activity.project.client:id,company_name')
            ->orderByDesc('date')
            ->orderBy('started_at')
            ->get();

        [$activityUsed, $projectUsed] = $this->usedMinutes($entries);

        $days = $entries->groupBy(fn (TimeEntry $entry) => $entry->date->toDateString())
            ->map(fn ($dayEntries, string $date) => [
                'date' => $date,
                'total_minutes' => (int) $dayEntries->sum('duration_minutes'),
                'entries' => $dayEntries->map(fn (TimeEntry $entry) => [
                    'id' => $entry->id,
                    'activity_id' => $entry->activity_id,
                    'activity_name' => $entry->activity->name,
                    'project_name' => $entry->activity->project->name,
                    'client_name' => $entry->activity->project->client->company_name,
                    'started_at' => substr((string) $entry->started_at, 0, 5),
                    'ended_at' => substr((string) $entry->ended_at, 0, 5),
                    'duration_minutes' => $entry->duration_minutes,
                    'description' => $entry->description,
                    'source' => $entry->source->value,
                    'activity_over_budget' => $this->isOver($activityUsed[$entry->activity_id] ?? 0, (float) $entry->activity->planned_hours),
                    'project_over_budget' => $this->isOver($projectUsed[$entry->activity->project_id] ?? 0, (float) $entry->activity->project->planned_hours),
                ])->values(),
            ])->values();

        return Inertia::render('time-entries/index', [
            'days' => $days,
            'filters' => ['from' => $from, 'to' => $to, 'project_id' => $filters['project_id'] ?? null, 'activity_id' => $filters['activity_id'] ?? null],
            'activities' => Activity::query()
                ->whereNot('status', WorkStatus::Archived->value)
                ->with('project:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'project_id'])
                ->map(fn (Activity $activity) => [
                    'id' => $activity->id,
                    'name' => $activity->name,
                    'project_id' => $activity->project_id,
                    'project_name' => $activity->project->name,
                ]),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function store(SaveTimeEntryRequest $request, CreateTimeEntry $action): RedirectResponse
    {
        $action->handle($request->user(), $request->toDto());

        return back()->with('success', 'Registrazione salvata.');
    }

    /**
     * @throws Throwable
     */
    public function update(SaveTimeEntryRequest $request, TimeEntry $timeEntry, UpdateTimeEntry $action): RedirectResponse
    {
        $action->handle($timeEntry, $request->toDto());

        return back()->with('success', 'Registrazione aggiornata.');
    }

    /**
     * @throws Throwable
     */
    public function destroy(TimeEntry $timeEntry, DeleteTimeEntry $action): RedirectResponse
    {
        $this->authorize('delete', $timeEntry);

        $action->handle($timeEntry);

        return back()->with('success', 'Registrazione eliminata.');
    }

    /**
     * Total minutes logged (by anyone) on the activities and projects touched by the list.
     *
     * @param  Collection<int, TimeEntry>  $entries
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    private function usedMinutes($entries): array
    {
        $activityIds = $entries->pluck('activity_id')->unique()->all();
        $projectIds = $entries->map(fn (TimeEntry $e) => $e->activity->project_id)->unique()->all();

        $activityUsed = TimeEntry::query()
            ->whereIn('activity_id', $activityIds)
            ->groupBy('activity_id')
            ->selectRaw('activity_id, SUM(duration_minutes) as minutes')
            ->pluck('minutes', 'activity_id')
            ->map(fn ($m) => (int) $m)
            ->all();

        $projectUsed = TimeEntry::query()
            ->join('activities', 'activities.id', '=', 'time_entries.activity_id')
            ->whereIn('activities.project_id', $projectIds)
            ->groupBy('activities.project_id')
            ->selectRaw('activities.project_id, SUM(time_entries.duration_minutes) as minutes')
            ->pluck('minutes', 'project_id')
            ->map(fn ($m) => (int) $m)
            ->all();

        return [$activityUsed, $projectUsed];
    }

    private function isOver(int $usedMinutes, float $plannedHours): bool
    {
        return $plannedHours > 0 && $usedMinutes > $plannedHours * 60;
    }
}
