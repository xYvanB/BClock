<?php

declare(strict_types=1);

use App\Http\Controllers\AreaController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['guest', 'signed'])->group(function () {
    Route::get('invitation/{user}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('invitation/{user}', [InvitationController::class, 'accept'])->name('invitation.accept');
});

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');

    Route::get('areas', [AreaController::class, 'index'])->name('areas.index');
    Route::post('areas', [AreaController::class, 'store'])->name('areas.store');
    Route::put('areas/{area}', [AreaController::class, 'update'])->name('areas.update');

    Route::resource('time-entries', TimeEntryController::class)
        ->only(['index', 'store', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
