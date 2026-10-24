<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Timer;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            // Timer attivi della persona (sempre visibili nella topbar); `server_now` corregge l'orologio del browser.
            'timers' => fn () => $request->user() === null ? [] : Timer::query()
                ->where('user_id', $request->user()->id)
                ->with('activity.project:id,name')
                ->orderBy('started_at')
                ->get()
                ->map(fn (Timer $timer) => [
                    'id' => $timer->id,
                    'activity_id' => $timer->activity_id,
                    'activity_name' => $timer->activity->name,
                    'project_name' => $timer->activity->project->name,
                    'description' => $timer->description,
                    'started_at' => $timer->started_at->toIso8601String(),
                ])
                ->values(),
            'timer_warning_minutes' => Timer::WARNING_AFTER_MINUTES,
            'server_now' => fn () => now()->toIso8601String(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
        ]);
    }
}
