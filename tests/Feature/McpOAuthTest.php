<?php

declare(strict_types=1);

use App\Models\AccessToken;
use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;

function mcpClient(string $name = 'Claude'): Client
{
    return app(ClientRepository::class)->createAuthorizationCodeGrantClient($name, ['https://claude.example/callback'], confidential: false);
}

function mcpAccess(User $user, Client $client, array $attributes = []): AccessToken
{
    $token = AccessToken::forceCreate($attributes + [
        'id' => Str::random(40),
        'user_id' => $user->id,
        'client_id' => $client->id,
        'scopes' => ['mcp:use'],
        'revoked' => false,
        'expires_at' => now()->addDays(30),
    ]);

    Passport::refreshToken()->forceFill(['id' => Str::random(40), 'access_token_id' => $token->id, 'revoked' => false, 'expires_at' => now()->addDays(90)])->save();

    return $token;
}

function mcpRpc(string $method, array $params = []): array
{
    return ['jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params];
}

it('refuses the MCP endpoint without a token', function (): void {
    $this->postJson('/mcp/bclock', mcpRpc('tools/list'))->assertUnauthorized();
});

it('lists the tools for a person who authorized an app', function (): void {
    Passport::actingAs(User::factory()->employee()->create(), ['mcp:use']);

    $this->postJson('/mcp/bclock', mcpRpc('tools/list'))
        ->assertOk()
        ->assertSee('log_time_entry')
        ->assertSee('list_my_activities');
});

it('keeps tool names stable for AI clients', function (): void {
    Passport::actingAs(User::factory()->employee()->create(), ['mcp:use']);

    $this->postJson('/mcp/bclock', mcpRpc('tools/list'))->assertSee('"name":"request_absence"', false);
});

it('points an unauthenticated client to the OAuth metadata', function (): void {
    $this->postJson('/mcp/bclock', mcpRpc('tools/list'))
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate');

    $this->getJson('/.well-known/oauth-protected-resource/mcp/bclock')->assertOk()->assertJsonStructure(['authorization_servers']);
});

it('runs a tool over HTTP as the person who authorized the app', function (): void {
    $person = User::factory()->employee()->create();
    $project = Project::factory()->create(['starts_on' => '2026-11-01', 'due_on' => '2026-12-31']);
    Activity::factory()->for($project)->create(['name' => 'Sviluppo login', 'starts_on' => '2026-11-09', 'due_on' => '2026-11-13'])->assignees()->attach($person);
    Passport::actingAs($person, ['mcp:use']);

    $this->postJson('/mcp/bclock', mcpRpc('tools/call', ['name' => 'list_my_activities', 'arguments' => (object) []]))
        ->assertOk()
        ->assertSee('Sviluppo login')
        ->assertDontSee('Not authenticated.');
});

it('publishes the OAuth discovery metadata', function (): void {
    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('authorization_endpoint', route('passport.authorizations.authorize'))
        ->assertJsonPath('token_endpoint', route('passport.token'))
        ->assertJsonPath('scopes_supported.0', 'mcp:use');

    $this->getJson('/.well-known/oauth-protected-resource')->assertOk()->assertJsonStructure(['authorization_servers']);
});

it('registers an app dynamically', function (): void {
    $this->postJson('/oauth/register', ['client_name' => 'Claude', 'redirect_uris' => ['https://claude.example/callback']])
        ->assertSuccessful()
        ->assertJsonStructure(['client_id']);
});

it('shows the consent screen in the language of the person', function (string $locale, string $title, string $role): void {
    $person = User::factory()->employee()->create(['name' => 'Mario', 'surname' => 'Rossi', 'locale' => $locale]);
    $client = mcpClient();

    $this->actingAs($person)->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->id,
        'redirect_uri' => 'https://claude.example/callback',
        'response_type' => 'code',
        'scope' => 'mcp:use',
        'state' => 'abc',
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', 'verifier', true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
    ]))->assertOk()
        ->assertSee($title, false)
        ->assertSee("Mario Rossi ($role)", false)
        ->assertSee(route('passport.authorizations.approve'), false);
})->with([
    'italian' => ['it', 'Collegare «Claude» a BClock?', 'Dipendente'],
    'english' => ['en', 'Connect “Claude” to BClock?', 'Employee'],
])->skip(fn () => ! file_exists(Passport::keyPath('oauth-private.key')), 'Passport keys are not generated.');

it('sends a signed-out person to the login and back to the consent screen', function (): void {
    $url = '/oauth/authorize?'.http_build_query(['client_id' => mcpClient()->id, 'response_type' => 'code', 'redirect_uri' => 'https://claude.example/callback', 'scope' => 'mcp:use', 'code_challenge' => str_repeat('a', 43), 'code_challenge_method' => 'S256']);

    $this->get($url)->assertRedirect(route('login'));

    expect(session('url.intended'))->toStartWith(url('/oauth/authorize?'))->toContain('code_challenge=');
});

it('remembers when an app last used its access', function (): void {
    $person = User::factory()->employee()->create();
    $token = mcpAccess($person, mcpClient());
    $this->travelTo(now()->addDay());

    Passport::actingAs($person, ['mcp:use']);
    $person->token()->oauth_access_token_id = $token->id;

    $this->postJson('/mcp/bclock', mcpRpc('tools/list'))->assertOk();

    expect($token->fresh()->last_used_at->toDateTimeString())->toBe(now()->toDateTimeString());
});

it('lists only the apps of the signed-in person, one row per app', function (): void {
    $person = User::factory()->employee()->create();
    $claude = mcpClient('Claude');
    mcpAccess($person, $claude);
    mcpAccess($person, $claude);
    mcpAccess(User::factory()->employee()->create(), mcpClient('Altro'));
    mcpAccess($person, mcpClient('Scaduta'), ['expires_at' => now()->subDay(), 'revoked' => true]);

    $this->actingAs($person)->get(route('connected-apps.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/connected-apps')
            ->has('apps', 1)
            ->where('apps.0.name', 'Claude')
            ->where('apps.0.last_used_at', null)
            ->where('server_url', url('/mcp/bclock')));
});

it('revokes the access and the refresh tokens of one app only', function (): void {
    $person = User::factory()->employee()->create();
    $claude = mcpClient('Claude');
    $other = mcpClient('Cursor');
    $revoked = mcpAccess($person, $claude);
    $kept = mcpAccess($person, $other);

    $this->actingAs($person)->delete(route('connected-apps.destroy', $claude->id))->assertRedirect(route('connected-apps.index'));

    expect($revoked->fresh()->revoked)->toBeTrue()
        ->and(Passport::refreshToken()->newQuery()->where('access_token_id', $revoked->id)->value('revoked'))->toBeTruthy()
        ->and($kept->fresh()->revoked)->toBeFalse()
        ->and(Passport::refreshToken()->newQuery()->where('access_token_id', $kept->id)->value('revoked'))->toBeFalsy();
});

it('does not let a person revoke the app of someone else', function (): void {
    $owner = User::factory()->employee()->create();
    $client = mcpClient();
    $token = mcpAccess($owner, $client);

    $this->actingAs(User::factory()->employee()->create())->delete(route('connected-apps.destroy', $client->id));

    expect($token->fresh()->revoked)->toBeFalse();
});
