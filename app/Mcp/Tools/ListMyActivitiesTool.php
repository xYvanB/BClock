<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enum\WorkStatus;
use App\Mcp\Support\RunsAsUser;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_my_activities')]
#[Description('Lists the tasks (activities) assigned to the signed-in person, with project, dates, planned and logged hours. Use it to find the activity id needed to log time or start a timer.')]
#[IsReadOnly]
class ListMyActivitiesTool extends Tool
{
    use RunsAsUser;

    public function handle(Request $request): Response
    {
        return $this->actAs($request, function (User $user) use ($request): Response {
            $status = $request->get('status');

            $activities = Activity::query()
                ->whereHas('assignees', fn ($query) => $query->where('users.id', $user->id))
                ->when($status, fn ($query) => $query->where('status', $status))
                ->when(! $status, fn ($query) => $query->whereNotIn('status', [WorkStatus::Done->value, WorkStatus::Archived->value]))
                ->with('project:id,name')
                ->withSum('timeEntries as used_minutes', 'duration_minutes')
                ->orderBy('due_on')
                ->limit(100)
                ->get()
                ->map(fn (Activity $activity): array => [
                    'id' => $activity->id,
                    'name' => $activity->name,
                    'project' => $activity->project->name,
                    'status' => $activity->status->value,
                    'starts_on' => $activity->starts_on->toDateString(),
                    'due_on' => $activity->due_on->toDateString(),
                    'planned_hours' => (float) $activity->planned_hours,
                    'logged_hours' => round(((int) $activity->used_minutes) / 60, 2),
                ]);

            return Response::json($activities);
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'status' => $schema->string()
                ->enum(array_column(WorkStatus::cases(), 'value'))
                ->description('Only tasks with this status. By default the open ones (not completed, not archived).'),
        ];
    }
}
