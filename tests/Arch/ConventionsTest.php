<?php

declare(strict_types=1);

arch('app code declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('actions are final and own the database transactions')
    ->expect('App\Actions')
    ->toBeFinal()
    ->toHaveMethod('handle');

arch('database transactions only open inside actions')
    ->expect('Illuminate\Support\Facades\DB')
    ->not->toBeUsedIn([
        'App\Http\Controllers',
        'App\Http\Requests',
        'App\Models',
        'App\Policies',
        'App\DTO',
    ]);

arch('dtos are final readonly classes')
    ->expect('App\DTO')
    ->toBeFinal()
    ->toBeReadonly();

arch('enums are backed enums')
    ->expect('App\Enum')
    ->toBeEnums();

arch('policies are final')
    ->expect('App\Policies')
    ->toBeFinal();
