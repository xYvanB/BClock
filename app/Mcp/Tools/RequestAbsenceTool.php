<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Actions\Absences\SaveAbsence;
use App\Enum\AbsenceType;
use App\Http\Requests\SaveAbsenceRequest;
use App\Mcp\Support\RunsAsUser;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Throwable;

#[Description('Requests a vacation (one or more whole days) or a leave (a few hours within one day) for the signed-in person. The request starts pending and is approved by the area head and/or HR as usual; the Boss\'s own absences are confirmed at once. Non-working days and overlaps are refused.')]
class RequestAbsenceTool extends Tool
{
    use RunsAsUser;

    public function __construct(private readonly SaveAbsence $action) {}

    /**
     * @throws Throwable
     */
    public function handle(Request $request): Response
    {
        return $this->actAs($request, function (User $user) use ($request): Response {
            $validated = $this->validatedRequest(SaveAbsenceRequest::class, $request->all(), $user);

            $absence = $this->action->handle($user, $validated->toDto());

            return Response::json([
                'id' => $absence->id,
                'type' => $absence->type->value,
                'period' => $absence->periodLabel(),
                'status' => $absence->status->value,
            ]);
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(AbsenceType::values())->description('"ferie" is a vacation (whole days), "permesso" is a leave (hours within one day).')->required(),
            'starts_on' => $schema->string()->description('First day, YYYY-MM-DD.')->required(),
            'ends_on' => $schema->string()->description('Last day, YYYY-MM-DD. For a leave it must equal starts_on.')->required(),
            'starts_time' => $schema->string()->description('Leave only: start time, HH:MM.'),
            'ends_time' => $schema->string()->description('Leave only: end time, HH:MM, after the start.'),
            'note' => $schema->string()->description('A note for who approves (optional).'),
        ];
    }
}
