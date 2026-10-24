<?php

declare(strict_types=1);

use App\Http\Middleware\TrackMcpUsage;
use App\Mcp\Servers\BClockServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

/*
| AI apps connect here: they sign in through OAuth (Passport) and then act as the person who authorized them.
*/
// Anyone can register an app, so that endpoint is throttled.
Route::middleware('throttle:20,1')->group(fn () => Mcp::oauthRoutes());

Mcp::web('/mcp/bclock', BClockServer::class)
    ->middleware(['auth:api', TrackMcpUsage::class]);
