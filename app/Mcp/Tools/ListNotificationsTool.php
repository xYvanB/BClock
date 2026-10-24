<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Http\Controllers\NotificationController;
use App\Mcp\Support\RunsAsUser;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Notifications\DatabaseNotification;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Lists the signed-in person\'s latest notifications (20), unread ones first if asked.')]
#[IsReadOnly]
class ListNotificationsTool extends Tool
{
    use RunsAsUser;

    public function handle(Request $request): Response
    {
        return $this->actAs($request, function (User $user) use ($request): Response {
            $notifications = $user->notifications()
                ->when($request->get('unread_only'), fn ($query) => $query->whereNull('read_at'))
                ->limit(20)
                ->get()
                ->map(fn (DatabaseNotification $notification): array => NotificationController::present($notification));

            return Response::json(['unread_count' => $user->unreadNotifications()->count(), 'notifications' => $notifications]);
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'unread_only' => $schema->boolean()->description('Only the notifications not read yet.'),
        ];
    }
}
