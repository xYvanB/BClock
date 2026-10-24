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
     * Moves the allocation of one day (calendar F9).
     *
     * @throws Throwable
     */
    public function move(MoveAllocationRequest $request, ActivityAllocation $allocation, MoveAllocation $action): RedirectResponse
    {
        $action->handle(
            $request->user(),
            $allocation,
            $request->toDate(),
            $request->boolean('extend_activity'),
            $request->boolean('extend_project'),
            $request->hours(),
        );

        return back()->with('success', __('Quota spostata.'));
    }
}
