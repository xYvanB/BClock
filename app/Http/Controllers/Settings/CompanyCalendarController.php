<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdateWorkingWeekdays;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\WorkingWeekdaysRequest;
use App\Models\Holiday;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class CompanyCalendarController extends Controller
{
    /**
     * Company calendar (Boss and HR only): working weekdays, holidays and extra closures.
     */
    public function edit(): Response
    {
        $this->authorize('manage', Setting::class);

        return Inertia::render('settings/company-calendar', [
            'weekdays' => Setting::workingWeekdays(),
            'closures' => Holiday::query()->orderBy('date')->get()->map(fn (Holiday $holiday): array => [
                'id' => $holiday->id,
                'date' => $holiday->date->toDateString(),
                'name' => $holiday->name,
                'kind' => $holiday->kind->value,
            ]),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function update(WorkingWeekdaysRequest $request, UpdateWorkingWeekdays $action): RedirectResponse
    {
        $action->handle($request->weekdays());

        return to_route('company-calendar.edit')->with('success', __('Giorni lavorativi salvati.'));
    }
}
