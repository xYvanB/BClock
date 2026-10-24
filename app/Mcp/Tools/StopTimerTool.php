<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Timers\StopTimer;
use App\Mcp\Support\RunsAsUser;
use App\Models\Timer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Throwable;

#[Description('Stops one of the signed-in person\'s running timers and turns it into time entries (one per day if it crossed midnight). Without a timer id it stops the only running timer, and asks which one if there are several.')]
class StopTimerTool extends Tool
{
    use RunsAsUser;

    public function __construct(private readonly StopTimer $action) {}

    /**
     * @throws Throwable
     */
    public function handle(Request $request): Response
    {
        return $this->actAs($request, function (User $user) use ($request): Response {
            $timers = Timer::query()->where('user_id', $user->id)->with('activity:id,name')->get();

            $timer = $request->get('timer_id') !== null
                ? $timers->firstWhere('id', (int) $request->get('timer_id'))
                : ($timers->count() === 1 ? $timers->first() : null);

            if ($timer === null) {
                return Response::error($timers->isEmpty()
                    ? __('Nessun timer attivo.')
                    : 'Several timers are running, say which one: '.$timers->map(fn (Timer $running): string => "{$running->id} ({$running->activity->name})")->implode(', ').'.');
            }

            if ($user->cannot('stop', $timer)) {
                throw new AuthorizationException;
            }

            $created = $this->action->handle($timer);

            return Response::json(['timer_id' => $timer->id, 'time_entries_created' => $created ?? 0, 'already_stopped' => $created === null]);
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'timer_id' => $schema->integer()->description('The id of the timer; optional when only one is running.'),
        ];
    }
}
