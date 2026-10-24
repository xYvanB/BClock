<?php

declare(strict_types=1);

use App\Actions\Allocations\GenerateActivityAllocations;
use App\Actions\Allocations\RegenerateAllocations;
use App\Enum\WorkStatus;
use App\Models\Activity;
use App\Models\ActivityAllocation;
use App\Models\Area;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Activity Mon 2026-11-09 … Fri 2026-11-13, 10 hours over 5 days (2 hours a day), in a project due on 2026-11-20.
 *
 * @param  list<User>  $assignees
 */
function movableActivity(array $assignees, string $hours = '10.00'): Activity
{
    $project = Project::factory()->create(['starts_on' => '2026-11-01', 'due_on' => '2026-11-20']);
    $activity = Activity::factory()->for($project)->create([
        'starts_on' => '2026-11-09',
        'due_on' => '2026-11-13',
        'planned_hours' => $hours,
        'status' => WorkStatus::Todo,
    ]);
    $activity->assignees()->attach(collect($assignees)->pluck('id'));
    app(GenerateActivityAllocations::class)->handle($activity);

    return $activity;
}

function quotaOn(Activity $activity, User $user, string $date): ActivityAllocation
{
    return $activity->allocations()->where('user_id', $user->id)->whereDate('date', $date)->firstOrFail();
}

function moveQuota(ActivityAllocation $quota, string $to, array $flags = []): array
{
    return ['date' => $to] + $flags;
}

it('moves the quota of one day onto another working day of the activity', function (): void {
    $user = User::factory()->employee()->create();
    $activity = movableActivity([$user]);
    $from = quotaOn($activity, $user, '2026-11-09');

    $this->actingAs(User::factory()->boss()->create())
        ->from(route('calendar.index'))
        ->post(route('allocations.move', $from), ['date' => '2026-11-11'])
        ->assertRedirect(route('calendar.index'))
        ->assertSessionHasNoErrors();

    // Wednesday has its own allocation and Monday's; Monday stays empty
    expect((float) quotaOn($activity, $user, '2026-11-11')->hours)->toBe(4.0)
        ->and((float) $from->refresh()->hours)->toBe(0.0)
        ->and(round((float) $activity->allocations()->sum('hours'), 2))->toBe(10.0)
        ->and($activity->refresh()->due_on->toDateString())->toBe('2026-11-13');
});

it('moves onto a day without a quota, creating it', function (): void {
    $user = User::factory()->employee()->create();
    $activity = movableActivity([$user]);
    $first = quotaOn($activity, $user, '2026-11-09');
    $this->actingAs(User::factory()->boss()->create())->post(route('allocations.move', $first), ['date' => '2026-11-10']);

    // Monday is now at 0: moving Tuesday onto Monday again fills it
    $tuesday = quotaOn($activity, $user, '2026-11-10');
    $this->actingAs(User::factory()->boss()->create())->post(route('allocations.move', $tuesday), ['date' => '2026-11-09'])->assertSessionHasNoErrors();

    expect((float) quotaOn($activity, $user, '2026-11-09')->hours)->toBe(4.0)
        ->and((float) $tuesday->refresh()->hours)->toBe(0.0)
        ->and($activity->allocations()->where('is_manual', true)->count())->toBe(2);
});

it('changes the load shown in the calendar and hides the emptied day', function (): void {
    $user = User::factory()->employee()->create();
    $activity = movableActivity([$user]);
    $this->actingAs(User::factory()->boss()->create())->post(route('allocations.move', quotaOn($activity, $user, '2026-11-09')), ['date' => '2026-11-11']);

    $this->actingAs(User::factory()->boss()->create())->get(route('calendar.index', ['date' => '2026-11-09']))
        ->assertInertia(fn (Assert $page) => $page->where('people', function ($people) use ($user): bool {
            $days = collect($people)->firstWhere('id', $user->id)['days'];

            return $days['2026-11-09']['load'] === 0 && $days['2026-11-09']['items'] === []
                && $days['2026-11-11']['load'] === 4 && $days['2026-11-11']['items'][0]['hours'] === 4;
        }));
});

it('needs a confirmation to go past the activity due date, and then extends it', function (): void {
    $user = User::factory()->employee()->create();
    $activity = movableActivity([$user]);
    $quota = quotaOn($activity, $user, '2026-11-09');
    $boss = User::factory()->boss()->create();

    $this->actingAs($boss)->post(route('allocations.move', $quota), ['date' => '2026-11-16'])->assertSessionHasErrors('extend_activity');
    expect($activity->refresh()->due_on->toDateString())->toBe('2026-11-13')
        ->and((float) $quota->refresh()->hours)->toBe(2.0);

    $this->actingAs($boss)->post(route('allocations.move', $quota), ['date' => '2026-11-16', 'extend_activity' => true])->assertSessionHasNoErrors();

    expect($activity->refresh()->due_on->toDateString())->toBe('2026-11-16')
        ->and($activity->project->refresh()->due_on->toDateString())->toBe('2026-11-20')
        ->and((float) quotaOn($activity, $user, '2026-11-16')->hours)->toBe(2.0);
});

it('needs a second confirmation to go past the project due date, and then extends both', function (): void {
    $user = User::factory()->employee()->create();
    $activity = movableActivity([$user]);
    $quota = quotaOn($activity, $user, '2026-11-09');
    $boss = User::factory()->boss()->create();

    $this->actingAs($boss)->post(route('allocations.move', $quota), ['date' => '2026-11-23', 'extend_activity' => true])
        ->assertSessionHasErrors('extend_project');
    expect($activity->refresh()->due_on->toDateString())->toBe('2026-11-13');

    $this->actingAs($boss)->post(route('allocations.move', $quota), ['date' => '2026-11-23', 'extend_activity' => true, 'extend_project' => true])
        ->assertSessionHasNoErrors();

    expect($activity->refresh()->due_on->toDateString())->toBe('2026-11-23')
        ->and($activity->project->refresh()->due_on->toDateString())->toBe('2026-11-23');
});

it('refuses weekends, holidays, the same day, days before the start and empty quotas', function (string $to, string $field): void {
    $user = User::factory()->employee()->create();
    $activity = movableActivity([$user]);
    $quota = quotaOn($activity, $user, '2026-11-09');
    Holiday::factory()->create(['date' => '2026-11-12']);

    $this->actingAs(User::factory()->boss()->create())
        ->post(route('allocations.move', $quota), ['date' => $to, 'extend_activity' => true, 'extend_project' => true])
        ->assertSessionHasErrors($field);

    expect((float) $quota->refresh()->hours)->toBe(2.0);
})->with([
    'saturday' => ['2026-11-14', 'date'],
    'holiday' => ['2026-11-12', 'date'],
    'same day' => ['2026-11-09', 'date'],
    'before the start' => ['2026-11-06', 'date'],
    'malformed' => ['09/11/2026', 'date'],
]);

it('refuses to move a quota that has no hours left', function (): void {
    $user = User::factory()->employee()->create();
    $activity = movableActivity([$user]);
    $quota = quotaOn($activity, $user, '2026-11-09');
    $boss = User::factory()->boss()->create();
    $this->actingAs($boss)->post(route('allocations.move', $quota), ['date' => '2026-11-10']);

    $this->actingAs($boss)->post(route('allocations.move', $quota->refresh()), ['date' => '2026-11-11'])->assertSessionHasErrors('date');
});

it('refuses finished and archived activities', function (WorkStatus $status): void {
    $user = User::factory()->employee()->create();
    $activity = movableActivity([$user]);
    $activity->update(['status' => $status]);

    $this->actingAs(User::factory()->boss()->create())
        ->post(route('allocations.move', quotaOn($activity, $user, '2026-11-09')), ['date' => '2026-11-10'])
        ->assertSessionHasErrors('date');
})->with([WorkStatus::Done, WorkStatus::Archived]);

it('applies the activity permissions: boss always, area head only on their own people, hr and employees never', function (): void {
    $area = Area::factory()->create();
    $head = User::factory()->areaHead($area)->create();
    $mine = User::factory()->employee($area)->create();
    $outsider = User::factory()->employee(Area::factory()->create())->create();

    $own = movableActivity([$mine]);
    $foreign = movableActivity([$outsider]);
    $mixed = movableActivity([$mine, $outsider]);

    $this->actingAs($head)->post(route('allocations.move', quotaOn($own, $mine, '2026-11-09')), ['date' => '2026-11-10'])->assertSessionHasNoErrors();
    $this->actingAs($head)->post(route('allocations.move', quotaOn($foreign, $outsider, '2026-11-09')), ['date' => '2026-11-10'])->assertForbidden();
    $this->actingAs($head)->post(route('allocations.move', quotaOn($mixed, $mine, '2026-11-09')), ['date' => '2026-11-10'])->assertForbidden();

    foreach ([User::factory()->hr()->create(), $mine] as $user) {
        $this->actingAs($user)->post(route('allocations.move', quotaOn($foreign, $outsider, '2026-11-09')), ['date' => '2026-11-10'])->assertForbidden();
    }
    $this->actingAs(User::factory()->boss()->create())->post(route('allocations.move', quotaOn($foreign, $outsider, '2026-11-09')), ['date' => '2026-11-10'])->assertSessionHasNoErrors();
});

it('answers 403, not a validation error, when someone who cannot move sends an invalid date', function (): void {
    $user = User::factory()->employee()->create();
    $quota = quotaOn(movableActivity([$user]), $user, '2026-11-09');

    $this->actingAs($user)->post(route('allocations.move', $quota), [])->assertForbidden();
});

it('keeps the moved quotas when the activity is regenerated, and spreads only the remaining hours', function (): void {
    $user = User::factory()->employee()->create();
    $activity = movableActivity([$user]);
    $this->actingAs(User::factory()->boss()->create())->post(route('allocations.move', quotaOn($activity, $user, '2026-11-09')), ['date' => '2026-11-11']);

    app(RegenerateAllocations::class)->handle();

    expect((float) quotaOn($activity, $user, '2026-11-09')->hours)->toBe(0.0)
        ->and((float) quotaOn($activity, $user, '2026-11-11')->hours)->toBe(4.0)
        ->and(round((float) $activity->allocations()->sum('hours'), 2))->toBe(10.0)
        ->and($activity->allocations()->where('is_manual', false)->count())->toBe(3);
});

it('keeps them when the planned hours change, and drops them when the person or the day leaves the activity', function (): void {
    $anna = User::factory()->employee()->create();
    $luca = User::factory()->employee()->create();
    $activity = movableActivity([$anna, $luca]);
    $boss = User::factory()->boss()->create();
    $this->actingAs($boss)->post(route('allocations.move', quotaOn($activity, $anna, '2026-11-09')), ['date' => '2026-11-10']);
    $this->actingAs($boss)->post(route('allocations.move', quotaOn($activity, $luca, '2026-11-13')), ['date' => '2026-11-12']);

    $activity->update(['planned_hours' => '20.00']);
    app(GenerateActivityAllocations::class)->handle($activity);
    expect((float) quotaOn($activity, $anna, '2026-11-10')->hours)->toBe(2.0)
        ->and(round((float) $activity->allocations()->sum('hours'), 2))->toBe(20.0);

    // Luca leaves the activity: his manual allocations disappear and the hours go back to the others
    $activity->assignees()->detach($luca);
    app(GenerateActivityAllocations::class)->handle($activity);
    expect($activity->allocations()->where('user_id', $luca->id)->exists())->toBeFalse()
        ->and(round((float) $activity->allocations()->sum('hours'), 2))->toBe(20.0);

    // the activity shortens to the 10th: allocations outside the period disappear and, if the manual ones fill every remaining day, all the hours still come back
    $activity->update(['due_on' => '2026-11-10']);
    app(GenerateActivityAllocations::class)->handle($activity);
    expect($activity->allocations()->whereDate('date', '>', '2026-11-10')->exists())->toBeFalse()
        ->and(round((float) $activity->allocations()->sum('hours'), 2))->toBe(20.0);
});

it('exposes to the calendar what is needed to move a quota', function (): void {
    $user = User::factory()->employee()->create();
    $activity = movableActivity([$user]);

    $this->actingAs(User::factory()->boss()->create())->get(route('calendar.index', ['date' => '2026-11-09']))
        ->assertInertia(fn (Assert $page) => $page->where('people', function ($people) use ($user, $activity): bool {
            $item = collect($people)->firstWhere('id', $user->id)['days']['2026-11-09']['items'][0];

            return $item['can_move'] === true
                && $item['activity_id'] === $activity->id
                && $item['activity_starts_on'] === '2026-11-09'
                && $item['activity_due_on'] === '2026-11-13'
                && $item['project_due_on'] === '2026-11-20'
                && is_int($item['allocation_id']);
        }));

    $this->actingAs(User::factory()->hr()->create())->get(route('calendar.index', ['date' => '2026-11-09']))
        ->assertInertia(fn (Assert $page) => $page->where('people', fn ($people) => collect($people)->firstWhere('id', $user->id)['days']['2026-11-09']['items'][0]['can_move'] === false));
});

it('moves only some of the hours, leaving the rest on the original day', function (): void {
    $user = User::factory()->employee()->create();
    $activity = movableActivity([$user]);
    $quota = quotaOn($activity, $user, '2026-11-09');

    $this->actingAs(User::factory()->boss()->create())
        ->post(route('allocations.move', $quota), ['date' => '2026-11-11', 'hours' => '0.5'])
        ->assertSessionHasNoErrors();

    expect((float) $quota->refresh()->hours)->toBe(1.5)
        ->and((float) quotaOn($activity, $user, '2026-11-11')->hours)->toBe(2.5)
        ->and(round((float) $activity->allocations()->sum('hours'), 2))->toBe(10.0)
        ->and($quota->is_manual)->toBeTrue();
});

it('moves part of the hours several times, always summing them on the destination', function (): void {
    $user = User::factory()->employee()->create();
    $activity = movableActivity([$user]);
    $quota = quotaOn($activity, $user, '2026-11-09');
    $boss = User::factory()->boss()->create();

    $this->actingAs($boss)->post(route('allocations.move', $quota), ['date' => '2026-11-10', 'hours' => '0.75'])->assertSessionHasNoErrors();
    $this->actingAs($boss)->post(route('allocations.move', $quota->refresh()), ['date' => '2026-11-10', 'hours' => '1.25'])->assertSessionHasNoErrors();

    expect((float) $quota->refresh()->hours)->toBe(0.0)
        ->and((float) quotaOn($activity, $user, '2026-11-10')->hours)->toBe(4.0);
});

it('refuses hours that are zero, negative or more than the quota, and moves nothing', function (string $hours): void {
    $user = User::factory()->employee()->create();
    $activity = movableActivity([$user]);
    $quota = quotaOn($activity, $user, '2026-11-09');

    $this->actingAs(User::factory()->boss()->create())
        ->post(route('allocations.move', $quota), ['date' => '2026-11-11', 'hours' => $hours])
        ->assertSessionHasErrors('hours');

    expect((float) $quota->refresh()->hours)->toBe(2.0)
        ->and((float) quotaOn($activity, $user, '2026-11-11')->hours)->toBe(2.0);
})->with(['0', '-1', '2.5', 'molte']);

it('does not extend the activity when the hours are refused', function (): void {
    $user = User::factory()->employee()->create();
    $activity = movableActivity([$user]);
    $quota = quotaOn($activity, $user, '2026-11-09');

    $this->actingAs(User::factory()->boss()->create())
        ->post(route('allocations.move', $quota), ['date' => '2026-11-16', 'extend_activity' => true, 'hours' => '9'])
        ->assertSessionHasErrors('hours');

    expect($activity->refresh()->due_on->toDateString())->toBe('2026-11-13');
});
