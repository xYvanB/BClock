<?php

declare(strict_types=1);

use App\Models\Area;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

it('lets the boss list the areas', function (): void {
    Area::factory()->count(2)->create();

    $this->actingAs(User::factory()->boss()->create())
        ->get(route('areas.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('areas/index')
            ->has('areas', 2));
});

it('forbids everyone but the boss from the areas', function (User $user): void {
    $this->actingAs($user)->get(route('areas.index'))->assertForbidden();
    $this->actingAs($user)->post(route('areas.store'), ['name' => 'Design'])->assertForbidden();
})->with([
    'area head' => fn () => User::factory()->areaHead()->create(),
    'hr' => fn () => User::factory()->hr()->create(),
    'employee' => fn () => User::factory()->employee()->create(),
]);

it('creates an area', function (): void {
    $this->actingAs(User::factory()->boss()->create())
        ->post(route('areas.store'), ['name' => 'Design', 'description' => 'Team creativo'])
        ->assertRedirect(route('areas.index'));

    $this->assertDatabaseHas('areas', ['name' => 'Design', 'description' => 'Team creativo']);
});

it('updates an area keeping its own name valid', function (): void {
    $area = Area::factory()->create(['name' => 'Design']);

    $this->actingAs(User::factory()->boss()->create())
        ->put(route('areas.update', $area), ['name' => 'Design', 'description' => 'Nuova descrizione'])
        ->assertRedirect(route('areas.index'))
        ->assertSessionHasNoErrors();

    expect($area->refresh()->description)->toBe('Nuova descrizione');
});

it('validates the area name', function (array $payload, string $field): void {
    Area::factory()->create(['name' => 'Design']);

    $this->actingAs(User::factory()->boss()->create())
        ->post(route('areas.store'), $payload)
        ->assertSessionHasErrors($field);
})->with([
    'missing name' => [[], 'name'],
    'duplicate name' => [['name' => 'Design'], 'name'],
    'too long' => [['name' => str_repeat('a', 121)], 'name'],
]);

it('answers 403, not a validation error, when a non-boss sends an invalid payload', function (): void {
    $this->actingAs(User::factory()->employee()->create())
        ->post(route('areas.store'), ['name' => ''])
        ->assertForbidden();
});
