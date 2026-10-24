<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Timers\StartTimer;
use App\Actions\Timers\StopTimer;
use App\Http\Requests\StartTimerRequest;
use App\Models\Timer;
use Illuminate\Http\RedirectResponse;
use Throwable;

class TimerController extends Controller
{
    /**
     * @throws Throwable
     */
    public function store(StartTimerRequest $request, StartTimer $action): RedirectResponse
    {
        $action->handle($request->user(), $request->toDto());

        return back()->with('success', __('Timer avviato.'));
    }

    /**
     * @throws Throwable
     */
    public function stop(Timer $timer, StopTimer $action): RedirectResponse
    {
        $this->authorize('stop', $timer);

        $created = $action->handle($timer);

        return back()->with('success', match (true) {
            $created === null => __('Il timer era già stato fermato.'),
            $created === 0 => __('Timer fermato: durata inferiore a un minuto, nessuna registrazione creata.'),
            $created === 1 => __('Timer fermato: registrazione creata.'),
            default => __('Timer fermato: il timer attraversava la mezzanotte, create :count registrazioni (una per giorno). Controllale.', ['count' => $created]),
        });
    }
}
