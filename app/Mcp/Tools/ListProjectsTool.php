<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Enum\WorkStatus;
use App\Mcp\Support\RunsAsUser;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_projects')]
#[Description('Lists the projects with customer, dates and hours (planned and logged). Only for the Boss, area heads and HR; employees see their own tasks with list_my_activities instead.')]
#[IsReadOnly]
class ListProjectsTool extends Tool
{
    use RunsAsUser;

    public function handle(Request $request): Response
    {
        return $this->actAs($request, function (User $user): Response {
            if ($user->cannot('viewAny', Project::class)) {
                throw new AuthorizationException;
            }

            return Response::json(
                Project::query()
                    ->where('status', '!=', WorkStatus::Archived->value)
                    ->with('client:id,company_name')
                    ->withSum('timeEntries as used_minutes', 'duration_minutes')
                    ->orderBy('due_on')
                    ->limit(200)
                    ->get()
                    ->map(fn (Project $project): array => [
                        'id' => $project->id,
                        'name' => $project->name,
                        'customer' => $project->client->company_name,
                        'status' => $project->status->value,
                        'starts_on' => $project->starts_on->toDateString(),
                        'due_on' => $project->due_on->toDateString(),
                        'planned_hours' => (float) $project->planned_hours,
                        'logged_hours' => round(((int) $project->used_minutes) / 60, 2),
                    ]),
            );
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
