<?php

declare(strict_types=1);

use App\Actions\Allocations\GenerateActivityAllocations;
use App\Enum\WorkStatus;
use App\Models\Activity;
use App\Models\Area;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  list<User>  $assignees
 */
function ganttActivity(Project $project, string $name, string $start, string $due, array $assignees = [], WorkStatus $status = WorkStatus::Todo): Activity
{
    $activity = Activity::factory()->for($project)->create([
        'name' => $name,
        'starts_on' => $start,
        'due_on' => $due,
        'planned_hours' => '10.00',
        'status' => $status,
    ]);
    $activity->assignees()->attach(collect($assignees)->pluck('id'));
    app(GenerateActivityAllocations::class)->handle($activity);

    return $activity;
}

/**
 * Project 1 Nov … 31 Dec with three chained activities: A (Mon 9 – Wed 11) ← B (Thu 12 – Fri 13) ← C (Mon 16 – Tue 17).
 *
 * @return array{Project, Activity, Activity, Activity, User}
 */
function ganttChain(): array
{
    $person = User::factory()->employee()->create();
    $project = Project::factory()->create(['starts_on' => '2026-11-01', 'due_on' => '2026-12-31']);
    $a = ganttActivity($project, 'A', '2026-11-09', '2026-11-11', [$person]);
    $b = ganttActivity($project, 'B', '2026-11-12', '2026-11-13', [$person]);
    $c = ganttActivity($project, 'C', '2026-11-16', '2026-11-17', [$person]);
    $b->dependencies()->attach($a);
    $c->dependencies()->attach($b);

    return [$project, $a, $b, $c, $person];
}

/**
 * @param  array<string, mixed>  $data
 */
function shiftRequest(array $data = []): array
{
    return array_merge(['mode' => 'move', 'starts_on' => '2026-11-11', 'due_on' => '2026-11-13'], $data);
}

it('moves an activity and every activity depending on it by the same working days', function (): void {
    [, $a, $b, $c] = ganttChain();

    // A starts on Wednesday 11 instead of Monday 9: +2 working days
    $this->actingAs(User::factory()->boss()->create())
        ->post(route('gantt.shift', $a), shiftRequest())
        ->assertRedirect(route('gantt.index'))
        ->assertSessionHasNoErrors();

    $dates = fn (Activity $activity) => [$activity->refresh()->starts_on->toDateString(), $activity->due_on->toDateString()];

    expect($dates($a))->toBe(['2026-11-11', '2026-11-13'])
        ->and($dates($b))->toBe(['2026-11-16', '2026-11-17'])
        ->and($dates($c))->toBe(['2026-11-18', '2026-11-19']);
});

it('skips weekends and holidays when it counts the days', function (): void {
    Holiday::factory()->create(['date' => '2026-11-12']);
    [, $a, $b] = ganttChain();

    // +4 working days: A Mon 9→ Fri 13; B Thu 12 (holiday!) – Fri 13 → +4 working
    $this->actingAs(User::factory()->boss()->create())
        ->post(route('gantt.shift', $a), shiftRequest(['starts_on' => '2026-11-13']))
        ->assertSessionHasNoErrors();

    // Mon 9 + 3 working (10, 11, 13: the 12th is a holiday) = Fri 13
    expect($a->refresh()->starts_on->toDateString())->toBe('2026-11-13')
        ->and($a->due_on->toDateString())->toBe('2026-11-17')
        ->and($b->refresh()->starts_on->toDateString())->toBe('2026-11-17');
});

it('moves an activity earlier', function (): void {
    [, , $b, $c] = ganttChain();

    $this->actingAs(User::factory()->boss()->create())
        ->post(route('gantt.shift', $b), shiftRequest(['starts_on' => '2026-11-10']))
        ->assertSessionHasNoErrors();

    expect($b->refresh()->starts_on->toDateString())->toBe('2026-11-10')
        ->and($b->due_on->toDateString())->toBe('2026-11-11')
        ->and($c->refresh()->starts_on->toDateString())->toBe('2026-11-12');
});

it('changes only the due date on resize_end and shifts the dependents by the difference', function (): void {
    [, $a, $b, $c] = ganttChain();

    $this->actingAs(User::factory()->boss()->create())
        ->post(route('gantt.shift', $a), shiftRequest(['mode' => 'resize_end', 'due_on' => '2026-11-13']))
        ->assertSessionHasNoErrors();

    expect($a->refresh()->starts_on->toDateString())->toBe('2026-11-09')
        ->and($a->due_on->toDateString())->toBe('2026-11-13')
        ->and($b->refresh()->starts_on->toDateString())->toBe('2026-11-16')
        ->and($c->refresh()->due_on->toDateString())->toBe('2026-11-19');
});

it('changes only the start date on resize_start, without moving anything else', function (): void {
    [, $a, $b] = ganttChain();

    $this->actingAs(User::factory()->boss()->create())
        ->post(route('gantt.shift', $a), shiftRequest(['mode' => 'resize_start', 'starts_on' => '2026-11-10']))
        ->assertSessionHasNoErrors();

    expect($a->refresh()->starts_on->toDateString())->toBe('2026-11-10')
        ->and($a->due_on->toDateString())->toBe('2026-11-11')
        ->and($b->refresh()->starts_on->toDateString())->toBe('2026-11-12');
});

it('refuses a due date before the start, and a request that changes nothing', function (): void {
    [, $a] = ganttChain();
    $boss = User::factory()->boss()->create();

    $this->actingAs($boss)->post(route('gantt.shift', $a), shiftRequest(['mode' => 'resize_end', 'due_on' => '2026-11-06']))->assertSessionHasErrors('activity');
    $this->actingAs($boss)->post(route('gantt.shift', $a), shiftRequest(['starts_on' => '2026-11-09']))->assertSessionHasErrors('activity');

    expect($a->refresh()->due_on->toDateString())->toBe('2026-11-11');
});

it('moves nothing when a completed or archived activity sits in the chain', function (WorkStatus $status): void {
    [, $a, $b, $c] = ganttChain();
    $c->update(['status' => $status]);

    $response = $this->actingAs(User::factory()->boss()->create())->post(route('gantt.shift', $a), shiftRequest());

    $response->assertSessionHasErrors('activity');
    expect(session('errors')->first('activity'))->toContain('«C»')
        ->and($a->refresh()->starts_on->toDateString())->toBe('2026-11-09')
        ->and($b->refresh()->starts_on->toDateString())->toBe('2026-11-12');
})->with([WorkStatus::Done, WorkStatus::Archived]);

it('moves nothing when the area head cannot edit one of the dependents', function (): void {
    $area = Area::factory()->create();
    $head = User::factory()->areaHead($area)->create();
    $mine = User::factory()->employee($area)->create();
    $outsider = User::factory()->employee(Area::factory()->create())->create();

    $project = Project::factory()->create(['starts_on' => '2026-11-01', 'due_on' => '2026-12-31']);
    $a = ganttActivity($project, 'A', '2026-11-09', '2026-11-11', [$mine]);
    $b = ganttActivity($project, 'B', '2026-11-12', '2026-11-13', [$outsider]);
    $b->dependencies()->attach($a);

    $this->actingAs($head)->post(route('gantt.shift', $a), shiftRequest())->assertSessionHasErrors('activity');

    expect(session('errors')->first('activity'))->toContain('«B»')
        ->and($a->refresh()->starts_on->toDateString())->toBe('2026-11-09');

    // without dependents outside the area it is allowed
    $b->dependencies()->detach();
    $this->actingAs($head)->post(route('gantt.shift', $a), shiftRequest())->assertSessionHasNoErrors();
    expect($a->refresh()->starts_on->toDateString())->toBe('2026-11-11');
});

it('forbids hr and employees from moving activities', function (User $user): void {
    [, $a] = ganttChain();

    $this->actingAs($user)->post(route('gantt.shift', $a), shiftRequest())->assertForbidden();
    $this->actingAs($user)->post(route('gantt.shift', $a), [])->assertForbidden();

    expect($a->refresh()->starts_on->toDateString())->toBe('2026-11-09');
})->with([
    'hr' => fn () => User::factory()->hr()->create(),
    'employee' => fn () => User::factory()->employee()->create(),
]);

it('needs a confirmation to go past the project due date, and then extends the project', function (): void {
    [$project, $a, , $c] = ganttChain();
    $project->update(['due_on' => '2026-11-20']);
    $boss = User::factory()->boss()->create();

    // C would become Wed 18 – Thu 19… with +4: Tue 17 + 4 = Mon 23, past the 20th
    $request = shiftRequest(['starts_on' => '2026-11-13']);

    $this->actingAs($boss)->post(route('gantt.shift', $a), $request)->assertSessionHasErrors('confirm_projects');
    expect($a->refresh()->starts_on->toDateString())->toBe('2026-11-09')
        ->and($project->refresh()->due_on->toDateString())->toBe('2026-11-20');

    $this->actingAs($boss)->post(route('gantt.shift', $a), $request + ['confirm_projects' => true])->assertSessionHasNoErrors();

    expect($a->refresh()->starts_on->toDateString())->toBe('2026-11-13')
        ->and($project->refresh()->due_on->toDateString())->toBe($c->refresh()->due_on->toDateString());
});

it('refuses to move an activity before the start of its project', function (): void {
    [, $a, $b] = ganttChain();

    $this->actingAs(User::factory()->boss()->create())
        ->post(route('gantt.shift', $b), shiftRequest(['starts_on' => '2026-10-26']))
        ->assertSessionHasErrors('activity');
});

it('recalculates the quotas of every moved activity and shifts the hand-moved ones with them', function (): void {
    [, $a, $b, , $person] = ganttChain();
    $boss = User::factory()->boss()->create();

    // A has 3 days (about 3.33 h a day); the Monday allocation is moved to Wednesday (by hand)
    $monday = $a->allocations()->whereDate('date', '2026-11-09')->firstOrFail();
    $this->actingAs($boss)->post(route('allocations.move', $monday), ['date' => '2026-11-10'])->assertSessionHasNoErrors();
    $manualBefore = (float) $a->allocations()->whereDate('date', '2026-11-10')->value('hours');

    $this->actingAs($boss)->post(route('gantt.shift', $a), shiftRequest())->assertRedirect(route('gantt.index'))->assertSessionHasNoErrors();

    $a->refresh();
    expect($a->allocations()->whereDate('date', '<', '2026-11-11')->where('hours', '>', 0)->exists())->toBeFalse()
        ->and($a->allocations()->whereDate('date', '>', '2026-11-13')->exists())->toBeFalse()
        ->and(round((float) $a->allocations()->sum('hours'), 2))->toBe(10.0)
        ->and((float) $a->allocations()->whereDate('date', '2026-11-12')->value('hours'))->toBe($manualBefore)
        ->and($a->allocations()->whereDate('date', '2026-11-11')->value('hours'))->toEqual(0)
        ->and(round((float) $b->refresh()->allocations()->sum('hours'), 2))->toBe(10.0)
        ->and($b->allocations()->whereDate('date', '>=', '2026-11-16')->count())->toBe($b->allocations()->count());
});

it('shows the boss and hr every activity, the area head their area and the employee their own', function (): void {
    $area = Area::factory()->create();
    $head = User::factory()->areaHead($area)->create();
    $mine = User::factory()->employee($area)->create();
    $other = User::factory()->employee(Area::factory()->create())->create();
    $project = Project::factory()->create(['starts_on' => '2026-11-01', 'due_on' => '2026-12-31']);
    ganttActivity($project, 'Mia', '2026-11-09', '2026-11-11', [$mine]);
    ganttActivity($project, 'Altrui', '2026-11-09', '2026-11-11', [$other]);

    $names = fn (User $user) => collect($this->actingAs($user)->get(route('gantt.index'))->viewData('page')['props']['projects'])
        ->flatMap(fn ($project) => collect($project['activities'])->pluck('name'))->sort()->values()->all();

    expect($names(User::factory()->boss()->create()))->toBe(['Altrui', 'Mia'])
        ->and($names(User::factory()->hr()->create()))->toBe(['Altrui', 'Mia'])
        ->and($names($head))->toBe(['Mia'])
        ->and($names($mine))->toBe(['Mia'])
        ->and($names(User::factory()->employee()->create()))->toBe([]);
});

it('tells who can move what, and hides archived activities unless asked', function (): void {
    [$project, $a] = ganttChain();
    ganttActivity($project, 'Vecchia', '2026-11-09', '2026-11-10', [], WorkStatus::Archived);

    $this->actingAs(User::factory()->boss()->create())->get(route('gantt.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('gantt/index')
            ->has('projects', 1)
            ->has('projects.0.activities', 3)
            ->where('projects.0.activities.0.can_move', true)
            ->where('zoom', 'week'));

    $this->actingAs(User::factory()->boss()->create())->get(route('gantt.index', ['status' => 'archiviato']))
        ->assertInertia(fn (Assert $page) => $page->has('projects.0.activities', 1)->where('projects.0.activities.0.name', 'Vecchia'));

    $this->actingAs(User::factory()->hr()->create())->get(route('gantt.index'))
        ->assertInertia(fn (Assert $page) => $page->where('projects.0.activities.0.can_move', false));
});

it('filters by project, area and person', function (): void {
    [$project, $a, , , $person] = ganttChain();
    $otherProject = Project::factory()->create(['starts_on' => '2026-11-01', 'due_on' => '2026-12-31']);
    $someone = User::factory()->employee(Area::factory()->create())->create();
    ganttActivity($otherProject, 'X', '2026-11-09', '2026-11-10', [$someone]);
    $boss = User::factory()->boss()->create();

    $this->actingAs($boss)->get(route('gantt.index', ['project_id' => $otherProject->id]))
        ->assertInertia(fn (Assert $page) => $page->has('projects', 1)->where('projects.0.id', $otherProject->id));
    $this->actingAs($boss)->get(route('gantt.index', ['area_id' => $someone->area_id]))
        ->assertInertia(fn (Assert $page) => $page->has('projects', 1)->where('projects.0.activities.0.name', 'X'));
    $this->actingAs($boss)->get(route('gantt.index', ['user_id' => $person->id]))
        ->assertInertia(fn (Assert $page) => $page->has('projects', 1)->has('projects.0.activities', 3));
});

it('spans the timeline over the activities, the projects and today', function (): void {
    ganttChain();

    $this->actingAs(User::factory()->boss()->create())->get(route('gantt.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('today', now(config('app.local_timezone'))->toDateString())
            ->where('from', fn ($from) => $from <= '2026-10-29')
            ->where('to', fn ($to) => $to >= '2027-01-07'));
});

it('previews the shift on a partial reload: changes, extensions and blockers', function (): void {
    [$project, $a] = ganttChain();
    $project->update(['due_on' => '2026-11-18']);
    $query = ['preview_activity' => $a->id, 'preview_mode' => 'move', 'preview_start' => '2026-11-11', 'preview_due' => '2026-11-13'];

    $this->actingAs(User::factory()->boss()->create())->get(route('gantt.index', $query))
        ->assertInertia(fn (Assert $page) => $page->reloadOnly('preview', fn (Assert $reload) => $reload
            ->where('preview.requested', ['mode' => 'move', 'start' => '2026-11-11', 'due' => '2026-11-13'])
            ->where('preview.shift', 2)
            ->has('preview.changes', 3)
            ->where('preview.changes.0.new_starts_on', '2026-11-11')
            ->where('preview.changes.2.new_due_on', '2026-11-19')
            ->has('preview.projects', 1)
            ->where('preview.projects.0.new_due_on', '2026-11-19')
            ->where('preview.blockers', [])));

    expect($a->refresh()->starts_on->toDateString())->toBe('2026-11-09');
});

it('does not compute the preview on a normal visit, nor for activities the user cannot see', function (): void {
    [, $a] = ganttChain();

    $this->actingAs(User::factory()->boss()->create())->get(route('gantt.index'))
        ->assertInertia(fn (Assert $page) => $page->missing('preview'));

    $query = ['preview_activity' => $a->id, 'preview_mode' => 'move', 'preview_start' => '2026-11-11', 'preview_due' => '2026-11-13'];
    $this->actingAs(User::factory()->employee()->create())->get(route('gantt.index', $query))
        ->assertInertia(fn (Assert $page) => $page->reloadOnly('preview', fn (Assert $reload) => $reload->where('preview', null)));
});
