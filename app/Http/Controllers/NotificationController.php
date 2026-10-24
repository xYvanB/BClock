<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Notifications\AppNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Full list of the person's notifications, with an "unread" filter.
     */
    public function index(Request $request): Response
    {
        $unreadOnly = $request->query('filter') === 'unread';

        $notifications = $request->user()->notifications()
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DatabaseNotification $notification): array => self::present($notification));

        return Inertia::render('notifications/index', [
            'list' => $notifications,
            'filter' => $unreadOnly ? 'unread' : 'all',
        ]);
    }

    /**
     * Marks a notification of the person (never of others) as read and opens the linked page.
     */
    public function read(Request $request, string $notification): RedirectResponse
    {
        $found = $request->user()->notifications()->findOrFail($notification);
        $found->markAsRead();

        $path = parse_url((string) ($found->data['url'] ?? ''), PHP_URL_PATH);

        return redirect($path ?: route('dashboard', absolute: false));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }

    /**
     * @return array{id: string, message: string, kind: string|null, actor_name: string|null, created_at: string, is_read: bool}
     */
    public static function present(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'message' => isset($notification->data['message_key'])
                ? AppNotification::render($notification->data['message_key'], $notification->data['params'] ?? [])
                : (string) ($notification->data['message'] ?? ''),
            'kind' => $notification->data['kind'] ?? null,
            'actor_name' => $notification->data['actor_name'] ?? null,
            'created_at' => $notification->created_at->toIso8601String(),
            'is_read' => $notification->read_at !== null,
        ];
    }
}
