<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Models\Activity;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ProjectHoursReport
{
    /**
     * Ore per progetto nel periodo: previste, utilizzate nel periodo e utilizzate in totale.
     * Con un progetto scelto aggiunge la suddivisione per attività e per persona.
     * Conta solo ciò che `$viewer` può vedere (vedi `TimeEntry::scopeVisibleTo`).
     *
     * @return array{
     *     total_hours: float,
     *     projects: list<array{id: int, name: string, client: string, planned_hours: float, period_hours: float, total_hours: float}>,
     *     selected: array{project_id: int, activities: list<array{id: int, name: string, planned_hours: float, hours: float}>, people: list<array{id: int, name: string, hours: float}>}|null,
     *     options: list<array{id: int, name: string}>
     * }
     */
    public function handle(User $viewer, string $from, string $to, ?int $projectId): array
    {
        $base = fn (): Builder => TimeEntry::query()
            ->visibleTo($viewer)
            ->join('activities', 'activities.id', '=', 'time_entries.activity_id');

        $inPeriod = fn (Builder $query): Builder => $query->whereBetween('time_entries.date', [$from, $to]);

        $period = $inPeriod($base())->groupBy('activities.project_id')
            ->selectRaw('activities.project_id as project_id, SUM(time_entries.duration_minutes) as minutes')
            ->pluck('minutes', 'project_id');

        $total = $base()->groupBy('activities.project_id')
            ->selectRaw('activities.project_id as project_id, SUM(time_entries.duration_minutes) as minutes')
            ->pluck('minutes', 'project_id');

        $projects = Project::query()->with('client:id,company_name')->whereIn('id', $total->keys())->orderBy('name')->get();

        $selected = null;
        if ($projectId !== null && $total->has($projectId)) {
            $selected = [
                'project_id' => $projectId,
                'activities' => $this->activities($base, $inPeriod, $projectId),
                'people' => $this->people($base, $inPeriod, $projectId),
            ];
        }

        return [
            'total_hours' => $this->hours((int) $period->sum()),
            'projects' => $projects
                ->filter(fn (Project $project): bool => $period->has($project->id) || $project->id === $projectId)
                ->map(fn (Project $project): array => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'client' => $project->client->company_name,
                    'planned_hours' => (float) $project->planned_hours,
                    'period_hours' => $this->hours((int) ($period[$project->id] ?? 0)),
                    'total_hours' => $this->hours((int) ($total[$project->id] ?? 0)),
                ])
                ->values()
                ->all(),
            'selected' => $selected,
            'options' => $projects->map(fn (Project $project): array => ['id' => $project->id, 'name' => $project->name])->values()->all(),
        ];
    }

    /**
     * @param  callable(): Builder  $base
     * @param  callable(Builder): Builder  $inPeriod
     * @return list<array{id: int, name: string, planned_hours: float, hours: float}>
     */
    private function activities(callable $base, callable $inPeriod, int $projectId): array
    {
        $minutes = $inPeriod($base())->where('activities.project_id', $projectId)->groupBy('activities.id')
            ->selectRaw('activities.id as activity_id, SUM(time_entries.duration_minutes) as minutes')
            ->pluck('minutes', 'activity_id');

        return Activity::query()->where('project_id', $projectId)->orderBy('name')->get()
            ->filter(fn ($activity): bool => $minutes->has($activity->id))
            ->map(fn ($activity): array => [
                'id' => $activity->id,
                'name' => $activity->name,
                'planned_hours' => (float) $activity->planned_hours,
                'hours' => $this->hours((int) $minutes[$activity->id]),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  callable(): Builder  $base
     * @param  callable(Builder): Builder  $inPeriod
     * @return list<array{id: int, name: string, hours: float}>
     */
    private function people(callable $base, callable $inPeriod, int $projectId): array
    {
        $minutes = $inPeriod($base())->where('activities.project_id', $projectId)->groupBy('time_entries.user_id')
            ->selectRaw('time_entries.user_id as user_id, SUM(time_entries.duration_minutes) as minutes')
            ->pluck('minutes', 'user_id');

        return User::query()->whereIn('id', $minutes->keys())->orderBy('surname')->orderBy('name')->get()
            ->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->fullName(), 'hours' => $this->hours((int) $minutes[$user->id])])
            ->values()
            ->all();
    }

    private function hours(int $minutes): float
    {
        return round($minutes / 60, 2);
    }
}
