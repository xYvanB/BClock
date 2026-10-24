<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Allocations\MoveAllocation;
use App\Http\Requests\MoveAllocationRequest;
use App\Models\ActivityAllocation;
use Illuminate\Http\RedirectResponse;
use Throwable;

class ActivityAllocationController extends Controller
{
    /**
     * Sposta la quota di un giorno (calendario F9).
     *
     * @throws Throwable
     */
    public function move(MoveAllocationRequest $request, ActivityAllocation $allocation, MoveAllocation $action): RedirectResponse
    {
        $action->handle(
            $allocation,
            $request->toDate(),
            $request->boolean('extend_activity'),
            $request->boolean('extend_project'),
            $request->hours(),
        );

        return back()->with('success', 'Quota spostata.');
    }
}
