<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Templates\CreateProjectTemplate;
use App\Actions\Templates\UpdateProjectTemplate;
use App\Http\Requests\SaveProjectTemplateRequest;
use App\Models\Area;
use App\Models\ProjectTemplate;
use App\Models\TemplateActivity;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ProjectTemplateController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', ProjectTemplate::class);

        return Inertia::render('templates/index', [
            'templates' => ProjectTemplate::query()
                ->with('activities')
                ->orderBy('name')
                ->get()
                ->map(fn (ProjectTemplate $template) => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'activities' => $template->activities->map(fn (TemplateActivity $activity) => [
                        'name' => $activity->name,
                        'description' => $activity->description,
                        'planned_hours' => (float) $activity->planned_hours,
                        'start_day' => $activity->start_day,
                        'end_day' => $activity->end_day,
                        'suggested_area_id' => $activity->suggested_area_id,
                        'suggested_role' => $activity->suggested_role?->value,
                    ])->values(),
                ]),
            'areas' => Area::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function store(SaveProjectTemplateRequest $request, CreateProjectTemplate $action): RedirectResponse
    {
        $action->handle($request->toDto());

        return to_route('templates.index')->with('success', __('Template creato.'));
    }

    /**
     * @throws Throwable
     */
    public function update(SaveProjectTemplateRequest $request, ProjectTemplate $projectTemplate, UpdateProjectTemplate $action): RedirectResponse
    {
        $action->handle($projectTemplate, $request->toDto());

        return to_route('templates.index')->with('success', __('Template aggiornato.'));
    }
}
