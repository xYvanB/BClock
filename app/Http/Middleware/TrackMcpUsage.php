<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\AccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Remembers when an AI app last used its access, shown in Settings › Connected apps (updated at most once a minute).
 */
class TrackMcpUsage
{
    public function handle(Request $request, Closure $next): Response
    {
        $tokenId = $request->user('api')?->token()?->oauth_access_token_id;

        if (is_string($tokenId)) {
            AccessToken::query()
                ->whereKey($tokenId)
                ->where(fn ($query) => $query->whereNull('last_used_at')->orWhere('last_used_at', '<', now()->subMinute()))
                ->update(['last_used_at' => now()]);
        }

        return $next($request);
    }
}
