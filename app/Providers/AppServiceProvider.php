<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AccessToken;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::useTokenModel(AccessToken::class);
        Passport::authorizationView('mcp.authorize');
        Passport::tokensExpireIn(Date::now()->addDays(30));
        Passport::refreshTokensExpireIn(Date::now()->addDays(90));
    }
}
