<?php

declare(strict_types=1);

use App\Enum\AppLocale;
use App\Enum\TimeFormat;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;

function invitationUrl(User $user, string $route = 'invitation.show'): string
{
    return URL::temporarySignedRoute($route, now()->addDay(), ['user' => $user->id]);
}

function pendingUser(): User
{
    return User::factory()->create(['invited_at' => now(), 'email_verified_at' => null]);
}

it('shows the first access page from a valid signed link', function (): void {
    $user = pendingUser();

    $this->get(invitationUrl($user))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/accept-invitation')
            ->where('invitee.email', $user->email));
});

it('rejects an unsigned or expired link', function (): void {
    $user = pendingUser();

    $this->get(route('invitation.show', $user))->assertForbidden();

    $expired = URL::temporarySignedRoute('invitation.show', now()->subMinute(), ['user' => $user->id]);
    $this->get($expired)->assertForbidden();
});

it('activates the user with the chosen password and preferences', function (): void {
    $user = pendingUser();

    $this->post(invitationUrl($user, 'invitation.accept'), [
        'password' => 'una-password-lunga-1',
        'password_confirmation' => 'una-password-lunga-1',
        'locale' => 'en',
        'time_format' => '12h',
    ])->assertRedirect(route('dashboard'));

    $user->refresh();
    expect($user->invited_at)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('una-password-lunga-1', $user->password))->toBeTrue()
        ->and($user->locale)->toBe(AppLocale::En)
        ->and($user->time_format)->toBe(TimeFormat::Hours12);
    $this->assertAuthenticatedAs($user);
});

it('validates the password and the preferences', function (array $overrides, string $field): void {
    $user = pendingUser();

    $this->post(invitationUrl($user, 'invitation.accept'), array_merge([
        'password' => 'una-password-lunga-1',
        'password_confirmation' => 'una-password-lunga-1',
        'locale' => 'it',
        'time_format' => '24h',
    ], $overrides))->assertSessionHasErrors($field);

    expect($user->refresh()->invited_at)->not->toBeNull();
})->with([
    'too short' => [['password' => 'corta', 'password_confirmation' => 'corta'], 'password'],
    'mismatch' => [['password_confirmation' => 'diversa-1234'], 'password'],
    'bad locale' => [['locale' => 'fr'], 'locale'],
    'bad time format' => [['time_format' => '13h'], 'time_format'],
]);

it('does not reuse an invitation already accepted', function (): void {
    $user = User::factory()->create(['invited_at' => null]);

    $this->get(invitationUrl($user))->assertForbidden();
});

it('has no public registration', function (): void {
    $this->get('/register')->assertNotFound();
    $this->post('/register', ['name' => 'X', 'email' => 'x@x.it', 'password' => 'password', 'password_confirmation' => 'password'])->assertStatus(404);
});
