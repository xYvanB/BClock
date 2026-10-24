<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Users\AcceptInvitation;
use App\Enum\AppLocale;
use App\Enum\TimeFormat;
use App\Http\Requests\AcceptInvitationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * First access from an invitation (F2): the routes are protected by the signed link.
 */
class InvitationController extends Controller
{
    public function show(User $user): Response
    {
        abort_if($user->invited_at === null, 403, __('Invito già utilizzato.'));

        return Inertia::render('auth/accept-invitation', [
            'invitee' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'options' => ['locales' => AppLocale::values(), 'time_formats' => TimeFormat::values()],
        ]);
    }

    /**
     * @throws Throwable
     */
    public function accept(AcceptInvitationRequest $request, User $user, AcceptInvitation $action): RedirectResponse
    {
        abort_if($user->invited_at === null, 403, __('Invito già utilizzato.'));

        $action->handle($user, $request->toDto());

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('dashboard');
    }
}
