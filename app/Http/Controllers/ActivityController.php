<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Activities\ArchiveActivity;
use App\Actions\Activities\CreateActivity;
use App\Actions\Activities\DeleteActivity;
use App\Actions\Activities\UpdateActivity;
use App\Enum\Role;
use App\Enum\WorkStatus;
use App\Http\Requests\SaveActivityRequest;
use App\Http\Resources\ActivityListResource;
use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ActivityController extends Controller
{
    /**
     * Lista attività: il Capo e l'HR le vedono tutte, il Capo area quelle con almeno un assegnatario della propria area.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Activity::class);

        $actor = $request->user();
        $isAreaHead = $actor->hasRole(Role::AreaHead);

        return Inertia::render('activities/index', [
            'activities' => ActivityListResource::collection(
                Activity::query()
                    ->with(['project.client:id,company_name', 'assignees:id,name,surname,area_id'])
                    ->withSum('timeEntries as used_minutes', 'duration_minutes')
                    ->when($isAreaHead, fn ($query) => $query->whereHas('assignees', fn ($q) => $q->where('users.area_id', $actor->area_id)))
                    ->orderBy('due_on')
                    ->orderBy('name')
                    ->get()
            )->resolve(),
            'projects' => Project::query()
                ->with('client:id,company_name')
                ->whereNot('status', WorkStatus::Archived->value)
                ->orderBy('name')
                ->get()
                ->map(fn (Project $project) => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'client_name' => $project->client->company_name,
                    'due_on' => $project->due_on->toDateString(),
                ]),
            'assignees' => User::query()
                ->when($isAreaHead, fn ($query) => $query->where('area_id', $actor->area_id))
                ->orderBy('surname')
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->fullName()]),
            'can_create' => $actor->can('create', Activity::class),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function store(SaveActivityRequest $request, CreateActivity $action): RedirectResponse
    {
        $action->handle($request->toDto());

        return back()->with('success', 'Attività creata.');
    }

    /**
     * @throws Throwable
     */
    public function update(SaveActivityRequest $request, Activity $activity, UpdateActivity $action): RedirectResponse
    {
        $action->handle($activity, $request->toDto());

        return back()->with('success', 'Attività aggiornata.');
    }

    /**
     * @throws Throwable
     */
    public function archive(Activity $activity, ArchiveActivity $action): RedirectResponse
    {
        $this->authorize('archive', $activity);

        $action->handle($activity);

        return back()->with('success', 'Attività archiviata.');
    }

    /**
     * @throws Throwable
     */
    public function destroy(Activity $activity, DeleteActivity $action): RedirectResponse
    {
        $this->authorize('delete', $activity);

        $action->handle($activity);

        return back()->with('success', 'Attività eliminata.');
    }
}
