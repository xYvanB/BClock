<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Projects\ArchiveProject;
use App\Actions\Projects\CreateProject;
use App\Actions\Projects\DeleteProject;
use App\Actions\Projects\UpdateProject;
use App\Enum\Role;
use App\Enum\WorkStatus;
use App\Http\Requests\SaveProjectRequest;
use App\Http\Resources\ActivityListResource;
use App\Models\Activity;
use App\Models\Area;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\TemplateActivity;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProjectController extends Controller
{
    /**
     * "Hours" rows shown in the detail.
     */
    private const int TIME_ENTRIES_LIMIT = 100;

    /**
     * Project list. Archived ones are hidden, unless filtering by "archived" status or `archived=1`.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Project::class);

        $filters = $request->validate([
            'client_id' => ['nullable', 'integer'],
            'area_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(WorkStatus::class)],
            'archived' => ['nullable', 'boolean'],
        ]);

        $showArchived = ! empty($filters['archived']);

        return Inertia::render('projects/index', [
            'projects' => Project::query()
                ->with('client:id,company_name')
                ->withSum('timeEntries as used_minutes', 'duration_minutes')
                ->when($filters['client_id'] ?? null, fn ($query, $id) => $query->where('client_id', $id))
                ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
                ->when($filters['area_id'] ?? null, fn ($query, $areaId) => $query->whereHas(
                    'activities.assignees',
                    fn ($assignees) => $assignees->where('users.area_id', $areaId),
                ))
                ->when(
                    ! ($filters['status'] ?? null) && ! $showArchived,
                    fn ($query) => $query->whereNot('status', WorkStatus::Archived->value),
                )
                ->orderBy('due_on')
                ->orderBy('name')
                ->get()
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'client_name' => $project->client->company_name,
                    'status' => $project->status->value,
                    'starts_on' => $project->starts_on->toDateString(),
                    'due_on' => $project->due_on->toDateString(),
                    'planned_hours' => (float) $project->planned_hours,
                    'used_minutes' => (int) $project->used_minutes,
                ]),
            'filters' => [
                'client_id' => isset($filters['client_id']) ? (string) $filters['client_id'] : '',
                'area_id' => isset($filters['area_id']) ? (string) $filters['area_id'] : '',
                'status' => $filters['status'] ?? '',
                'archived' => $showArchived,
            ],
            'clients' => $this->clientOptions(),
            'users' => $this->userOptions(),
            'areas' => Area::query()->orderBy('name')->get(['id', 'name']),
            'templates' => $this->templateOptions($request->user()),
            'assignees' => $this->assigneeOptions($request->user()),
        ]);
    }

    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $actor = $request->user();
        $project->load(['client:id,company_name', 'template:id,name', 'members:id,name,surname', 'contacts']);
        $usedMinutes = (int) $project->timeEntries()->sum('duration_minutes');

        return Inertia::render('projects/show', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'template_name' => $project->template?->name,
                'description' => $project->description,
                'client_id' => $project->client_id,
                'client_name' => $project->client->company_name,
                'status' => $project->status->value,
                'starts_on' => $project->starts_on->toDateString(),
                'due_on' => $project->due_on->toDateString(),
                'planned_hours' => (float) $project->planned_hours,
                'used_minutes' => $usedMinutes,
                'members' => $project->members->map(fn (User $user) => ['id' => $user->id, 'name' => $user->fullName()])->values(),
                'contacts' => $project->contacts->map(fn (ClientContact $contact) => [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                ])->values(),
            ],
            'activities' => ActivityListResource::collection(
                $project->activities()
                    ->with(['project.client:id,company_name', 'assignees:id,name,surname,area_id', 'dependencies:id'])
                    ->withSum('timeEntries as used_minutes', 'duration_minutes')
                    ->orderBy('due_on')
                    ->orderBy('name')
                    ->get()
            )->resolve(),
            'time_entries' => TimeEntry::query()
                ->whereHas('activity', fn ($query) => $query->where('project_id', $project->id))
                ->when(
                    $actor->hasRole(Role::AreaHead),
                    fn ($query) => $query->whereHas('user', fn ($q) => $q->where('area_id', $actor->area_id)),
                )
                ->with(['user:id,name,surname', 'activity:id,name'])
                ->orderByDesc('date')
                ->orderByDesc('started_at')
                ->limit(self::TIME_ENTRIES_LIMIT)
                ->get()
                ->map(fn (TimeEntry $entry) => [
                    'id' => $entry->id,
                    'date' => $entry->date->toDateString(),
                    'user_name' => $entry->user->fullName(),
                    'activity_name' => $entry->activity->name,
                    'duration_minutes' => $entry->duration_minutes,
                ]),
            'clients' => $this->clientOptions(),
            'users' => $this->userOptions(),
            'assignees' => $this->assigneeOptions($actor),
            'can_create_activity' => $actor->can('create', Activity::class),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function store(SaveProjectRequest $request, CreateProject $action): RedirectResponse
    {
        $project = $action->handle($request->toDto());

        return to_route('projects.show', $project)->with('success', __('Progetto creato.'));
    }

    /**
     * @throws Throwable
     */
    public function update(SaveProjectRequest $request, Project $project, UpdateProject $action): RedirectResponse
    {
        $action->handle($project, $request->toDto());

        return back()->with('success', __('Progetto aggiornato.'));
    }

    /**
     * @throws Throwable
     */
    public function archive(Project $project, ArchiveProject $action): RedirectResponse
    {
        $this->authorize('archive', $project);

        $action->handle($project);

        return to_route('projects.index')->with('success', __('Progetto archiviato.'));
    }

    /**
     * @throws Throwable
     */
    public function destroy(Project $project, DeleteProject $action): RedirectResponse
    {
        $this->authorize('delete', $project);

        $action->handle($project);

        return to_route('projects.index')->with('success', __('Progetto eliminato.'));
    }

    /**
     * @return Collection<int, array{id: int, company_name: string, contacts: Collection<int, array{id: int, name: string}>}>
     */
    private function clientOptions(): Collection
    {
        return Client::query()
            ->with('contacts:id,client_id,name')
            ->orderBy('company_name')
            ->get()
            ->map(fn (Client $client) => [
                'id' => $client->id,
                'company_name' => $client->company_name,
                'contacts' => $client->contacts->map(fn (ClientContact $contact) => ['id' => $contact->id, 'name' => $contact->name])->values(),
            ]);
    }

    /**
     * @return Collection<int, array{id: int, name: string}>
     */
    private function userOptions(): Collection
    {
        return User::query()
            ->orderBy('surname')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->fullName()]);
    }

    /**
     * Templates with their activities, to choose from in the project drawer; empty for who cannot create activities (HR).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function templateOptions(User $actor): Collection
    {
        if (! $actor->can('create', Activity::class)) {
            return collect();
        }

        return ProjectTemplate::query()
            ->with('activities')
            ->orderBy('name')
            ->get()
            ->map(fn (ProjectTemplate $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'activities' => $template->activities->map(fn (TemplateActivity $activity) => [
                    'id' => $activity->id,
                    'name' => $activity->name,
                    'planned_hours' => (float) $activity->planned_hours,
                    'start_day' => $activity->start_day,
                    'end_day' => $activity->end_day,
                    'suggested_area_id' => $activity->suggested_area_id,
                    'suggested_role' => $activity->suggested_role?->value,
                ])->values(),
            ]);
    }

    /**
     * People assignable to activities: the area head only those of their own area.
     *
     * @return Collection<int, array{id: int, name: string, area_id: int|null}>
     */
    private function assigneeOptions(User $actor): Collection
    {
        return User::query()
            ->when($actor->hasRole(Role::AreaHead), fn ($query) => $query->where('area_id', $actor->area_id))
            ->orderBy('surname')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->fullName(), 'area_id' => $user->area_id]);
    }
}
