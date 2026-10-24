<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Timers\StartTimer;
use App\Http\Requests\StartTimerRequest;
use App\Mcp\Support\RunsAsUser;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Throwable;

#[Name('start_timer')]
#[Description('Starts a timer on a task for the signed-in person. One timer per task; stopping it turns it into time entries.')]
class StartTimerTool extends Tool
{
    use RunsAsUser;

    public function __construct(private readonly StartTimer $action) {}

    /**
     * @throws Throwable
     */
    public function handle(Request $request): Response
    {
        return $this->actAs($request, function (User $user) use ($request): Response {
            $validated = $this->validatedRequest(StartTimerRequest::class, $request->all(), $user);

            $timer = $this->action->handle($user, $validated->toDto());

            return Response::json(['id' => $timer->id, 'activity_id' => $timer->activity_id, 'started_at' => $timer->started_at->toIso8601String()]);
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'activity_id' => $schema->integer()->description('The id of the task (see list_my_activities).')->required(),
            'description' => $schema->string()->description('What is being done (optional).'),
        ];
    }
}
