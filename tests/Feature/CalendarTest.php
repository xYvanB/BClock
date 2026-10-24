<?php

declare(strict_types=1);

use App\Actions\Allocations\GenerateActivityAllocations;
use App\Enum\WorkStatus;
use App\Models\Absence;
use App\Models\Activity;
use App\Models\Area;
use App\Models\Holiday;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Attività assegnata alla persona, con le sue quote giornaliere.
 */
function calendarActivity(User $user, string $hours, string $start = '2026-11-09', string $due = '2026-11-13', WorkStatus $status = WorkStatus::Todo): Activity
{
    $activity = Activity::factory()->create(['starts_on' => $start, 'due_on' => $due, 'planned_hours' => $hours, 'status' => $status]);
    $activity->assignees()->attach($user);
    app(GenerateActivityAllocations::class)->handle($activity);

    return $activity;
}

it('shows the week of the given date, Monday to Sunday, with weekends and holidays flagged', function (): void {
    Holiday::factory()->create(['date' => '2026-11-11', 'name' => 'San Martino']);

    $this->actingAs(User::factory()->boss()->create())
        ->get(route('calendar.index', ['date' => '2026-11-12']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('calendar/index')
            ->where('view', 'week')
            ->where('from', '2026-11-09')
            ->where('to', '2026-11-15')
            ->where('previous', '2026-11-05')
            ->where('next', '2026-11-19')
            ->has('days', 7)
            ->where('days.2.holiday', 'San Martino')
            ->where('days.5.is_weekend', true)
            ->where('days.0.is_weekend', false));
});

it('shows the whole month in month view', function (): void {
    $this->actingAs(User::factory()->boss()->create())
        ->get(route('calendar.index', ['view' => 'month', 'date' => '2026-11-12']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('view', 'month')
            ->where('from', '2026-11-01')
            ->where('to', '2026-11-30')
            ->where('previous', '2026-10-12')
            ->where('next', '2026-12-12')
            ->has('days', 30));
});

it('falls back to the current week on a missing or invalid date', function (string $date): void {
    $this->actingAs(User::factory()->boss()->create())
        ->get(route('calendar.index', ['date' => $date]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('date', now(config('app.local_timezone'))->toDateString()));
})->with(['', 'ieri', '2026-02-31', '31/12/2026']);

it('gives each person a cell per day with load, capacity, level and the activities', function (): void {
    $user = User::factory()->employee()->create();
    $activity = calendarActivity($user, '5.00');

    $this->actingAs(User::factory()->boss()->create())
        ->get(route('calendar.index', ['date' => '2026-11-09']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('people', fn ($people) => collect($people)->firstWhere('id', $user->id)['days']['2026-11-09']['level'] === 'sotto_capacita')
            ->where('people', function ($people) use ($user, $activity): bool {
                $cell = collect($people)->firstWhere('id', $user->id)['days']['2026-11-09'];

                return $cell['load'] === 1 && $cell['capacity'] === 8
                    && $cell['items'][0]['activity'] === $activity->name
                    && $cell['items'][0]['project'] === $activity->project->name
                    && collect($people)->firstWhere('id', $user->id)['days']['2026-11-14']['level'] === null;
            }));
});

it('flags the confirmed absences and not the pending ones', function (): void {
    $user = User::factory()->employee()->create();
    Absence::factory()->for($user)->confirmed()->create(['starts_on' => '2026-11-10', 'ends_on' => '2026-11-11']);
    Absence::factory()->for($user)->leave('2026-11-12', '09:00', '11:00')->confirmed()->create();
    Absence::factory()->for($user)->create(['starts_on' => '2026-11-13', 'ends_on' => '2026-11-13']);

    $this->actingAs(User::factory()->boss()->create())
        ->get(route('calendar.index', ['date' => '2026-11-09']))
        ->assertInertia(fn (Assert $page) => $page->where('people', function ($people) use ($user): bool {
            $days = collect($people)->firstWhere('id', $user->id)['days'];

            return $days['2026-11-09']['absence'] === null
                && $days['2026-11-10']['absence'] === 'ferie'
                && $days['2026-11-11']['absence'] === 'ferie'
                && $days['2026-11-12']['absence'] === 'permesso'
                && $days['2026-11-12']['capacity'] === 6
                && $days['2026-11-13']['absence'] === null;
        }));
});

it('does not show finished or archived activities', function (): void {
    $user = User::factory()->employee()->create();
    calendarActivity($user, '8.00', '2026-11-09', '2026-11-09', WorkStatus::Done);

    $this->actingAs(User::factory()->boss()->create())
        ->get(route('calendar.index', ['date' => '2026-11-09']))
        ->assertInertia(fn (Assert $page) => $page->where('people', function ($people) use ($user): bool {
            $cell = collect($people)->firstWhere('id', $user->id)['days']['2026-11-09'];

            return $cell['load'] === 0 && $cell['items'] === [];
        }));
});

it('shows the boss and hr everybody, and lets them filter by area', function (User $viewer): void {
    $design = Area::factory()->create();
    $dev = Area::factory()->create();
    $inDesign = User::factory()->employee($design)->create();
    $inDev = User::factory()->employee($dev)->create();

    $this->actingAs($viewer)->get(route('calendar.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('areas', 2)
            ->where('people', fn ($people) => collect($people)->pluck('id')->contains($inDesign->id) && collect($people)->pluck('id')->contains($inDev->id)));

    $this->actingAs($viewer)->get(route('calendar.index', ['area_id' => $design->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('area_id', $design->id)
            ->where('people', fn ($people) => collect($people)->pluck('id')->all() === [$inDesign->id]));
})->with([
    'boss' => fn () => User::factory()->boss()->create(),
    'hr' => fn () => User::factory()->hr()->create(),
]);

it('shows the area head only their area, ignoring the area filter', function (): void {
    $area = Area::factory()->create();
    $head = User::factory()->areaHead($area)->create();
    $mine = User::factory()->employee($area)->create();
    $outsider = User::factory()->employee(Area::factory()->create())->create();

    $this->actingAs($head)->get(route('calendar.index', ['area_id' => $outsider->area_id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('areas', 0)
            ->where('people', function ($people) use ($head, $mine, $outsider): bool {
                $ids = collect($people)->pluck('id');

                return $ids->contains($head->id) && $ids->contains($mine->id) && ! $ids->contains($outsider->id);
            }));
});

it('shows an employee only themselves', function (): void {
    $area = Area::factory()->create();
    $me = User::factory()->employee($area)->create();
    User::factory()->employee($area)->create();

    $this->actingAs($me)->get(route('calendar.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('people', 1)
            ->where('people.0.id', $me->id)
            ->where('people.0.is_self', true)
            ->has('areas', 0));
});

it('requires a logged-in user', function (): void {
    $this->get(route('calendar.index'))->assertRedirect(route('login'));
});
