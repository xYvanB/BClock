<?php

declare(strict_types=1);

use App\Actions\Allocations\CalculateAvailability;
use App\Actions\Allocations\CalculateDailyLoad;
use App\Actions\Allocations\GenerateActivityAllocations;
use App\Enum\LoadLevel;
use App\Enum\WorkStatus;
use App\Models\Absence;
use App\Models\Activity;
use App\Models\Holiday;
use App\Models\User;

/**
 * Monday 2026-11-09 … Friday 2026-11-13; Saturday 14 and Sunday 15.
 *
 * @param  list<User>  $assignees
 */
function loadActivity(array $assignees, string $hours, string $start = '2026-11-09', string $due = '2026-11-13', WorkStatus $status = WorkStatus::Todo): Activity
{
    $activity = Activity::factory()->create([
        'starts_on' => $start,
        'due_on' => $due,
        'planned_hours' => $hours,
        'status' => $status,
    ]);
    $activity->assignees()->attach(collect($assignees)->pluck('id'));

    app(GenerateActivityAllocations::class)->handle($activity);

    return $activity;
}

/**
 * @return array{capacity: float, load: float, level: LoadLevel|null}
 */
function loadOf(User $user, string $day): array
{
    return app(CalculateDailyLoad::class)->handle([$user->id], $day, $day)[$user->id][$day];
}

it('has the full capacity of the person on a normal working day', function (): void {
    $user = User::factory()->employee()->create();

    $availability = app(CalculateAvailability::class)->handle([$user->id], '2026-11-09', '2026-11-15');

    expect($availability['working_days'])->toBe(['2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13'])
        ->and($availability['hours'][$user->id]['2026-11-09'])->toBe(8.0)
        ->and($availability['hours'][$user->id]['2026-11-14'])->toBe(0.0);
});

it('uses the daily capacity of each person', function (): void {
    $part = User::factory()->employee()->create(['daily_capacity' => 4]);

    expect(app(CalculateAvailability::class)->handle([$part->id], '2026-11-09', '2026-11-09')['hours'][$part->id]['2026-11-09'])->toBe(4.0);
});

it('has no availability on holidays, and on confirmed vacations only', function (): void {
    $user = User::factory()->employee()->create();
    Holiday::factory()->create(['date' => '2026-11-10']);
    Absence::factory()->for($user)->confirmed()->create(['starts_on' => '2026-11-11', 'ends_on' => '2026-11-11']);
    Absence::factory()->for($user)->create(['starts_on' => '2026-11-12', 'ends_on' => '2026-11-12']);

    $hours = app(CalculateAvailability::class)->handle([$user->id], '2026-11-09', '2026-11-13')['hours'][$user->id];

    expect($hours)->toBe(['2026-11-09' => 8.0, '2026-11-10' => 0.0, '2026-11-11' => 0.0, '2026-11-12' => 8.0, '2026-11-13' => 8.0]);
});

it('takes a confirmed leave off the day, but not a pending one', function (): void {
    $user = User::factory()->employee()->create();
    Absence::factory()->for($user)->leave('2026-11-09', '09:00', '11:00')->confirmed()->create();
    Absence::factory()->for($user)->leave('2026-11-10', '09:00', '11:00')->create();

    $hours = app(CalculateAvailability::class)->handle([$user->id], '2026-11-09', '2026-11-10')['hours'][$user->id];

    expect($hours)->toBe(['2026-11-09' => 6.0, '2026-11-10' => 8.0]);
});

it('gives each load level: under capacity, full, overloaded', function (): void {
    $user = User::factory()->employee()->create();
    loadActivity([$user], '5.00', '2026-11-09', '2026-11-09');
    loadActivity([$user], '8.00', '2026-11-10', '2026-11-10');
    loadActivity([$user], '9.50', '2026-11-11', '2026-11-11');

    expect(loadOf($user, '2026-11-09'))->toMatchArray(['capacity' => 8.0, 'load' => 5.0, 'level' => LoadLevel::Under])
        ->and(loadOf($user, '2026-11-10')['level'])->toBe(LoadLevel::Full)
        ->and(loadOf($user, '2026-11-11'))->toMatchArray(['load' => 9.5, 'level' => LoadLevel::Over]);
});

it('sums the quotas of several activities on the same day', function (): void {
    $user = User::factory()->employee()->create();
    loadActivity([$user], '4.00', '2026-11-09', '2026-11-09');
    loadActivity([$user], '4.00', '2026-11-09', '2026-11-09');
    loadActivity([$user], '1.00', '2026-11-09', '2026-11-09');

    expect(loadOf($user, '2026-11-09'))->toMatchArray(['load' => 9.0, 'level' => LoadLevel::Over]);
});

it('does not count finished or archived activities, nor other people', function (): void {
    $user = User::factory()->employee()->create();
    $other = User::factory()->employee()->create();
    loadActivity([$user], '4.00', '2026-11-09', '2026-11-09', WorkStatus::Done);
    $archived = loadActivity([$user], '4.00', '2026-11-09', '2026-11-09');
    $archived->update(['status' => WorkStatus::Archived]);
    loadActivity([$other], '8.00', '2026-11-09', '2026-11-09');
    loadActivity([$user], '2.00', '2026-11-09', '2026-11-09');

    expect(loadOf($user, '2026-11-09'))->toMatchArray(['load' => 2.0, 'level' => LoadLevel::Under]);
});

it('has no level on a free day without work, and stays overloaded when work is assigned on a day off', function (): void {
    $user = User::factory()->employee()->create();

    expect(loadOf($user, '2026-11-14'))->toBe(['capacity' => 0.0, 'load' => 0.0, 'level' => null]);

    // activity only on the weekend: the hours fall back onto the two days
    loadActivity([$user], '4.00', '2026-11-14', '2026-11-15');

    expect(loadOf($user, '2026-11-14'))->toMatchArray(['capacity' => 0.0, 'load' => 2.0, 'level' => LoadLevel::Over]);
});

it('lowers the capacity to match a confirmed leave, which can make a day full or overloaded', function (): void {
    $user = User::factory()->employee()->create();
    loadActivity([$user], '8.00', '2026-11-09', '2026-11-09');
    expect(loadOf($user, '2026-11-09')['level'])->toBe(LoadLevel::Full);

    $absence = Absence::factory()->for($user)->leave('2026-11-09', '09:00', '13:00')->create();
    expect(loadOf($user, '2026-11-09')['level'])->toBe(LoadLevel::Full);

    // the confirmation recalculates the allocations: the work moves to the other days of the activity, here nobody
    $this->actingAs(User::factory()->hr()->create())->post(route('absences.confirm', $absence));

    expect(loadOf($user, '2026-11-09'))->toMatchArray(['capacity' => 4.0, 'load' => 8.0, 'level' => LoadLevel::Over]);
});

it('computes the load of many people over a range in one call', function (): void {
    $anna = User::factory()->employee()->create();
    $luca = User::factory()->employee()->create();
    loadActivity([$anna], '10.00');

    $load = app(CalculateDailyLoad::class)->handle([$anna->id, $luca->id], '2026-11-09', '2026-11-15');

    expect($load)->toHaveCount(2)
        ->and($load[$anna->id])->toHaveCount(7)
        ->and($load[$anna->id]['2026-11-12']['level'])->toBe(LoadLevel::Under)
        ->and($load[$luca->id]['2026-11-12']['load'])->toBe(0.0)
        ->and($load[$luca->id]['2026-11-12']['level'])->toBe(LoadLevel::Under)
        ->and($load[$luca->id]['2026-11-14']['level'])->toBeNull();
});
