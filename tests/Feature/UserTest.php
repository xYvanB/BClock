<?php

declare(strict_types=1);

use App\Enum\Role;
use App\Models\Area;
use App\Models\User;
use App\Notifications\UserInvited;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

function validUser(array $overrides = []): array
{
    return array_merge([
        'name' => 'Giulia',
        'surname' => 'Verdi',
        'email' => 'giulia.verdi@azienda.it',
        'role' => 'dipendente',
        'area_id' => null,
        'daily_capacity' => '8',
    ], $overrides);
}

it('forbids employees from the users section', function (): void {
    $employee = User::factory()->employee()->create();

    $this->actingAs($employee)->get(route('users.index'))->assertForbidden();
    $this->actingAs($employee)->post(route('users.store'), validUser())->assertForbidden();
});

it('shows every user to the boss and to hr', function (User $viewer): void {
    User::factory()->count(3)->create();

    $this->actingAs($viewer)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('users/index')
            ->has('users', User::count()));
})->with([
    'boss' => fn () => User::factory()->boss()->create(),
    'hr' => fn () => User::factory()->hr()->create(),
]);

it('limits the area head to their own area', function (): void {
    $design = Area::factory()->create();
    $dev = Area::factory()->create();
    $head = User::factory()->areaHead($design)->create();
    User::factory()->employee($design)->create();
    User::factory()->employee($dev)->create();

    $this->actingAs($head)
        ->get(route('users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users', 2)
            ->where('fixed_area_id', $design->id)
            ->where('assignable_roles', ['dipendente']));
});

it('invites a user: creates them as pending and sends the email', function (): void {
    Notification::fake();

    $this->actingAs(User::factory()->boss()->create())
        ->post(route('users.store'), validUser(['role' => 'hr', 'daily_capacity' => '6.5']))
        ->assertRedirect(route('users.index'))
        ->assertSessionHasNoErrors();

    $invited = User::where('email', 'giulia.verdi@azienda.it')->sole();
    expect($invited->role)->toBe(Role::Hr)
        ->and($invited->invited_at)->not->toBeNull()
        ->and((float) $invited->daily_capacity)->toBe(6.5);

    Notification::assertSentTo($invited, UserInvited::class);
});

it('forces the area of users invited by an area head and only allows employees', function (): void {
    Notification::fake();
    $design = Area::factory()->create();
    $dev = Area::factory()->create();
    $head = User::factory()->areaHead($design)->create();

    $this->actingAs($head)
        ->post(route('users.store'), validUser(['area_id' => $dev->id]))
        ->assertSessionHasNoErrors();

    expect(User::where('email', 'giulia.verdi@azienda.it')->sole()->area_id)->toBe($design->id);

    $this->actingAs($head)
        ->post(route('users.store'), validUser(['email' => 'altro@azienda.it', 'role' => 'capo']))
        ->assertSessionHasErrors('role');
});

it('validates the invite form', function (array $overrides, string $field): void {
    User::factory()->create(['email' => 'esiste@azienda.it']);

    $this->actingAs(User::factory()->boss()->create())
        ->post(route('users.store'), validUser($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'bad email' => [['email' => 'non-una-email'], 'email'],
    'duplicate email' => [['email' => 'esiste@azienda.it'], 'email'],
    'unknown role' => [['role' => 're'], 'role'],
    'area head without area' => [['role' => 'capo_area', 'area_id' => null], 'area_id'],
    'capacity too high' => [['daily_capacity' => '30'], 'daily_capacity'],
]);

it('edits a user keeping their own email valid', function (): void {
    $user = User::factory()->employee()->create(['email' => 'mario@azienda.it']);

    $this->actingAs(User::factory()->hr()->create())
        ->put(route('users.update', $user), validUser(['email' => 'mario@azienda.it', 'name' => 'Mario']))
        ->assertRedirect(route('users.index'))
        ->assertSessionHasNoErrors();

    expect($user->refresh()->name)->toBe('Mario');
});

it('does not let an area head edit users of another area', function (): void {
    $design = Area::factory()->create();
    $other = User::factory()->employee(Area::factory()->create())->create();

    $this->actingAs(User::factory()->areaHead($design)->create())
        ->put(route('users.update', $other), validUser(['email' => $other->email]))
        ->assertForbidden();
});

it('does not let hr assign the boss role', function (): void {
    Notification::fake();

    $this->actingAs(User::factory()->hr()->create())
        ->post(route('users.store'), validUser(['role' => 'capo']))
        ->assertSessionHasErrors('role');

    expect(User::where('email', 'giulia.verdi@azienda.it')->exists())->toBeFalse();
});

it('does not let hr edit the boss', function (): void {
    $boss = User::factory()->boss()->create();

    $this->actingAs(User::factory()->hr()->create())
        ->put(route('users.update', $boss), validUser(['email' => $boss->email, 'role' => 'dipendente']))
        ->assertForbidden();

    expect($boss->refresh()->role)->toBe(Role::Boss);
});

it('does not let anyone change their own role', function (User $actor, string $newRole): void {
    $originalRole = $actor->role;

    $this->actingAs($actor)
        ->put(route('users.update', $actor), validUser(['email' => $actor->email, 'role' => $newRole]))
        ->assertSessionHasErrors('role');

    expect($actor->refresh()->role)->toBe($originalRole);
})->with([
    'hr promoting themselves' => fn () => [User::factory()->hr()->create(), 'capo_area'],
    'boss demoting themselves' => fn () => [User::factory()->boss()->create(), 'dipendente'],
]);

it('lets people edit their own data while keeping their role', function (): void {
    $hr = User::factory()->hr()->create(['email' => 'anna@azienda.it']);

    $this->actingAs($hr)
        ->put(route('users.update', $hr), validUser(['email' => 'anna@azienda.it', 'name' => 'Anna', 'role' => 'hr']))
        ->assertSessionHasNoErrors();

    expect($hr->refresh()->name)->toBe('Anna');
});
