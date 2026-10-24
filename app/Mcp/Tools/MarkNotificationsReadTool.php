<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Support\RunsAsUser;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Marks all of the signed-in person\'s unread notifications as read.')]
#[IsIdempotent]
class MarkNotificationsReadTool extends Tool
{
    use RunsAsUser;

    public function handle(Request $request): Response
    {
        return $this->actAs($request, function (User $user): Response {
            $count = $user->unreadNotifications()->update(['read_at' => now()]);

            return Response::json(['marked_as_read' => $count]);
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
