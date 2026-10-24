<?php

declare(strict_types=1);

use App\Enum\WorkStatus;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function validClientPayload(array $overrides = []): array
{
    return array_merge([
        'company_name' => 'Verdi Srl',
        'contacts' => [
            ['name' => 'Marta Verdi', 'email' => 'marta@verdi.it', 'phone' => '011 123456', 'is_primary' => true],
            ['name' => 'Piero Verdi', 'email' => null, 'phone' => null, 'is_primary' => false],
        ],
    ], $overrides);
}

it('lets boss, area head and hr list clients with active projects and primary contact', function (User $user): void {
    $client = Client::factory()->create(['company_name' => 'Rossi Srl']);
    ClientContact::factory()->for($client)->primary()->create(['name' => 'Paolo']);
    ClientContact::factory()->for($client)->create(['name' => 'Altro']);
    Project::factory()->for($client)->create(['status' => WorkStatus::InProgress]);
    Project::factory()->for($client)->create(['status' => WorkStatus::WaitingClient]);
    Project::factory()->for($client)->create(['status' => WorkStatus::Done]);
    Project::factory()->for($client)->create(['status' => WorkStatus::Archived]);

    $this->actingAs($user)
        ->get(route('clients.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/index')
            ->has('clients', 1)
            ->where('clients.0.active_projects_count', 2)
            ->where('clients.0.primary_contact', 'Paolo')
            ->has('clients.0.contacts', 2));
})->with([
    'boss' => fn () => User::factory()->boss()->create(),
    'area head' => fn () => User::factory()->areaHead()->create(),
    'hr' => fn () => User::factory()->hr()->create(),
]);

it('shows the client detail with contacts and linked projects', function (): void {
    $client = Client::factory()->create();
    ClientContact::factory()->for($client)->primary()->create();
    Project::factory()->for($client)->count(2)->create();

    $this->actingAs(User::factory()->hr()->create())
        ->get(route('clients.show', $client))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('clients/show')
            ->where('client.id', $client->id)
            ->has('client.contacts', 1)
            ->has('client.projects', 2));
});

it('forbids the employee from every client route', function (): void {
    $employee = User::factory()->employee()->create();
    $client = Client::factory()->create();

    $this->actingAs($employee)->get(route('clients.index'))->assertForbidden();
    $this->actingAs($employee)->get(route('clients.show', $client))->assertForbidden();
    $this->actingAs($employee)->post(route('clients.store'), validClientPayload())->assertForbidden();
    $this->actingAs($employee)->put(route('clients.update', $client), validClientPayload())->assertForbidden();
});

it('answers 403, not a validation error, when the employee sends an invalid payload', function (): void {
    $this->actingAs(User::factory()->employee()->create())
        ->post(route('clients.store'), ['company_name' => ''])
        ->assertForbidden();
});

it('creates a client with its contacts', function (): void {
    $this->actingAs(User::factory()->areaHead()->create())
        ->post(route('clients.store'), validClientPayload())
        ->assertRedirect(route('clients.index'));

    $client = Client::where('company_name', 'Verdi Srl')->firstOrFail();

    expect($client->contacts)->toHaveCount(2)
        ->and($client->contacts->firstWhere('is_primary', true)->name)->toBe('Marta Verdi');
});

it('creates a client without contacts', function (): void {
    $this->actingAs(User::factory()->boss()->create())
        ->post(route('clients.store'), validClientPayload(['contacts' => []]))
        ->assertSessionHasNoErrors();

    expect(Client::where('company_name', 'Verdi Srl')->firstOrFail()->contacts)->toBeEmpty();
});

it('makes the first contact primary when none is flagged', function (): void {
    $this->actingAs(User::factory()->boss()->create())
        ->post(route('clients.store'), validClientPayload([
            'contacts' => [
                ['name' => 'Uno', 'is_primary' => false],
                ['name' => 'Due', 'is_primary' => false],
            ],
        ]))
        ->assertSessionHasNoErrors();

    $contacts = Client::where('company_name', 'Verdi Srl')->firstOrFail()->contacts;

    expect($contacts->firstWhere('name', 'Uno')->is_primary)->toBeTrue()
        ->and($contacts->firstWhere('name', 'Due')->is_primary)->toBeFalse();
});

it('rejects more than one primary contact', function (): void {
    $this->actingAs(User::factory()->boss()->create())
        ->post(route('clients.store'), validClientPayload([
            'contacts' => [
                ['name' => 'Uno', 'is_primary' => true],
                ['name' => 'Due', 'is_primary' => true],
            ],
        ]))
        ->assertSessionHasErrors('contacts');

    $this->assertDatabaseMissing('clients', ['company_name' => 'Verdi Srl']);
});

it('validates client and contact fields', function (array $payload, string $field): void {
    $this->actingAs(User::factory()->boss()->create())
        ->post(route('clients.store'), validClientPayload($payload))
        ->assertSessionHasErrors($field);
})->with([
    'missing company name' => [['company_name' => ''], 'company_name'],
    'company name too long' => [['company_name' => str_repeat('a', 151)], 'company_name'],
    'contacts missing' => [['contacts' => null], 'contacts'],
    'contact without name' => [['contacts' => [['name' => '']]], 'contacts.0.name'],
    'contact with invalid email' => [['contacts' => [['name' => 'Uno', 'email' => 'non-una-email']]], 'contacts.0.email'],
]);

it('updates a client keeping existing contact ids, adding one and removing one', function (): void {
    $client = Client::factory()->create(['company_name' => 'Vecchio nome']);
    $kept = ClientContact::factory()->for($client)->primary()->create(['name' => 'Da tenere']);
    $removed = ClientContact::factory()->for($client)->create(['name' => 'Da togliere']);

    $this->actingAs(User::factory()->hr()->create())
        ->put(route('clients.update', $client), [
            'company_name' => 'Nuovo nome',
            'contacts' => [
                ['id' => $kept->id, 'name' => 'Tenuto e rinominato', 'is_primary' => true],
                ['name' => 'Nuovo referente', 'is_primary' => false],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $client->refresh();

    expect($client->company_name)->toBe('Nuovo nome')
        ->and($client->contacts)->toHaveCount(2)
        ->and($kept->refresh()->name)->toBe('Tenuto e rinominato')
        ->and(ClientContact::find($removed->id))->toBeNull()
        ->and($client->contacts->pluck('name')->all())->toContain('Nuovo referente');
});

it('keeps the link between a project and a contact that survives the update', function (): void {
    $client = Client::factory()->create();
    $contact = ClientContact::factory()->for($client)->primary()->create();
    $project = Project::factory()->for($client)->create();
    $project->contacts()->attach($contact);

    $this->actingAs(User::factory()->boss()->create())
        ->put(route('clients.update', $client), [
            'company_name' => 'Altro nome',
            'contacts' => [['id' => $contact->id, 'name' => 'Rinominato', 'is_primary' => true]],
        ])
        ->assertSessionHasNoErrors();

    expect($project->contacts()->pluck('client_contacts.id')->all())->toBe([$contact->id]);
});

it('rejects a contact id that belongs to another client', function (): void {
    $client = Client::factory()->create();
    $foreign = ClientContact::factory()->for(Client::factory())->create(['name' => 'Altrui']);

    $this->actingAs(User::factory()->boss()->create())
        ->put(route('clients.update', $client), [
            'company_name' => 'Cliente',
            'contacts' => [['id' => $foreign->id, 'name' => 'Sovrascritto']],
        ])
        ->assertSessionHasErrors('contacts.0.id');

    expect($foreign->refresh()->name)->toBe('Altrui');
});

it('rejects the same contact id sent twice', function (): void {
    $client = Client::factory()->create();
    $contact = ClientContact::factory()->for($client)->create();

    $this->actingAs(User::factory()->boss()->create())
        ->put(route('clients.update', $client), [
            'company_name' => 'Cliente',
            'contacts' => [
                ['id' => $contact->id, 'name' => 'Uno'],
                ['id' => $contact->id, 'name' => 'Due'],
            ],
        ])
        ->assertSessionHasErrors('contacts.0.id');
});

it('tells how many projects each contact is linked to, so the UI can warn before removal', function (): void {
    $client = Client::factory()->create();
    $linked = ClientContact::factory()->for($client)->primary()->create();
    ClientContact::factory()->for($client)->create();
    Project::factory()->for($client)->count(2)->create()->each(fn (Project $project) => $project->contacts()->attach($linked));

    $this->actingAs(User::factory()->boss()->create())
        ->get(route('clients.show', $client))
        ->assertInertia(fn (Assert $page) => $page
            ->where('client.contacts.0.projects_count', 2)
            ->where('client.contacts.1.projects_count', 0));
});
