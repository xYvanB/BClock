<?php

declare(strict_types=1);

use App\Actions\Allocations\GenerateActivityAllocations;
use App\Enum\WorkStatus;
use App\Models\Absence;
use App\Models\Activity;
use App\Models\ActivityAllocation;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\User;

/**
 * Monday 2026-11-09 … Friday 2026-11-13 are five working days.
 *
 * @param  list<User>  $assignees
 */
function allocatedActivity(array $assignees, string $start = '2026-11-09', string $due = '2026-11-13', string $hours = '10.00'): Activity
{
    $activity = Activity::factory()->create([
        'starts_on' => $start,
        'due_on' => $due,
        'planned_hours' => $hours,
        'status' => WorkStatus::Todo,
    ]);
    $activity->assignees()->attach(collect($assignees)->pluck('id'));

    app(GenerateActivityAllocations::class)->handle($activity);

    return $activity;
}

it('splits the planned hours equally on the working days between start and due date', function (): void {
    $user = User::factory()->employee()->create();
    $activity = allocatedActivity([$user]);

    $quotas = $activity->allocations()->orderBy('date')->get();

    expect($quotas)->toHaveCount(5)
        ->and($quotas->pluck('hours')->map(fn ($hours) => (float) $hours)->unique()->all())->toBe([2.0])
        ->and($quotas->first()->date->toDateString())->toBe('2026-11-09')
        ->and($quotas->last()->date->toDateString())->toBe('2026-11-13');
});

it('skips weekends and holidays', function (): void {
    Holiday::factory()->create(['date' => '2026-11-11']);
    $user = User::factory()->employee()->create();

    // from Friday to Tuesday: Fri 6, (Sat, Sun), Mon 9, Tue 10, Wed 11 holiday -> Fri, Mon, Tue
    $activity = allocatedActivity([$user], '2026-11-06', '2026-11-11', '9.00');

    expect($activity->allocations()->orderBy('date')->pluck('date')->map->toDateString()->all())
        ->toBe(['2026-11-06', '2026-11-09', '2026-11-10'])
        ->and((float) $activity->allocations()->first()->hours)->toBe(3.0);
});

it('keeps the total equal to the planned hours when it does not divide evenly', function (): void {
    $user = User::factory()->employee()->create();
    $activity = allocatedActivity([$user], hours: '10.01');

    expect(round((float) $activity->allocations()->sum('hours'), 2))->toBe(10.01);
});

it('splits between assignees and gives nothing to a person on a confirmed absence', function (): void {
    $anna = User::factory()->employee()->create();
    $luca = User::factory()->employee()->create();
    Absence::factory()->for($luca)->confirmed()->create(['starts_on' => '2026-11-09', 'ends_on' => '2026-11-10']);

    // 10 person-day pairs, 2 absent -> 8 allocations of 1.25 hours
    $activity = allocatedActivity([$anna, $luca]);

    expect($activity->allocations()->count())->toBe(8)
        ->and($activity->allocations()->where('user_id', $luca->id)->count())->toBe(3)
        ->and((float) $activity->allocations()->first()->hours)->toBe(1.25)
        ->and(round((float) $activity->allocations()->sum('hours'), 2))->toBe(10.0);
});

it('ignores pending and rejected absences', function (): void {
    $user = User::factory()->employee()->create();
    Absence::factory()->for($user)->create(['starts_on' => '2026-11-09', 'ends_on' => '2026-11-09']);

    expect(allocatedActivity([$user])->allocations()->count())->toBe(5);
});

it('does not lose hours when the only assignee is absent the whole period, nor on a weekend-only period', function (): void {
    $user = User::factory()->employee()->create();
    Absence::factory()->for($user)->confirmed()->create(['starts_on' => '2026-11-09', 'ends_on' => '2026-11-13']);

    $absent = allocatedActivity([$user]);
    $weekend = allocatedActivity([User::factory()->employee()->create()], '2026-11-14', '2026-11-15', '4.00');

    expect(round((float) $absent->allocations()->sum('hours'), 2))->toBe(10.0)
        ->and($weekend->allocations()->count())->toBe(2)
        ->and(round((float) $weekend->allocations()->sum('hours'), 2))->toBe(4.0);
});

it('creates no quotas without assignees or planned hours, and leaves archived activities alone', function (): void {
    expect(allocatedActivity([])->allocations()->count())->toBe(0);
    expect(allocatedActivity([User::factory()->employee()->create()], hours: '0.00')->allocations()->count())->toBe(0);

    $archived = allocatedActivity([User::factory()->employee()->create()]);
    $archived->update(['status' => WorkStatus::Archived, 'planned_hours' => '40.00']);
    app(GenerateActivityAllocations::class)->handle($archived);

    expect(round((float) $archived->allocations()->sum('hours'), 2))->toBe(10.0);
});

it('generates the quotas when an activity is created and updated through the app', function (): void {
    $boss = User::factory()->boss()->create();
    $user = User::factory()->employee()->create();
    $project = Project::factory()->create(['starts_on' => '2026-11-01', 'due_on' => '2026-12-31']);

    $this->actingAs($boss)->post(route('activities.store'), [
        'project_id' => $project->id,
        'name' => 'Layout',
        'starts_on' => '2026-11-09',
        'due_on' => '2026-11-10',
        'planned_hours' => '8',
        'status' => 'da_fare',
        'assignee_ids' => [$user->id],
    ])->assertSessionHasNoErrors();

    $activity = Activity::firstOrFail();
    expect($activity->allocations()->count())->toBe(2);

    $this->actingAs($boss)->put(route('activities.update', $activity), [
        'project_id' => $project->id,
        'name' => 'Layout',
        'starts_on' => '2026-11-09',
        'due_on' => '2026-11-13',
        'planned_hours' => '10',
        'status' => 'da_fare',
        'assignee_ids' => [$user->id],
    ])->assertSessionHasNoErrors();

    expect($activity->allocations()->count())->toBe(5)
        ->and(round((float) $activity->allocations()->sum('hours'), 2))->toBe(10.0);
});

it('recalculates when hr adds, moves or removes a holiday', function (): void {
    $hr = User::factory()->hr()->create();
    $activity = allocatedActivity([User::factory()->employee()->create()]);

    $this->actingAs($hr)->post(route('holidays.store'), ['date' => '2026-11-11', 'name' => 'Festa']);
    expect($activity->allocations()->count())->toBe(4)
        ->and($activity->allocations()->where('date', '2026-11-11')->exists())->toBeFalse();

    $holiday = Holiday::firstOrFail();
    $this->actingAs($hr)->put(route('holidays.update', $holiday), ['date' => '2026-11-12', 'name' => 'Festa']);
    expect($activity->allocations()->where('date', '2026-11-11')->exists())->toBeTrue()
        ->and($activity->allocations()->where('date', '2026-11-12')->exists())->toBeFalse();

    $this->actingAs($hr)->delete(route('holidays.destroy', $holiday));
    expect($activity->allocations()->count())->toBe(5);
});

it('recalculates only when an absence is confirmed, and again when a confirmed one is deleted', function (): void {
    $hr = User::factory()->hr()->create();
    $user = User::factory()->employee()->create();
    $other = User::factory()->employee()->create();
    $activity = allocatedActivity([$user]);
    $untouched = allocatedActivity([$other]);
    $untouchedIds = $untouched->allocations()->pluck('id')->all();

    $absence = Absence::factory()->for($user)->create(['starts_on' => '2026-11-09', 'ends_on' => '2026-11-10']);
    expect($activity->allocations()->count())->toBe(5);

    $this->actingAs($hr)->post(route('absences.confirm', $absence));
    expect($activity->allocations()->count())->toBe(3)
        ->and($activity->allocations()->where('user_id', $user->id)->where('date', '2026-11-09')->exists())->toBeFalse()
        ->and($untouched->allocations()->pluck('id')->all())->toBe($untouchedIds);

    $this->actingAs($hr)->delete(route('absences.destroy', $absence), ['reason' => 'Annullata']);
    expect($activity->allocations()->count())->toBe(5);
});

it('does not touch finished activities when the calendar changes', function (): void {
    $done = allocatedActivity([User::factory()->employee()->create()]);
    $done->update(['status' => WorkStatus::Done]);

    $this->actingAs(User::factory()->hr()->create())->post(route('holidays.store'), ['date' => '2026-11-11', 'name' => 'Festa']);

    expect($done->allocations()->count())->toBe(5);
});

it('rebuilds the quotas of the open activities with the artisan command', function (): void {
    $activity = allocatedActivity([User::factory()->employee()->create()]);
    ActivityAllocation::query()->delete();

    $this->artisan('allocations:regenerate')->expectsOutput('Quote ricalcolate per 1 attività.')->assertSuccessful();

    expect($activity->allocations()->count())->toBe(5);
});
