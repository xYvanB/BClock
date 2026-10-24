<?php

declare(strict_types=1);

use App\Enum\AppLocale;
use App\Enum\Palette;
use App\Enum\TimeFormat;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the preferences page with the available options', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('preferences.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/preferences')
            ->where('options.palettes', Palette::values())
            ->where('options.time_formats', TimeFormat::values())
            ->where('options.locales', AppLocale::values()));
});

it('saves language, time format and palette', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('preferences.update'), ['locale' => 'en', 'time_format' => '12h', 'palette' => 'chiaro'])
        ->assertRedirect(route('preferences.edit'))
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->locale)->toBe(AppLocale::En)
        ->and($user->time_format)->toBe(TimeFormat::Hours12)
        ->and($user->palette)->toBe(Palette::Light);
});

it('rejects unknown values', function (array $payload, string $field): void {
    $this->actingAs(User::factory()->create())
        ->patch(route('preferences.update'), array_merge(['locale' => 'it', 'time_format' => '24h', 'palette' => 'scuro'], $payload))
        ->assertSessionHasErrors($field);
})->with([
    'locale' => [['locale' => 'fr'], 'locale'],
    'time format' => [['time_format' => '13h'], 'time_format'],
    'palette' => [['palette' => 'arcobaleno'], 'palette'],
]);

it('requires authentication', function (): void {
    $this->patch(route('preferences.update'), ['locale' => 'it', 'time_format' => '24h', 'palette' => 'scuro'])
        ->assertRedirect(route('login'));
});

it('applies the saved palette on the page markup before any script runs', function (): void {
    $user = User::factory()->create(['palette' => Palette::Light]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-tema="chiaro"', false);

    $dark = User::factory()->create(['palette' => Palette::Dark]);

    $this->actingAs($dark)
        ->get(route('dashboard'))
        ->assertSee('data-tema="scuro"', false)
        ->assertSee('class="dark"', false);
});

it('uses the dark palette by default for guests', function (): void {
    $this->get(route('login'))->assertSee('data-tema="scuro"', false);
});

it('sets the application locale from the user preference', function (): void {
    $user = User::factory()->create(['locale' => AppLocale::En]);

    $this->actingAs($user)->get(route('dashboard'));

    expect(app()->getLocale())->toBe('en');
});
