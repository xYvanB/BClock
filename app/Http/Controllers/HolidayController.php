<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Holidays\DeleteHoliday;
use App\Actions\Holidays\SaveHoliday;
use App\Http\Requests\SaveHolidayRequest;
use App\Models\Holiday;
use Illuminate\Http\RedirectResponse;
use Throwable;

class HolidayController extends Controller
{
    /**
     * @throws Throwable
     */
    public function store(SaveHolidayRequest $request, SaveHoliday $action): RedirectResponse
    {
        $action->handle($request->toDto());

        return back()->with('success', 'Giorno non lavorativo aggiunto.');
    }

    /**
     * @throws Throwable
     */
    public function update(SaveHolidayRequest $request, Holiday $holiday, SaveHoliday $action): RedirectResponse
    {
        $action->handle($request->toDto(), $holiday);

        return back()->with('success', 'Giorno non lavorativo aggiornato.');
    }

    /**
     * @throws Throwable
     */
    public function destroy(Holiday $holiday, DeleteHoliday $action): RedirectResponse
    {
        $this->authorize('delete', $holiday);

        $action->handle($holiday);

        return back()->with('success', 'Giorno non lavorativo eliminato.');
    }
}
