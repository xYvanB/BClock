<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Settings\UpdatePreferences;
use App\Enum\AppLocale;
use App\Enum\Palette;
use App\Enum\TimeFormat;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PreferencesRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PreferencesController extends Controller
{
    /**
     * Pagina delle preferenze personali (F14).
     */
    public function edit(): Response
    {
        return Inertia::render('settings/preferences', [
            'options' => [
                'locales' => AppLocale::values(),
                'time_formats' => TimeFormat::values(),
                'palettes' => Palette::values(),
            ],
        ]);
    }

    /**
     * @throws Throwable
     */
    public function update(PreferencesRequest $request, UpdatePreferences $action): RedirectResponse
    {
        $action->handle($request->user(), $request->toDto());

        return to_route('preferences.edit')->with('success', 'Preferenze salvate.');
    }
}
