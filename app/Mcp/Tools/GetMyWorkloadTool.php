<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Allocations\CalculateDailyLoad;
use App\Mcp\Support\RunsAsUser;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Shows the signed-in person\'s workload day by day: capacity (hours available after holidays and absences), assigned hours and the level (under capacity, full, overloaded).')]
#[IsReadOnly]
class GetMyWorkloadTool extends Tool
{
    use RunsAsUser;

    public function __construct(private readonly CalculateDailyLoad $load) {}

    public function handle(Request $request): Response
    {
        return $this->actAs($request, function (User $user) use ($request): Response {
            $data = $request->validate([
                'from' => ['nullable', 'date_format:Y-m-d'],
                'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            ]);

            $from = $data['from'] ?? CarbonImmutable::now()->startOfWeek()->toDateString();
            $to = $data['to'] ?? CarbonImmutable::parse($from)->addDays(13)->toDateString();

            if (CarbonImmutable::parse($from)->diffInDays($to) > 92) {
                return Response::error('The range can be at most 93 days.');
            }

            $days = collect($this->load->handle([$user->id], $from, $to)[$user->id] ?? [])
                ->map(fn (array $day, string $date): array => [
                    'date' => $date,
                    'capacity_hours' => $day['capacity'],
                    'assigned_hours' => $day['load'],
                    'level' => $day['level']?->value,
                ])
                ->values();

            return Response::json(['from' => $from, 'to' => $to, 'days' => $days]);
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('First day, YYYY-MM-DD. Default: Monday of this week.'),
            'to' => $schema->string()->description('Last day, YYYY-MM-DD. Default: two weeks from the first day. At most 93 days.'),
        ];
    }
}
