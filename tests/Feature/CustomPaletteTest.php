<?php

declare(strict_types=1);

use App\Enum\Palette;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, string>  $colors
 * @return array<string, mixed>
 */
function preferencesPayload(array $colors = [], string $palette = 'personalizzata'): array
{
    return ['locale' => 'it', 'time_format' => '24h', 'palette' => $palette] + ($colors === [] ? [] : ['custom_colors' => $colors]);
}

it('saves the colours chosen for the custom palette', function (): void {
    $user = User::factory()->employee()->create();
    $colors = ['bg' => '#101820', 'surface' => '#1b2735', 'text' => '#f2f2f2', 'border' => '#44556b', 'primary' => '#ff7a00', 'accent' => '#ffd166', 'danger' => '#ff5d5d', 'success' => '#4cd97b'];

    $this->actingAs($user)->patch(route('preferences.update'), preferencesPayload($colors))->assertSessionHasNoErrors();

    expect($user->refresh()->palette)->toBe(Palette::Custom)
        ->and($user->custom_colors)->toEqualCanonicalizing($colors);
});

it('lowercases the colours and keeps them when the palette is changed', function (): void {
    $user = User::factory()->employee()->create();
    $this->actingAs($user)->patch(route('preferences.update'), preferencesPayload(['primary' => '#FF7A00']));
    $this->actingAs($user)->patch(route('preferences.update'), preferencesPayload([], 'chiaro'))->assertSessionHasNoErrors();

    expect($user->refresh()->palette)->toBe(Palette::Light)
        ->and($user->custom_colors['primary'])->toBe('#ff7a00');
});

it('refuses colours that are not six-digit hex codes, or that are unknown', function (array $colors, string $field): void {
    $this->actingAs(User::factory()->employee()->create())
        ->patch(route('preferences.update'), preferencesPayload($colors))
        ->assertSessionHasErrors($field);
})->with([
    'a name' => [['primary' => 'red'], 'custom_colors.primary'],
    'three digits' => [['bg' => '#fff'], 'custom_colors.bg'],
    'no hash' => [['text' => 'ffffff'], 'custom_colors.text'],
    'css injection' => [['bg' => '#000000;position:fixed'], 'custom_colors.bg'],
    'an unknown key' => [['sfondo' => '#000000'], 'custom_colors'],
]);

it('renders the saved colours on the page, only for the custom palette', function (): void {
    $user = User::factory()->employee()->create();
    $this->actingAs($user)->patch(route('preferences.update'), preferencesPayload(['bg' => '#101820', 'primary' => '#ff7a00']));

    $this->actingAs($user->refresh())->get(route('dashboard'))
        ->assertSee('data-tema="personalizzata"', false)
        ->assertSee('--t-bg:#101820;', false)
        ->assertSee('--t-primary:#ff7a00;', false)
        ->assertSee('--t-text:#c8c8d8;', false)
        ->assertSee('--t-on-primary:#0a0a0f;', false);

    $this->actingAs($user)->patch(route('preferences.update'), preferencesPayload([], 'scuro'));
    $this->actingAs($user->refresh())->get(route('dashboard'))->assertDontSee('--t-primary:#ff7a00', false);
});

it('picks a readable text colour for a dark primary', function (): void {
    expect(Palette::Custom->customStyle(['primary' => '#1a1a80']))->toContain('--t-on-primary:#ffffff;')
        ->and(Palette::Custom->customStyle(['primary' => '#ffe28a']))->toContain('--t-on-primary:#0a0a0f;')
        ->and(Palette::Dark->customStyle(['primary' => '#1a1a80']))->toBe('');
});

it('hands the defaults and the saved colours to the preferences page', function (): void {
    $user = User::factory()->employee()->create();
    $this->actingAs($user)->patch(route('preferences.update'), preferencesPayload(['primary' => '#ff7a00']));

    $this->actingAs($user->refresh())->get(route('preferences.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('options.custom_defaults.primary', '#c9a84c')
            ->where('auth.user.custom_colors.primary', '#ff7a00'));
});
