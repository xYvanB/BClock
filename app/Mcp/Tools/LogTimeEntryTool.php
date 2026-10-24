<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\TimeEntries\CreateTimeEntry;
use App\Http\Requests\SaveTimeEntryRequest;
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

#[Name('log_time_entry')]
#[Description('Logs hours worked by the signed-in person on a task: day, start and end time. Overlaps with other entries are allowed; going over the planned hours is only a warning. Archived tasks are refused.')]
class LogTimeEntryTool extends Tool
{
    use RunsAsUser;

    public function __construct(private readonly CreateTimeEntry $action) {}

    /**
     * @throws Throwable
     */
    public function handle(Request $request): Response
    {
        return $this->actAs($request, function (User $user) use ($request): Response {
            $validated = $this->validatedRequest(SaveTimeEntryRequest::class, $request->all(), $user);

            $entry = $this->action->handle($user, $validated->toDto());

            return Response::json([
                'id' => $entry->id,
                'date' => $entry->date->toDateString(),
                'started_at' => substr((string) $entry->started_at, 0, 5),
                'ended_at' => substr((string) $entry->ended_at, 0, 5),
                'duration_minutes' => $entry->duration_minutes,
            ]);
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'activity_id' => $schema->integer()->description('The id of the task (see list_my_activities).')->required(),
            'date' => $schema->string()->description('The day, YYYY-MM-DD.')->required(),
            'started_at' => $schema->string()->description('Start time, HH:MM (24 hours).')->required(),
            'ended_at' => $schema->string()->description('End time, HH:MM (24 hours), after the start, same day.')->required(),
            'description' => $schema->string()->description('What was done (optional).'),
        ];
    }
}
