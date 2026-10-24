<?php

declare(strict_types=1);

use App\Http\Controllers\AbsenceController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectTemplateController;
use App\Http\Controllers\TimeEntryController;
use App\Http\Controllers\TimerController;
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

    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::put('projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::post('projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');
    Route::delete('projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    Route::get('templates', [ProjectTemplateController::class, 'index'])->name('templates.index');
    Route::post('templates', [ProjectTemplateController::class, 'store'])->name('templates.store');
    Route::put('templates/{projectTemplate}', [ProjectTemplateController::class, 'update'])->name('templates.update');

    Route::get('activities', [ActivityController::class, 'index'])->name('activities.index');
    Route::post('activities', [ActivityController::class, 'store'])->name('activities.store');
    Route::put('activities/{activity}', [ActivityController::class, 'update'])->name('activities.update');
    Route::post('activities/{activity}/archive', [ActivityController::class, 'archive'])->name('activities.archive');
    Route::delete('activities/{activity}', [ActivityController::class, 'destroy'])->name('activities.destroy');

    Route::get('clients', [ClientController::class, 'index'])->name('clients.index');
    Route::post('clients', [ClientController::class, 'store'])->name('clients.store');
    Route::get('clients/{client}', [ClientController::class, 'show'])->name('clients.show');
    Route::put('clients/{client}', [ClientController::class, 'update'])->name('clients.update');

    Route::post('timers', [TimerController::class, 'store'])->name('timers.store');
    Route::post('timers/{timer}/stop', [TimerController::class, 'stop'])->name('timers.stop');

    Route::get('absences', [AbsenceController::class, 'index'])->name('absences.index');
    Route::post('absences', [AbsenceController::class, 'store'])->name('absences.store');
    Route::put('absences/{absence}', [AbsenceController::class, 'update'])->name('absences.update');
    Route::delete('absences/{absence}', [AbsenceController::class, 'destroy'])->name('absences.destroy');
    Route::post('absences/notifications/read', [AbsenceController::class, 'readNotifications'])->name('absences.notifications.read');
    Route::post('absences/{absence}/reject', [AbsenceController::class, 'reject'])->name('absences.reject');
    Route::post('absences/{absence}/confirm', [AbsenceController::class, 'confirm'])->name('absences.confirm');

    Route::post('holidays', [HolidayController::class, 'store'])->name('holidays.store');
    Route::put('holidays/{holiday}', [HolidayController::class, 'update'])->name('holidays.update');
    Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

    Route::resource('time-entries', TimeEntryController::class)
        ->only(['index', 'store', 'update', 'destroy']);
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
