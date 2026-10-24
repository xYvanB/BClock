<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Areas\CreateArea;
use App\Actions\Areas\UpdateArea;
use App\Http\Requests\SaveAreaRequest;
use App\Models\Area;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class AreaController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Area::class);

        return Inertia::render('areas/index', [
            'areas' => Area::query()
                ->withCount('users')
                ->orderBy('name')
                ->get(['id', 'name', 'description']),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function store(SaveAreaRequest $request, CreateArea $action): RedirectResponse
    {
        $action->handle($request->toDto());

        return to_route('areas.index')->with('success', 'Area creata.');
    }

    /**
     * @throws Throwable
     */
    public function update(SaveAreaRequest $request, Area $area, UpdateArea $action): RedirectResponse
    {
        $action->handle($area, $request->toDto());

        return to_route('areas.index')->with('success', 'Area aggiornata.');
    }
}
