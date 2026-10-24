<?php

declare(strict_types=1);

use App\Http\Controllers\Settings\CompanyCalendarController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\PreferencesController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\WorkloadSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('password.update');

    Route::get('settings/preferences', [PreferencesController::class, 'edit'])->name('preferences.edit');
    Route::patch('settings/preferences', [PreferencesController::class, 'update'])->name('preferences.update');

    Route::get('settings/company-calendar', [CompanyCalendarController::class, 'edit'])->name('company-calendar.edit');
    Route::put('settings/company-calendar', [CompanyCalendarController::class, 'update'])->name('company-calendar.update');

    Route::get('settings/workload', [WorkloadSettingsController::class, 'edit'])->name('workload.edit');
    Route::patch('settings/workload', [WorkloadSettingsController::class, 'update'])->name('workload.update');
});
