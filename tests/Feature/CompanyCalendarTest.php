<?php

declare(strict_types=1);

use App\Actions\Allocations\CalculateAvailability;
use App\Actions\Allocations\GenerateActivityAllocations;
use App\Enum\HolidayKind;
use App\Models\Absence;
use App\Models\Activity;
use App\Models\Holiday;
use App\Models\Setting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Monday 2026-11-09 … Friday 13, Saturday 14, Sunday 15.
 *
 * @return array<string, string>
 */
function weekPayload(array $overrides = []): array
{
    return array_merge(['type' => 'ferie', 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-09', 'note' => ''], $overrides);
}

it('lets only the boss and hr see and change the company calendar', function (User $user, bool $allowed): void {
    $page = $this->actingAs($user)->get(route('company-calendar.edit'));
    $save = $this->actingAs($user)->put(route('company-calendar.update'), ['weekdays' => [1, 2, 3]]);

    if ($allowed) {
        $page->assertInertia(fn (Assert $inertia) => $inertia->component('settings/company-calendar')->where('weekdays', [1, 2, 3, 4, 5])->has('closures', 0));
        expect(Setting::workingWeekdays())->toBe([1, 2, 3]);
    } else {
        $page->assertForbidden();
        $save->assertForbidden();
        expect(Setting::workingWeekdays())->toBe([1, 2, 3, 4, 5]);
    }
})->with([
    'boss' => [fn () => User::factory()->boss()->create(), true],
    'hr' => [fn () => User::factory()->hr()->create(), true],
    'area head' => [fn () => User::factory()->areaHead()->create(), false],
    'employee' => [fn () => User::factory()->employee()->create(), false],
]);

it('validates the working weekdays', function (array $payload): void {
    $this->actingAs(User::factory()->hr()->create())->put(route('company-calendar.update'), $payload)->assertSessionHasErrors('weekdays');
})->with([
    'none' => [[]],
    'empty list' => [['weekdays' => []]],
]);

it('rejects out of range or repeated weekdays', function (array $weekdays): void {
    $this->actingAs(User::factory()->hr()->create())->put(route('company-calendar.update'), ['weekdays' => $weekdays])->assertSessionHasErrors('weekdays.1');
})->with([
    'zero' => [[1, 0]],
    'eight' => [[1, 8]],
    'repeated' => [[1, 1]],
]);

it('counts only the chosen weekdays as working days', function (): void {
    $user = User::factory()->employee()->create();
    $this->actingAs(User::factory()->hr()->create())->put(route('company-calendar.update'), ['weekdays' => [1, 2, 3, 4, 6]]);

    $availability = app(CalculateAvailability::class)->handle([$user->id], '2026-11-09', '2026-11-15');

    // Friday at home, Saturday at work
    expect($availability['working_days'])->toBe(['2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12', '2026-11-14'])
        ->and($availability['hours'][$user->id]['2026-11-13'])->toBe(0.0)
        ->and($availability['hours'][$user->id]['2026-11-14'])->toBe(8.0);
});

it('recalculates the quotas of open activities when the working days change', function (): void {
    $user = User::factory()->employee()->create();
    $activity = Activity::factory()->create(['starts_on' => '2026-11-09', 'due_on' => '2026-11-15', 'planned_hours' => '12.00']);
    $activity->assignees()->attach($user);
    app(GenerateActivityAllocations::class)->handle($activity);
    expect($activity->allocations()->count())->toBe(5);

    $this->actingAs(User::factory()->boss()->create())->put(route('company-calendar.update'), ['weekdays' => [1, 2, 3, 4, 5, 6]]);

    expect($activity->allocations()->count())->toBe(6)
        ->and(round((float) $activity->allocations()->sum('hours'), 2))->toBe(12.0);
});

it('refuses a leave on a day off, whatever the reason', function (string $date, array $holiday, array $weekdays): void {
    if ($holiday !== []) {
        Holiday::factory()->create($holiday);
    }
    if ($weekdays !== []) {
        Setting::write(Setting::WORKING_WEEKDAYS, implode(',', $weekdays));
    }

    $this->actingAs(User::factory()->employee()->create())
        ->post(route('absences.store'), weekPayload(['type' => 'permesso', 'starts_on' => $date, 'ends_on' => $date, 'starts_time' => '09:00', 'ends_time' => '11:00']))
        ->assertSessionHasErrors('starts_on');

    expect(Absence::count())->toBe(0);
})->with([
    'saturday' => ['2026-11-14', [], []],
    'a holiday' => ['2026-11-11', ['date' => '2026-11-11', 'name' => 'Festa', 'kind' => HolidayKind::Holiday], []],
    'an extra closure' => ['2026-11-12', ['date' => '2026-11-12', 'name' => 'Ponte', 'kind' => HolidayKind::Closure], []],
    'a weekday the company does not work' => ['2026-11-13', [], [1, 2, 3, 4]],
]);

it('accepts a leave on a day that became a working day', function (): void {
    Setting::write(Setting::WORKING_WEEKDAYS, '1,2,3,4,5,6');

    $this->actingAs(User::factory()->employee()->create())
        ->post(route('absences.store'), weekPayload(['type' => 'permesso', 'starts_on' => '2026-11-14', 'ends_on' => '2026-11-14', 'starts_time' => '09:00', 'ends_time' => '11:00']))
        ->assertSessionHasNoErrors();
});

it('refuses a vacation made only of days off, but accepts one that spans a weekend', function (): void {
    Holiday::factory()->create(['date' => '2026-11-16', 'name' => 'Ponte', 'kind' => HolidayKind::Closure]);
    $user = User::factory()->employee()->create();

    $this->actingAs($user)->post(route('absences.store'), weekPayload(['starts_on' => '2026-11-14', 'ends_on' => '2026-11-16']))
        ->assertSessionHasErrors('starts_on');

    $this->actingAs($user)->post(route('absences.store'), weekPayload(['starts_on' => '2026-11-13', 'ends_on' => '2026-11-17']))
        ->assertSessionHasNoErrors();
});

it('creates a closure period as one day per working day, skipping weekends and days already closed', function (): void {
    Holiday::factory()->create(['date' => '2026-12-09', 'name' => 'Già chiuso']);
    $this->actingAs(User::factory()->hr()->create())
        ->post(route('holidays.store'), ['date' => '2026-12-07', 'until' => '2026-12-14', 'name' => 'Ponte di dicembre', 'kind' => 'chiusura'])
        ->assertSessionHasNoErrors();

    $created = Holiday::where('name', 'Ponte di dicembre')->orderBy('date')->get();

    expect($created->pluck('date')->map->toDateString()->all())->toBe(['2026-12-07', '2026-12-08', '2026-12-10', '2026-12-11', '2026-12-14'])
        ->and($created->pluck('kind')->unique()->all())->toBe([HolidayKind::Closure]);
});

it('validates a closure period', function (array $payload, string $field): void {
    $this->actingAs(User::factory()->hr()->create())
        ->post(route('holidays.store'), $payload + ['name' => 'Chiusura', 'kind' => 'chiusura'])
        ->assertSessionHasErrors($field);
})->with([
    'end before start' => [['date' => '2026-12-10', 'until' => '2026-12-05'], 'until'],
    'same day' => [['date' => '2026-12-10', 'until' => '2026-12-10'], 'until'],
    'too long' => [['date' => '2026-12-01', 'until' => '2027-04-01'], 'until'],
    'unknown kind' => [['date' => '2026-12-10', 'kind' => 'ferie'], 'kind'],
]);

it('does not accept a period when editing a day', function (): void {
    $holiday = Holiday::factory()->create(['date' => '2026-12-25']);

    $this->actingAs(User::factory()->hr()->create())
        ->put(route('holidays.update', $holiday), ['date' => '2026-12-25', 'name' => 'Natale', 'kind' => 'festivo', 'until' => '2026-12-31'])
        ->assertSessionHasErrors('until');
});

it('changes the kind of a day and recalculates the quotas around it', function (): void {
    $user = User::factory()->employee()->create();
    $activity = Activity::factory()->create(['starts_on' => '2026-11-09', 'due_on' => '2026-11-13', 'planned_hours' => '10.00']);
    $activity->assignees()->attach($user);
    app(GenerateActivityAllocations::class)->handle($activity);

    $this->actingAs(User::factory()->hr()->create())
        ->post(route('holidays.store'), ['date' => '2026-11-11', 'name' => 'Ponte', 'kind' => 'chiusura'])
        ->assertSessionHasNoErrors();

    expect($activity->allocations()->count())->toBe(4)
        ->and(Holiday::firstOrFail()->kind)->toBe(HolidayKind::Closure);
});

it('shares the calendar with the pages and shows the kind of each day', function (): void {
    Holiday::factory()->create(['date' => '2026-12-07', 'name' => 'Ponte', 'kind' => HolidayKind::Closure]);
    Setting::write(Setting::WORKING_WEEKDAYS, '1,2,3,4');

    $this->actingAs(User::factory()->employee()->create())->get(route('absences.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('calendar.working_weekdays', [1, 2, 3, 4])
            ->where('calendar.closures', ['2026-12-07'])
            ->where('holidays.0.kind', 'chiusura'));
});

it('marks the non working weekdays in the calendar', function (): void {
    Setting::write(Setting::WORKING_WEEKDAYS, '1,2,3,4');

    $this->actingAs(User::factory()->boss()->create())->get(route('calendar.index', ['date' => '2026-11-09']))
        ->assertInertia(fn (Assert $page) => $page->where('days.3.is_off', false)->where('days.4.is_off', true)->where('days.5.is_off', true));
});
