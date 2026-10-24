<?php

declare(strict_types=1);

use App\Models\Holiday;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the holidays to everyone, with management only for boss and hr', function (User $user, bool $canManage): void {
    Holiday::factory()->count(2)->create();

    $this->actingAs($user)
        ->get(route('absences.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('absences/index')
            ->has('holidays', 2)
            ->where('can_manage_holidays', $canManage));
})->with([
    'hr' => [fn () => User::factory()->hr()->create(), true],
    'boss' => [fn () => User::factory()->boss()->create(), true],
    'area head' => [fn () => User::factory()->areaHead()->create(), false],
    'employee' => [fn () => User::factory()->employee()->create(), false],
]);

it('lets hr and the boss create, update and delete a holiday', function (User $manager): void {
    $hr = $manager;

    $this->actingAs($hr)
        ->from(route('company-calendar.edit'))
        ->post(route('holidays.store'), ['date' => '2026-12-25', 'name' => 'Natale'])
        ->assertRedirect(route('company-calendar.edit'))
        ->assertSessionHasNoErrors();

    $holiday = Holiday::firstOrFail();
    expect($holiday->date->toDateString())->toBe('2026-12-25');

    $this->actingAs($hr)
        ->put(route('holidays.update', $holiday), ['date' => '2026-12-25', 'name' => 'Natale di Cristo'])
        ->assertSessionHasNoErrors();
    expect($holiday->refresh()->name)->toBe('Natale di Cristo');

    $this->actingAs($hr)->delete(route('holidays.destroy', $holiday))->assertSessionHasNoErrors();
    $this->assertDatabaseCount('holidays', 0);
})->with([
    'hr' => fn () => User::factory()->hr()->create(),
    'boss' => fn () => User::factory()->boss()->create(),
]);

it('forbids everyone but boss and hr from managing holidays', function (User $user): void {
    $holiday = Holiday::factory()->create(['date' => '2026-12-25']);

    $this->actingAs($user)->post(route('holidays.store'), ['date' => '2026-12-26', 'name' => 'Santo Stefano'])->assertForbidden();
    $this->actingAs($user)->put(route('holidays.update', $holiday), ['date' => '2026-12-25', 'name' => 'Altro'])->assertForbidden();
    $this->actingAs($user)->delete(route('holidays.destroy', $holiday))->assertForbidden();

    $this->assertDatabaseCount('holidays', 1);
})->with([
    'area head' => fn () => User::factory()->areaHead()->create(),
    'employee' => fn () => User::factory()->employee()->create(),
]);

it('answers 403, not a validation error, when a non-hr sends an invalid holiday', function (): void {
    $this->actingAs(User::factory()->employee()->create())
        ->post(route('holidays.store'), [])
        ->assertForbidden();
});

it('validates the holiday', function (array $payload, string $field): void {
    Holiday::factory()->create(['date' => '2026-12-25']);

    $this->actingAs(User::factory()->hr()->create())
        ->post(route('holidays.store'), $payload)
        ->assertSessionHasErrors($field);
})->with([
    'missing date' => [['name' => 'Natale'], 'date'],
    'malformed date' => [['date' => '25/12/2026', 'name' => 'Natale'], 'date'],
    'duplicate date' => [['date' => '2026-12-25', 'name' => 'Natale bis'], 'date'],
    'missing name' => [['date' => '2026-12-26'], 'name'],
]);

it('keeps the date of a holiday valid when updating it without changing the date', function (): void {
    $holiday = Holiday::factory()->create(['date' => '2026-12-25']);

    $this->actingAs(User::factory()->hr()->create())
        ->put(route('holidays.update', $holiday), ['date' => '2026-12-25', 'name' => 'Nuovo nome'])
        ->assertSessionHasNoErrors();
});
