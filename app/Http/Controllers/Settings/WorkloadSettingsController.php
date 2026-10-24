<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateWorkloadSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\WorkloadSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class WorkloadSettingsController extends Controller
{
    /**
     * Workload settings (Boss and HR only).
     */
    public function edit(): Response
    {
        $this->authorize('manage', Setting::class);

        return Inertia::render('settings/workload', [
            'tolerance_enabled' => Setting::loadToleranceEnabled(),
            'tolerance_hours' => Setting::configuredLoadToleranceHours(),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function update(WorkloadSettingsRequest $request, UpdateWorkloadSettings $action): RedirectResponse
    {
        $action->handle($request->toDto());

        return to_route('workload.edit')->with('success', 'Impostazioni salvate.');
    }
}
