<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Support\RunsAsUser;
use App\Models\Absence;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Lists the signed-in person\'s own absences (vacations and leaves) with their status, and the reason when rejected.')]
#[IsReadOnly]
class ListMyAbsencesTool extends Tool
{
    use RunsAsUser;

    public function handle(Request $request): Response
    {
        return $this->actAs($request, fn (User $user): Response => Response::json(
            Absence::query()
                ->where('user_id', $user->id)
                ->orderByDesc('starts_on')
                ->limit(100)
                ->get()
                ->map(fn (Absence $absence): array => [
                    'id' => $absence->id,
                    'type' => $absence->type->value,
                    'starts_on' => $absence->starts_on->toDateString(),
                    'ends_on' => $absence->ends_on->toDateString(),
                    'starts_time' => $absence->timeLabel($absence->starts_time),
                    'ends_time' => $absence->timeLabel($absence->ends_time),
                    'status' => $absence->status->value,
                    'approved_by_area_head' => $absence->isApprovedByAreaHead(),
                    'confirmed' => $absence->isApprovedByHr(),
                    'rejection_reason' => $absence->rejection_reason,
                ]),
        ));
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
