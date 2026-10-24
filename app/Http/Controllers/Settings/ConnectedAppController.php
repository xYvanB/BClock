<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\RevokeConnectedApp;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\Token;

class ConnectedAppController extends Controller
{
    /**
     * The AI apps the person authorized, one row per app, and the address to give them.
     */
    public function index(Request $request): Response
    {
        $apps = $request->user()->tokens()
            ->where('revoked', false)
            ->where(fn ($query) => $query->where('expires_at', '>', now())
                ->orWhereHas('refreshToken', fn ($refresh) => $refresh->where('revoked', false)->where('expires_at', '>', now())))
            ->with('client')
            ->get()
            ->groupBy('client_id')
            ->map(fn ($tokens): array => [
                'id' => (string) $tokens->first()->client_id,
                'name' => $tokens->first()->client?->name ?? __('App sconosciuta'),
                'connected_at' => $tokens->min('created_at')->toIso8601String(),
                'last_used_at' => $tokens->max(fn (Token $token): ?Carbon => $token->last_used_at)?->toIso8601String(),
            ])
            ->sortBy('name')
            ->values();

        return Inertia::render('settings/connected-apps', [
            'apps' => $apps,
            'server_url' => url('/mcp/bclock'),
        ]);
    }

    /**
     * Revokes the access of one app: it can no longer act on behalf of the person.
     */
    public function destroy(Request $request, string $client, RevokeConnectedApp $action): RedirectResponse
    {
        $action->handle($request->user(), $client);

        return to_route('connected-apps.index')->with('success', __('Accesso revocato.'));
    }
}
