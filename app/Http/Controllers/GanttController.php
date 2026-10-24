<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Gantt\PlanActivityShift;
use App\Actions\Gantt\ShiftActivities;
use App\Enum\Role;
use App\Enum\WorkStatus;
use App\Http\Requests\ShiftActivityRequest;
use App\Models\Activity;
use App\Models\Area;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class GanttController extends Controller
{
    /**
     * Gantt (F10): le attività raggruppate per progetto, con dipendenze, linea di oggi e scadenza del progetto.
     * Il Capo e l'HR vedono tutto (l'HR in sola lettura), il Capo area le attività con persone della sua area,
     * il Dipendente le proprie. Si può spostare solo ciò che si può modificare (ActivityPolicy).
     */
    public function index(Request $request, PlanActivityShift $planner): Response
    {
        $user = $request->user();
        $zoom = in_array($request->query('zoom'), ['day', 'week', 'month'], true) ? $request->query('zoom') : 'week';
        $canFilterArea = $user->hasRole(Role::Boss, Role::Hr);

        $filters = [
            'project_id' => $request->integer('project_id') ?: null,
            'area_id' => $canFilterArea ? ($request->integer('area_id') ?: null) : null,
            'user_id' => $request->integer('user_id') ?: null,
            'status' => WorkStatus::tryFrom((string) $request->query('status'))?->value,
        ];

        $activities = $this->visibleActivities($user)
            ->with(['project:id,name,starts_on,due_on', 'assignees:id,name,surname,area_id', 'dependencies:id'])
            ->when($filters['project_id'], fn ($query, $id) => $query->where('project_id', $id))
            ->when($filters['area_id'], fn ($query, $id) => $query->whereHas('assignees', fn ($q) => $q->where('users.area_id', $id)))
            ->when($filters['user_id'], fn ($query, $id) => $query->whereHas('assignees', fn ($q) => $q->where('users.id', $id)))
            ->when(
                $filters['status'],
                fn ($query, $status) => $query->where('status', $status),
                fn ($query) => $query->whereNot('status', WorkStatus::Archived->value),
            )
            ->orderBy('starts_on')
            ->orderBy('name')
            ->get();

        $projects = $activities->pluck('project')->unique('id')->sortBy('name')->values();
        $today = CarbonImmutable::today(config('app.local_timezone'))->toDateString();

        $dates = $activities->flatMap(fn (Activity $activity) => [$activity->starts_on->toDateString(), $activity->due_on->toDateString()])
            ->merge($projects->flatMap(fn ($project) => [$project->starts_on->toDateString(), $project->due_on->toDateString()]))
            ->push($today);

        return Inertia::render('gantt/index', [
            'zoom' => $zoom,
            'filters' => $filters,
            'from' => CarbonImmutable::parse($dates->min())->subDays(3)->toDateString(),
            'to' => CarbonImmutable::parse($dates->max())->addDays(7)->toDateString(),
            'today' => $today,
            'projects' => $projects->map(fn ($project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'starts_on' => $project->starts_on->toDateString(),
                'due_on' => $project->due_on->toDateString(),
                'activities' => $activities->where('project_id', $project->id)->map(fn (Activity $activity): array => [
                    'id' => $activity->id,
                    'name' => $activity->name,
                    'status' => $activity->status->value,
                    'starts_on' => $activity->starts_on->toDateString(),
                    'due_on' => $activity->due_on->toDateString(),
                    'assignees' => $activity->assignees->map(fn (User $person): string => $person->fullName())->values(),
                    'dependency_ids' => $activity->dependencies->pluck('id')->values(),
                    'can_move' => $user->can('update', $activity),
                ])->values(),
            ])->values(),
            'filter_options' => [
                'projects' => $this->visibleActivities($user)->with('project:id,name')->get()->pluck('project')->unique('id')->sortBy('name')->values()
                    ->map(fn ($project): array => ['id' => $project->id, 'name' => $project->name]),
                'areas' => $canFilterArea ? Area::orderBy('name')->get(['id', 'name']) : [],
                'people' => $user->hasRole(Role::Employee) ? [] : User::query()
                    ->when($user->hasRole(Role::AreaHead), fn ($query) => $user->area_id !== null ? $query->where('area_id', $user->area_id) : $query->whereKey($user->id))
                    ->orderBy('surname')->orderBy('name')->get()
                    ->map(fn (User $person): array => ['id' => $person->id, 'name' => $person->fullName()]),
            ],
            'preview' => Inertia::optional(fn (): ?array => $this->preview($request, $planner)),
        ]);
    }

    /**
     * Applica uno spostamento o ridimensionamento (con la sua catena di dipendenze).
     *
     * @throws Throwable
     */
    public function shift(ShiftActivityRequest $request, Activity $activity, ShiftActivities $action): RedirectResponse
    {
        $action->handle(
            $request->user(),
            $activity,
            $request->string('mode')->toString(),
            $request->string('starts_on')->toString(),
            $request->string('due_on')->toString(),
            $request->boolean('confirm_projects'),
        );

        return to_route('gantt.index', $request->view())->with('success', 'Attività spostate.');
    }

    /**
     * Anteprima di uno spostamento (richiesta parziale `only: ['preview']`): cosa cambia e cosa lo blocca.
     *
     * @return array<string, mixed>|null
     */
    private function preview(Request $request, PlanActivityShift $planner): ?array
    {
        $validator = Validator::make($request->query(), [
            'preview_activity' => ['required', 'integer', 'exists:activities,id'],
            'preview_mode' => ['required', 'in:move,resize_start,resize_end'],
            'preview_start' => ['required', 'date_format:Y-m-d'],
            'preview_due' => ['required', 'date_format:Y-m-d'],
        ]);

        if ($validator->fails()) {
            return null;
        }

        $activity = Activity::with('project')->findOrFail($request->integer('preview_activity'));

        if (! $this->visibleActivities($request->user())->whereKey($activity->id)->exists()) {
            return null;
        }

        return [
            'activity_id' => $activity->id,
            'requested' => [
                'mode' => $request->string('preview_mode')->toString(),
                'start' => $request->string('preview_start')->toString(),
                'due' => $request->string('preview_due')->toString(),
            ],
        ] + $planner->handle(
            $request->user(),
            $activity,
            $request->string('preview_mode')->toString(),
            $request->string('preview_start')->toString(),
            $request->string('preview_due')->toString(),
        );
    }

    /**
     * @return Builder<Activity>
     */
    private function visibleActivities(User $user): Builder
    {
        return Activity::query()->when(
            ! $user->hasRole(Role::Boss, Role::Hr),
            fn ($query) => $query->whereHas('assignees', fn ($q) => $user->hasRole(Role::AreaHead) && $user->area_id !== null
                ? $q->where('users.area_id', $user->area_id)
                : $q->where('users.id', $user->id)),
        );
    }
}
