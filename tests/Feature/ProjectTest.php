<?php

declare(strict_types=1);

use App\Enum\WorkStatus;
use App\Models\Activity;
use App\Models\Area;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function projectPayload(Client $client, array $overrides = []): array
{
    return array_merge([
        'client_id' => $client->id,
        'name' => 'Sito nuovo',
        'description' => 'Rifacimento',
        'starts_on' => '2026-11-01',
        'due_on' => '2026-12-31',
        'planned_hours' => 120,
        'status' => WorkStatus::Todo->value,
        'member_ids' => [],
        'contact_ids' => [],
    ], $overrides);
}

function projectUpdatePayload(Project $project, array $overrides = []): array
{
    return projectPayload($project->client, [
        'starts_on' => $project->starts_on->toDateString(),
        'due_on' => $project->due_on->toDateString(),
        'status' => $project->status->value,
        ...$overrides,
    ]);
}

it('lists projects for boss, area head and hr, hiding the archived ones by default', function (User $user): void {
    Project::factory()->create(['status' => WorkStatus::InProgress]);
    Project::factory()->create(['status' => WorkStatus::Archived]);

    $this->actingAs($user)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/index')
            ->has('projects', 1));
})->with([
    'boss' => fn () => User::factory()->boss()->create(),
    'area head' => fn () => User::factory()->areaHead()->create(),
    'hr' => fn () => User::factory()->hr()->create(),
]);

it('filters the list by client, status and the archived switch, and reports used minutes', function (): void {
    $acme = Client::factory()->create();
    $mine = Project::factory()->for($acme)->create(['status' => WorkStatus::InProgress]);
    Project::factory()->create(['status' => WorkStatus::Done]);
    $archived = Project::factory()->for($acme)->create(['status' => WorkStatus::Archived]);
    TimeEntry::factory()->for(Activity::factory()->for($mine))->create(['duration_minutes' => 75]);
    $boss = User::factory()->boss()->create();

    $this->actingAs($boss)
        ->get(route('projects.index', ['client_id' => $acme->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects', 1)
            ->where('projects.0.id', $mine->id)
            ->where('projects.0.used_minutes', 75));

    $this->actingAs($boss)
        ->get(route('projects.index', ['client_id' => $acme->id, 'archived' => 1]))
        ->assertInertia(fn (Assert $page) => $page->has('projects', 2));

    $this->actingAs($boss)
        ->get(route('projects.index', ['status' => WorkStatus::Archived->value]))
        ->assertInertia(fn (Assert $page) => $page->has('projects', 1)->where('projects.0.id', $archived->id));

    $this->actingAs($boss)
        ->get(route('projects.index', ['status' => 'boh']))
        ->assertSessionHasErrors('status');
});

it('forbids the employee from every project route', function (): void {
    $employee = User::factory()->employee()->create();
    $project = Project::factory()->create();

    $this->actingAs($employee)->get(route('projects.index'))->assertForbidden();
    $this->actingAs($employee)->get(route('projects.show', $project))->assertForbidden();
    $this->actingAs($employee)->post(route('projects.store'), [])->assertForbidden();
    $this->actingAs($employee)->put(route('projects.update', $project), [])->assertForbidden();
    $this->actingAs($employee)->post(route('projects.archive', $project))->assertForbidden();
    $this->actingAs($employee)->delete(route('projects.destroy', $project))->assertForbidden();
});

it('shows the project detail with activities, members, contacts and hours', function (): void {
    $project = Project::factory()->create();
    $member = User::factory()->employee()->create();
    $contact = ClientContact::factory()->for($project->client)->create();
    $project->members()->attach($member);
    $project->contacts()->attach($contact);
    $activity = Activity::factory()->for($project)->create();
    $activity->assignees()->attach($member);
    TimeEntry::factory()->for($activity)->for($member)->create(['duration_minutes' => 60]);

    $this->actingAs(User::factory()->hr()->create())
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/show')
            ->where('project.id', $project->id)
            ->where('project.used_minutes', 60)
            ->has('project.members', 1)
            ->has('project.contacts', 1)
            ->has('activities', 1)
            ->has('time_entries', 1)
            ->where('can_create_activity', false));
});

it('shows the area head only the hours of their own area', function (): void {
    $design = Area::factory()->create();
    $head = User::factory()->areaHead()->create(['area_id' => $design->id]);
    $mine = User::factory()->employee()->create(['area_id' => $design->id]);
    $other = User::factory()->employee()->create(['area_id' => Area::factory()->create()->id]);
    $activity = Activity::factory()->create();
    TimeEntry::factory()->for($activity)->for($mine)->create();
    TimeEntry::factory()->for($activity)->for($other)->create();

    $this->actingAs($head)
        ->get(route('projects.show', $activity->project))
        ->assertInertia(fn (Assert $page) => $page->has('time_entries', 1)->where('can_create_activity', true));
});

it('creates a project with members and contacts of its client', function (): void {
    $client = Client::factory()->create();
    $contact = ClientContact::factory()->for($client)->create();
    $members = User::factory()->employee()->count(2)->create();

    $response = $this->actingAs(User::factory()->areaHead()->create())
        ->post(route('projects.store'), projectPayload($client, [
            'member_ids' => $members->pluck('id')->all(),
            'contact_ids' => [$contact->id],
        ]));

    $project = Project::where('name', 'Sito nuovo')->firstOrFail();

    $response->assertRedirect(route('projects.show', $project))->assertSessionHasNoErrors();

    expect($project->client_id)->toBe($client->id)
        ->and($project->members)->toHaveCount(2)
        ->and($project->contacts->pluck('id')->all())->toBe([$contact->id])
        ->and((float) $project->planned_hours)->toBe(120.0);
});

it('answers 403, not a validation error, when the employee sends an invalid payload', function (): void {
    $this->actingAs(User::factory()->employee()->create())
        ->post(route('projects.store'), ['name' => ''])
        ->assertForbidden();
});

it('validates the project fields', function (array $overrides, string $field): void {
    $client = Client::factory()->create();

    $this->actingAs(User::factory()->boss()->create())
        ->post(route('projects.store'), projectPayload($client, $overrides))
        ->assertSessionHasErrors($field);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'unknown client' => [['client_id' => 999999], 'client_id'],
    'due before start' => [['starts_on' => '2026-12-10', 'due_on' => '2026-12-01'], 'due_on'],
    'negative hours' => [['planned_hours' => -5], 'planned_hours'],
    'unknown status' => [['status' => 'boh'], 'status'],
    'unknown member' => [['member_ids' => [999999]], 'member_ids.0'],
    'duplicate member' => [['member_ids' => [1, 1]], 'member_ids.0'],
]);

it('rejects contacts that belong to another client', function (): void {
    $client = Client::factory()->create();
    $foreign = ClientContact::factory()->for(Client::factory())->create();

    $this->actingAs(User::factory()->boss()->create())
        ->post(route('projects.store'), projectPayload($client, ['contact_ids' => [$foreign->id]]))
        ->assertSessionHasErrors('contact_ids.0');

    $this->assertDatabaseCount('projects', 0);
});

it('updates a project, its members and contacts, keeping the client', function (): void {
    $project = Project::factory()->create(['name' => 'Vecchio']);
    $contact = ClientContact::factory()->for($project->client)->create();
    $project->members()->attach(User::factory()->employee()->create());
    $newMember = User::factory()->employee()->create();

    $this->actingAs(User::factory()->hr()->create())
        ->put(route('projects.update', $project), projectUpdatePayload($project, [
            'name' => 'Nuovo',
            'planned_hours' => 200,
            'member_ids' => [$newMember->id],
            'contact_ids' => [$contact->id],
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $clientId = $project->client_id;
    $project->refresh();

    expect($project->name)->toBe('Nuovo')
        ->and($project->client_id)->toBe($clientId)
        ->and($project->members->pluck('id')->all())->toBe([$newMember->id])
        ->and($project->contacts->pluck('id')->all())->toBe([$contact->id]);
});

it('refuses to change the client of an existing project', function (): void {
    $project = Project::factory()->create();

    $this->actingAs(User::factory()->boss()->create())
        ->put(route('projects.update', $project), projectUpdatePayload($project, ['client_id' => Client::factory()->create()->id]))
        ->assertSessionHasErrors('client_id');
});

it('does not let the project due date precede the due date of its activities', function (): void {
    $project = Project::factory()->create(['starts_on' => '2026-11-01', 'due_on' => '2026-12-31']);
    Activity::factory()->for($project)->create(['starts_on' => '2026-11-02', 'due_on' => '2026-12-20']);
    $boss = User::factory()->boss()->create();

    $this->actingAs($boss)
        ->put(route('projects.update', $project), projectUpdatePayload($project, ['due_on' => '2026-12-10']))
        ->assertSessionHasErrors('due_on');

    $this->actingAs($boss)
        ->put(route('projects.update', $project), projectUpdatePayload($project, ['due_on' => '2026-12-20']))
        ->assertSessionHasNoErrors();
});

it('archives a project together with its activities', function (): void {
    $project = Project::factory()->create(['status' => WorkStatus::InProgress]);
    $activity = Activity::factory()->for($project)->create(['status' => WorkStatus::InProgress]);

    $this->actingAs(User::factory()->areaHead()->create())
        ->post(route('projects.archive', $project))
        ->assertRedirect(route('projects.index'));

    expect($project->refresh()->status)->toBe(WorkStatus::Archived)
        ->and($activity->refresh()->status)->toBe(WorkStatus::Archived);
});

it('deletes a project without hours together with its activities', function (): void {
    $project = Project::factory()->create();
    Activity::factory()->for($project)->count(2)->create();

    $this->actingAs(User::factory()->boss()->create())
        ->delete(route('projects.destroy', $project))
        ->assertRedirect(route('projects.index'));

    $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    $this->assertDatabaseCount('activities', 0);
});

it('refuses to delete a project with hours and keeps everything', function (): void {
    $project = Project::factory()->create();
    $activity = Activity::factory()->for($project)->create();
    TimeEntry::factory()->for($activity)->create();

    $this->actingAs(User::factory()->boss()->create())
        ->delete(route('projects.destroy', $project))
        ->assertSessionHasErrors('project');

    $this->assertDatabaseHas('projects', ['id' => $project->id]);
    $this->assertDatabaseHas('activities', ['id' => $activity->id]);
});

it('does not let the form archive a project: only the archive action does', function (): void {
    $client = Client::factory()->create();
    $project = Project::factory()->for($client)->create(['status' => WorkStatus::InProgress]);
    $boss = User::factory()->boss()->create();

    $this->actingAs($boss)
        ->post(route('projects.store'), projectPayload($client, ['status' => WorkStatus::Archived->value]))
        ->assertSessionHasErrors('status');

    $this->actingAs($boss)
        ->put(route('projects.update', $project), projectUpdatePayload($project, ['status' => WorkStatus::Archived->value]))
        ->assertSessionHasErrors('status');

    expect($project->refresh()->status)->toBe(WorkStatus::InProgress);
});

it('still lets an already archived project be edited', function (): void {
    $project = Project::factory()->create(['status' => WorkStatus::Archived]);

    $this->actingAs(User::factory()->boss()->create())
        ->put(route('projects.update', $project), projectUpdatePayload($project, ['name' => 'Rinominato']))
        ->assertSessionHasNoErrors();

    expect($project->refresh()->name)->toBe('Rinominato');
});
