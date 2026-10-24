<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Support\RunsAsUser;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Lists the signed-in person\'s own time entries in a period (this week by default), with the total minutes.')]
#[IsReadOnly]
class ListMyTimeEntriesTool extends Tool
{
    use RunsAsUser;

    public function handle(Request $request): Response
    {
        return $this->actAs($request, function (User $user) use ($request): Response {
            $data = $request->validate([
                'from' => ['nullable', 'date_format:Y-m-d'],
                'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            ]);

            $from = $data['from'] ?? CarbonImmutable::now()->startOfWeek()->toDateString();
            $to = $data['to'] ?? CarbonImmutable::now()->endOfWeek()->toDateString();

            $entries = TimeEntry::query()
                ->where('user_id', $user->id)
                ->whereBetween('date', [$from, $to])
                ->with('activity.project:id,name')
                ->orderBy('date')
                ->orderBy('started_at')
                ->limit(500)
                ->get();

            return Response::json([
                'from' => $from,
                'to' => $to,
                'total_minutes' => (int) $entries->sum('duration_minutes'),
                'entries' => $entries->map(fn (TimeEntry $entry): array => [
                    'id' => $entry->id,
                    'date' => $entry->date->toDateString(),
                    'started_at' => substr((string) $entry->started_at, 0, 5),
                    'ended_at' => substr((string) $entry->ended_at, 0, 5),
                    'duration_minutes' => $entry->duration_minutes,
                    'activity_id' => $entry->activity_id,
                    'activity' => $entry->activity->name,
                    'project' => $entry->activity->project->name,
                    'description' => $entry->description,
                ])->values(),
            ]);
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('First day, YYYY-MM-DD. Default: Monday of this week.'),
            'to' => $schema->string()->description('Last day, YYYY-MM-DD. Default: Sunday of this week.'),
        ];
    }
}
