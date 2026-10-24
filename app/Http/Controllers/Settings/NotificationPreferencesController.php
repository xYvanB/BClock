<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Notifications\UpdateNotificationPreferences;
use App\Enum\NotificationKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\NotificationPreferencesRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class NotificationPreferencesController extends Controller
{
    /**
     * The person's choices per notification type: in the app and by e-mail.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('settings/notifications', [
            'preferences' => collect(NotificationKind::cases())->map(fn (NotificationKind $kind): array => [
                'kind' => $kind->value,
                'label' => $kind->label(),
                'mandatory_in_app' => $kind->isMandatoryInApp(),
                'app' => $user->wantsNotification($kind, 'app'),
                'mail' => $user->wantsNotification($kind, 'mail'),
            ])->values(),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function update(NotificationPreferencesRequest $request, UpdateNotificationPreferences $action): RedirectResponse
    {
        $action->handle($request->user(), $request->preferences());

        return to_route('notification-preferences.edit')->with('success', __('Preferenze salvate.'));
    }
}
