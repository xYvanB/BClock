<?php

declare(strict_types=1);

use App\Enum\WorkStatus;
use App\Models\Activity;
use App\Models\Area;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function activityPayload(Project $project, array $assigneeIds, array $overrides = []): array
{
    return array_merge([
        'project_id' => $project->id,
        'name' => 'Mockup home',
        'description' => 'Prima bozza',
        'starts_on' => $project->due_on->copy()->subDays(10)->toDateString(),
        'due_on' => $project->due_on->toDateString(),
        'planned_hours' => 12.5,
        'status' => WorkStatus::Todo->value,
        'assignee_ids' => $assigneeIds,
    ], $overrides);
}

function activityUpdatePayload(Activity $activity, array $assigneeIds, array $overrides = []): array
{
    $payload = activityPayload($activity->project, $assigneeIds, $overrides);
    unset($payload['project_id']);

    return $payload;
}

it('lists activities for boss and hr, with used hours and the edit flag', function (User $user, bool $canUpdate): void {
    $activity = Activity::factory()->create();
    $activity->assignees()->attach(User::factory()->employee()->create());
    TimeEntry::factory()->for($activity)->create(['duration_minutes' => 90]);

    $this->actingAs($user)
        ->get(route('activities.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('activities/index')
            ->has('activities', 1)
            ->where('activities.0.used_minutes', 90)
            ->where('activities.0.can_update', $canUpdate)
            ->where('can_create', $canUpdate));
})->with([
    'boss' => [fn () => User::factory()->boss()->create(), true],
    'hr read-only' => [fn () => User::factory()->hr()->create(), false],
]);

it('limits the area head to activities with an assignee in their area and to their own people', function (): void {
    $design = Area::factory()->create();
    $development = Area::factory()->create();
    $head = User::factory()->areaHead()->create(['area_id' => $design->id]);
    $mine = User::factory()->employee()->create(['area_id' => $design->id]);
    $other = User::factory()->employee()->create(['area_id' => $development->id]);

    Activity::factory()->create()->assignees()->attach($mine);
    Activity::factory()->create()->assignees()->attach($other);

    $this->actingAs($head)
        ->get(route('activities.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('activities', 1)
            ->has('assignees', 2)
            ->where('can_create', true));
});

it('forbids the employee from the activities page and from creating', function (): void {
    $employee = User::factory()->employee()->create();
    $project = Project::factory()->create();

    $this->actingAs($employee)->get(route('activities.index'))->assertForbidden();
    $this->actingAs($employee)
        ->post(route('activities.store'), activityPayload($project, [$employee->id]))
        ->assertForbidden();
});

it('forbids hr from creating or changing activities, answering 403 before validation', function (): void {
    $hr = User::factory()->hr()->create();
    $activity = Activity::factory()->create();

    $this->actingAs($hr)->post(route('activities.store'), [])->assertForbidden();
    $this->actingAs($hr)->put(route('activities.update', $activity), [])->assertForbidden();
});

it('creates an activity with its assignees', function (): void {
    $project = Project::factory()->create();
    $people = User::factory()->employee()->count(2)->create();

    $this->actingAs(User::factory()->boss()->create())
        ->from(route('activities.index'))
        ->post(route('activities.store'), activityPayload($project, $people->pluck('id')->all()))
        ->assertRedirect(route('activities.index'))
        ->assertSessionHasNoErrors();

    $activity = Activity::where('name', 'Mockup home')->firstOrFail();

    expect($activity->assignees->pluck('id')->sort()->values()->all())->toBe($people->pluck('id')->sort()->values()->all())
        ->and($activity->project_id)->toBe($project->id)
        ->and($activity->status)->toBe(WorkStatus::Todo)
        ->and((float) $activity->planned_hours)->toBe(12.5);
});

it('rejects a due date after the project due date with an inline message on due_on', function (): void {
    $project = Project::factory()->create();
    $person = User::factory()->employee()->create();

    $this->actingAs(User::factory()->boss()->create())
        ->post(route('activities.store'), activityPayload($project, [$person->id], [
            'due_on' => $project->due_on->copy()->addDay()->toDateString(),
        ]))
        ->assertSessionHasErrors('due_on');

    $this->assertDatabaseCount('activities', 0);
});

it('accepts a due date equal to the project due date', function (): void {
    $project = Project::factory()->create();
    $person = User::factory()->employee()->create();

    $this->actingAs(User::factory()->boss()->create())
        ->post(route('activities.store'), activityPayload($project, [$person->id]))
        ->assertSessionHasNoErrors();
});

it('validates the activity fields', function (array $overrides, string $field): void {
    $project = Project::factory()->create();
    $person = User::factory()->employee()->create();

    $this->actingAs(User::factory()->boss()->create())
        ->post(route('activities.store'), activityPayload($project, [$person->id], $overrides))
        ->assertSessionHasErrors($field);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'due before start' => [['starts_on' => '2026-12-10', 'due_on' => '2026-12-01'], 'due_on'],
    'negative hours' => [['planned_hours' => -1], 'planned_hours'],
    'unknown status' => [['status' => 'boh'], 'status'],
    'no assignees' => [['assignee_ids' => []], 'assignee_ids'],
    'unknown assignee' => [['assignee_ids' => [999999]], 'assignee_ids.0'],
    'unknown project' => [['project_id' => 999999], 'project_id'],
]);

it('does not accept an archived project', function (): void {
    $project = Project::factory()->create(['status' => WorkStatus::Archived]);
    $person = User::factory()->employee()->create();

    $this->actingAs(User::factory()->boss()->create())
        ->post(route('activities.store'), activityPayload($project, [$person->id]))
        ->assertSessionHasErrors('project_id');
});

it('lets the area head assign only people of their own area', function (): void {
    $design = Area::factory()->create();
    $head = User::factory()->areaHead()->create(['area_id' => $design->id]);
    $mine = User::factory()->employee()->create(['area_id' => $design->id]);
    $outsider = User::factory()->employee()->create(['area_id' => Area::factory()->create()->id]);
    $project = Project::factory()->create();

    $this->actingAs($head)
        ->post(route('activities.store'), activityPayload($project, [$mine->id, $outsider->id]))
        ->assertSessionHasErrors('assignee_ids.1');

    $this->actingAs($head)
        ->post(route('activities.store'), activityPayload($project, [$mine->id]))
        ->assertSessionHasNoErrors();
});

it('updates an activity and its assignees but never its project', function (): void {
    $activity = Activity::factory()->create(['name' => 'Vecchio']);
    $activity->assignees()->attach(User::factory()->employee()->create());
    $newPerson = User::factory()->employee()->create();
    $boss = User::factory()->boss()->create();

    $this->actingAs($boss)
        ->put(route('activities.update', $activity), activityPayload($activity->project, [$newPerson->id], [
            'project_id' => Project::factory()->create()->id,
        ]))
        ->assertSessionHasErrors('project_id');

    $this->actingAs($boss)
        ->from(route('activities.index'))
        ->put(route('activities.update', $activity), activityUpdatePayload($activity, [$newPerson->id], [
            'name' => 'Nuovo',
            'status' => WorkStatus::InProgress->value,
        ]))
        ->assertRedirect(route('activities.index'))
        ->assertSessionHasNoErrors();

    $projectId = $activity->project_id;
    $activity->refresh();

    expect($activity->name)->toBe('Nuovo')
        ->and($activity->status)->toBe(WorkStatus::InProgress)
        ->and($activity->project_id)->toBe($projectId)
        ->and($activity->assignees->pluck('id')->all())->toBe([$newPerson->id]);
});

it('checks the project due date also when updating', function (): void {
    $activity = Activity::factory()->create();
    $person = User::factory()->employee()->create();
    $activity->assignees()->attach($person);

    $this->actingAs(User::factory()->boss()->create())
        ->put(route('activities.update', $activity), activityUpdatePayload($activity, [$person->id], [
            'due_on' => $activity->project->due_on->copy()->addDays(3)->toDateString(),
        ]))
        ->assertSessionHasErrors('due_on');
});

it('forbids the area head from updating an activity with assignees outside their area', function (): void {
    $design = Area::factory()->create();
    $head = User::factory()->areaHead()->create(['area_id' => $design->id]);
    $mine = User::factory()->employee()->create(['area_id' => $design->id]);
    $outsider = User::factory()->employee()->create(['area_id' => Area::factory()->create()->id]);

    $mixed = Activity::factory()->create();
    $mixed->assignees()->attach([$mine->id, $outsider->id]);
    $own = Activity::factory()->create();
    $own->assignees()->attach($mine);

    $this->actingAs($head)
        ->put(route('activities.update', $mixed), activityUpdatePayload($mixed, [$mine->id]))
        ->assertForbidden();

    $this->actingAs($head)
        ->put(route('activities.update', $own), activityUpdatePayload($own, [$mine->id]))
        ->assertSessionHasNoErrors();
});

it('accepts an update that sends the unchanged project id, as the drawer does', function (): void {
    $activity = Activity::factory()->create();
    $person = User::factory()->employee()->create();
    $activity->assignees()->attach($person);

    $this->actingAs(User::factory()->boss()->create())
        ->put(route('activities.update', $activity), activityPayload($activity->project, [$person->id], ['name' => 'Rinominata']))
        ->assertSessionHasNoErrors();

    expect($activity->refresh()->name)->toBe('Rinominata');
});

it('sends the user back to the project detail after saving an activity from there', function (): void {
    $project = Project::factory()->create();
    $person = User::factory()->employee()->create();

    $this->actingAs(User::factory()->boss()->create())
        ->from(route('projects.show', $project))
        ->post(route('activities.store'), activityPayload($project, [$person->id]))
        ->assertRedirect(route('projects.show', $project));
});
