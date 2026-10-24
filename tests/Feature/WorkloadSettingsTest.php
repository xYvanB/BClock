<?php

declare(strict_types=1);

use App\Actions\Allocations\CalculateDailyLoad;
use App\Actions\Allocations\GenerateActivityAllocations;
use App\Enum\LoadLevel;
use App\Models\Activity;
use App\Models\Setting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Un'attività di un solo giorno (lunedì 2026-11-09) con le ore indicate, assegnata alla persona.
 */
function mondayLoad(User $user, string $hours): ?LoadLevel
{
    $activity = Activity::factory()->create(['starts_on' => '2026-11-09', 'due_on' => '2026-11-09', 'planned_hours' => $hours]);
    $activity->assignees()->attach($user);
    app(GenerateActivityAllocations::class)->handle($activity);

    return app(CalculateDailyLoad::class)->handle([$user->id], '2026-11-09', '2026-11-09')[$user->id]['2026-11-09']['level'];
}

it('is off by default, so only an exact match is full', function (): void {
    expect(Setting::loadToleranceEnabled())->toBeFalse()
        ->and(Setting::loadToleranceHours())->toBe(0.0)
        ->and(mondayLoad(User::factory()->employee()->create(), '7.75'))->toBe(LoadLevel::Under);
});

it('makes days close to the capacity full when the tolerance is on', function (string $hours, LoadLevel $expected): void {
    Setting::write(Setting::LOAD_TOLERANCE_ENABLED, '1');
    Setting::write(Setting::LOAD_TOLERANCE_HOURS, '0.5');

    expect(mondayLoad(User::factory()->employee()->create(), $hours))->toBe($expected);
})->with([
    'just under the margin' => ['7.00', LoadLevel::Under],
    'on the lower edge' => ['7.50', LoadLevel::Full],
    'a bit under' => ['7.75', LoadLevel::Full],
    'exact' => ['8.00', LoadLevel::Full],
    'a bit over' => ['8.25', LoadLevel::Full],
    'on the upper edge' => ['8.50', LoadLevel::Full],
    'just over the margin' => ['8.75', LoadLevel::Over],
]);

it('does not apply the tolerance to a day without available hours', function (): void {
    Setting::write(Setting::LOAD_TOLERANCE_ENABLED, '1');
    $user = User::factory()->employee()->create();
    $activity = Activity::factory()->create(['starts_on' => '2026-11-14', 'due_on' => '2026-11-14', 'planned_hours' => '0.30']);
    $activity->assignees()->attach($user);
    app(GenerateActivityAllocations::class)->handle($activity);

    $day = app(CalculateDailyLoad::class)->handle([$user->id], '2026-11-14', '2026-11-14')[$user->id]['2026-11-14'];

    expect($day['level'])->toBe(LoadLevel::Over);
});

it('keeps the saved margin when the tolerance is switched off', function (): void {
    $boss = User::factory()->boss()->create();

    $this->actingAs($boss)->patch(route('workload.update'), ['tolerance_enabled' => true, 'tolerance_hours' => '1'])
        ->assertRedirect(route('workload.edit'))->assertSessionHasNoErrors();
    expect(Setting::loadToleranceHours())->toBe(1.0);

    $this->actingAs($boss)->patch(route('workload.update'), ['tolerance_enabled' => false, 'tolerance_hours' => '1']);
    expect(Setting::loadToleranceHours())->toBe(0.0)
        ->and(Setting::configuredLoadToleranceHours())->toBe(1.0);
});

it('lets boss and hr see and change the settings, and nobody else', function (User $user, bool $allowed): void {
    $page = $this->actingAs($user)->get(route('workload.edit'));
    $save = $this->actingAs($user)->patch(route('workload.update'), ['tolerance_enabled' => true, 'tolerance_hours' => '0.5']);

    if ($allowed) {
        $page->assertOk()->assertInertia(fn (Assert $inertia) => $inertia
            ->component('settings/workload')
            ->where('tolerance_enabled', false)
            ->where('tolerance_hours', 0.5));
        $save->assertSessionHasNoErrors();
        expect(Setting::loadToleranceEnabled())->toBeTrue();
    } else {
        $page->assertForbidden();
        $save->assertForbidden();
        expect(Setting::loadToleranceEnabled())->toBeFalse();
    }
})->with([
    'boss' => [fn () => User::factory()->boss()->create(), true],
    'hr' => [fn () => User::factory()->hr()->create(), true],
    'area head' => [fn () => User::factory()->areaHead()->create(), false],
    'employee' => [fn () => User::factory()->employee()->create(), false],
]);

it('validates the tolerance', function (array $payload, string $field): void {
    $this->actingAs(User::factory()->hr()->create())
        ->patch(route('workload.update'), $payload)
        ->assertSessionHasErrors($field);
})->with([
    'missing flag' => [['tolerance_hours' => '0.5'], 'tolerance_enabled'],
    'missing hours' => [['tolerance_enabled' => true], 'tolerance_hours'],
    'zero' => [['tolerance_enabled' => true, 'tolerance_hours' => '0'], 'tolerance_hours'],
    'too large' => [['tolerance_enabled' => true, 'tolerance_hours' => '5'], 'tolerance_hours'],
    'not a number' => [['tolerance_enabled' => true, 'tolerance_hours' => 'poco'], 'tolerance_hours'],
]);

it('answers 403, not a validation error, when an employee sends invalid settings', function (): void {
    $this->actingAs(User::factory()->employee()->create())->patch(route('workload.update'), [])->assertForbidden();
});
