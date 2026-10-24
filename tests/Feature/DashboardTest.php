<?php

declare(strict_types=1);

use App\Models\Absence;
use App\Models\Activity;
use App\Models\Area;
use App\Models\Client;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Registrazione di `$minutes` minuti della persona sull'attività, il giorno indicato.
 */
function logHours(User $user, Activity $activity, string $date, int $minutes = 120, string $start = '09:00'): TimeEntry
{
    return TimeEntry::factory()->create([
        'user_id' => $user->id,
        'activity_id' => $activity->id,
        'date' => $date,
        'started_at' => $start.':00',
        'ended_at' => date('H:i:s', strtotime($start) + $minutes * 60),
        'duration_minutes' => $minutes,
    ]);
}

function dashboardProject(string $name = 'Sito', float $planned = 10): Project
{
    return Project::factory()->for(Client::factory())->create(['name' => $name, 'planned_hours' => $planned]);
}

function dashboardActivity(Project $project, string $name = 'Layout'): Activity
{
    return Activity::factory()->for($project)->create(['name' => $name, 'planned_hours' => 6]);
}

it('shows each role the cards it is entitled to', function (User $user, bool $projectCards): void {
    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('project_hours', fn ($value) => ($value !== null) === $projectCards)
            ->where('day_hours', fn ($value) => ($value !== null) === $projectCards)
            ->has('worked_hours'));
})->with([
    'boss' => [fn () => User::factory()->boss()->create(), true],
    'area head' => [fn () => User::factory()->areaHead()->create(), true],
    'employee' => [fn () => User::factory()->employee()->create(), true],
    'hr' => [fn () => User::factory()->hr()->create(), false],
]);

it('defaults to the current month and caps the period', function (): void {
    $this->actingAs(User::factory()->boss()->create())->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.from', now(config('app.local_timezone'))->startOfMonth()->toDateString())
            ->where('filters.to', now(config('app.local_timezone'))->endOfMonth()->toDateString())
            ->where('filters.day', now(config('app.local_timezone'))->toDateString()));

    $this->actingAs(User::factory()->boss()->create())->get(route('dashboard', ['from' => '2020-01-01', 'to' => '2030-01-01']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.to', '2020-12-31'));

    $this->actingAs(User::factory()->boss()->create())->get(route('dashboard', ['from' => '2026-11-10', 'to' => '2026-11-01']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.to', '2026-11-10'));

    $this->actingAs(User::factory()->boss()->create())->get(route('dashboard', ['from' => '2026-02-31', 'to' => 'x']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.from', now(config('app.local_timezone'))->startOfMonth()->toDateString()));
});

it('sums the hours per project, in the period and in total, against the planned ones', function (): void {
    $user = User::factory()->employee()->create();
    $project = dashboardProject('Sito', 10);
    $activity = dashboardActivity($project);
    logHours($user, $activity, '2026-11-10', 120);
    logHours($user, $activity, '2026-11-11', 60);
    logHours($user, $activity, '2026-10-01', 180);
    logHours($user, dashboardActivity(dashboardProject('Altro'), 'X'), '2026-12-15', 60);

    $this->actingAs(User::factory()->boss()->create())
        ->get(route('dashboard', ['from' => '2026-11-01', 'to' => '2026-11-30']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('project_hours.total_hours', 3)
            ->has('project_hours.projects', 1)
            ->where('project_hours.projects.0.name', 'Sito')
            ->where('project_hours.projects.0.period_hours', 3)
            ->where('project_hours.projects.0.total_hours', 6)
            ->where('project_hours.projects.0.planned_hours', 10)
            ->has('project_hours.options', 2)
            ->where('project_hours.selected', null));
});

it('breaks one project down by activity and by person', function (): void {
    $anna = User::factory()->employee()->create(['name' => 'Anna', 'surname' => 'Bianchi']);
    $luca = User::factory()->employee()->create(['name' => 'Luca', 'surname' => 'Rossi']);
    $project = dashboardProject();
    $layout = dashboardActivity($project, 'Layout');
    $code = dashboardActivity($project, 'Codice');
    logHours($anna, $layout, '2026-11-10', 120);
    logHours($anna, $code, '2026-11-10', 60, '14:00');
    logHours($luca, $code, '2026-11-12', 30);

    $this->actingAs(User::factory()->boss()->create())
        ->get(route('dashboard', ['from' => '2026-11-01', 'to' => '2026-11-30', 'project_id' => $project->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('project_hours.selected.project_id', $project->id)
            ->where('project_hours.selected.activities', fn ($rows) => collect($rows)->pluck('hours', 'name')->all() === ['Codice' => 1.5, 'Layout' => 2])
            ->where('project_hours.selected.people', fn ($rows) => collect($rows)->pluck('hours', 'name')->all() === ['Anna Bianchi' => 3, 'Luca Rossi' => 0.5]));
});

it('limits the data to what the viewer can see: all, the area, or only their own', function (): void {
    $area = Area::factory()->create();
    $head = User::factory()->areaHead($area)->create();
    $mine = User::factory()->employee($area)->create();
    $outsider = User::factory()->employee(Area::factory()->create())->create();
    $activity = dashboardActivity(dashboardProject());
    logHours($mine, $activity, '2026-11-10', 60);
    logHours($outsider, $activity, '2026-11-10', 120);

    $total = fn (User $user) => $this->actingAs($user)->get(route('dashboard', ['from' => '2026-11-01', 'to' => '2026-11-30']))
        ->viewData('page')['props']['project_hours']['total_hours'];

    expect($total(User::factory()->boss()->create()))->toEqual(3)
        ->and($total($head))->toEqual(1)
        ->and($total($mine))->toEqual(1)
        ->and($total($outsider))->toEqual(2)
        ->and($total(User::factory()->employee()->create()))->toEqual(0);
});

it('lists the registrations of a day per person, against the capacity of that day', function (): void {
    Holiday::factory()->create(['date' => '2026-11-12']);
    $anna = User::factory()->employee()->create(['name' => 'Anna', 'surname' => 'Bianchi']);
    $luca = User::factory()->employee()->create(['name' => 'Luca', 'surname' => 'Rossi']);
    $activity = dashboardActivity(dashboardProject());
    logHours($anna, $activity, '2026-11-10', 120);
    logHours($anna, $activity, '2026-11-10', 60, '14:00');
    logHours($luca, $activity, '2026-11-11', 60);
    Absence::factory()->for($anna)->leave('2026-11-10', '09:00', '11:00')->confirmed()->create();

    $boss = User::factory()->boss()->create();

    $this->actingAs($boss)->get(route('dashboard', ['day' => '2026-11-10']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('day_hours.people', 1)
            ->where('day_hours.people.0.name', 'Anna Bianchi')
            ->where('day_hours.people.0.total_minutes', 180)
            ->where('day_hours.people.0.capacity_hours', 6)
            ->has('day_hours.people.0.entries', 2)
            ->where('day_hours.people.0.entries.0.started_at', '09:00'));

    // persone indicate: compaiono anche senza registrazioni, e le festività azzerano la capacità
    $this->actingAs($boss)->get(route('dashboard', ['day' => '2026-11-12', 'user_ids' => [$luca->id]]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('day_hours.people', 1)
            ->where('day_hours.people.0.capacity_hours', 0)
            ->where('day_hours.people.0.total_minutes', 0));
});

it('ignores people the viewer cannot see in the day card', function (): void {
    $mine = User::factory()->employee()->create();
    $other = User::factory()->employee()->create();
    $activity = dashboardActivity(dashboardProject());
    logHours($other, $activity, '2026-11-10', 60);

    $this->actingAs($mine)->get(route('dashboard', ['day' => '2026-11-10', 'user_ids' => [$other->id]]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('day_hours.people', 0)
            ->has('day_hours.options', 1));
});

it('builds the worked hours table by week with column and grand totals', function (): void {
    $anna = User::factory()->employee()->create(['name' => 'Anna', 'surname' => 'Bianchi']);
    $luca = User::factory()->employee()->create(['name' => 'Luca', 'surname' => 'Rossi']);
    $activity = dashboardActivity(dashboardProject());
    logHours($anna, $activity, '2026-11-09', 60);
    logHours($anna, $activity, '2026-11-11', 120);
    logHours($anna, $activity, '2026-11-17', 30);
    logHours($luca, $activity, '2026-11-18', 90);

    $this->actingAs(User::factory()->hr()->create())
        ->get(route('dashboard', ['from' => '2026-11-04', 'to' => '2026-11-22', 'granularity' => 'week']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('worked_hours.buckets.0.from', '2026-11-04')
            ->where('worked_hours.buckets.0.to', '2026-11-08')
            ->where('worked_hours.buckets.1.from', '2026-11-09')
            ->where('worked_hours.buckets.1.to', '2026-11-15')
            ->where('worked_hours.buckets.2.to', '2026-11-22')
            ->has('worked_hours.buckets', 3)
            ->where('worked_hours.rows.0.name', 'Anna Bianchi')
            ->where('worked_hours.rows.0.minutes', [0, 180, 30])
            ->where('worked_hours.rows.0.total', 210)
            ->where('worked_hours.rows.1.minutes', [0, 0, 90])
            ->where('worked_hours.column_totals', [0, 180, 120])
            ->where('worked_hours.grand_total', 300));
});

it('groups the worked hours by day or by month, and filters by person and area', function (): void {
    $area = Area::factory()->create();
    $anna = User::factory()->employee($area)->create(['name' => 'Anna', 'surname' => 'Bianchi']);
    $luca = User::factory()->employee()->create(['name' => 'Luca', 'surname' => 'Rossi']);
    $activity = dashboardActivity(dashboardProject());
    logHours($anna, $activity, '2026-11-09', 60);
    logHours($luca, $activity, '2026-12-02', 60);
    $boss = User::factory()->boss()->create();

    $this->actingAs($boss)->get(route('dashboard', ['from' => '2026-11-09', 'to' => '2026-11-11', 'granularity' => 'day']))
        ->assertInertia(fn (Assert $page) => $page->has('worked_hours.buckets', 3)->where('worked_hours.column_totals', [60, 0, 0]));

    $this->actingAs($boss)->get(route('dashboard', ['from' => '2026-11-01', 'to' => '2026-12-31', 'granularity' => 'month']))
        ->assertInertia(fn (Assert $page) => $page->has('worked_hours.buckets', 2)->where('worked_hours.column_totals', [60, 60]));

    $this->actingAs($boss)->get(route('dashboard', ['from' => '2026-11-01', 'to' => '2026-12-31', 'user_id' => $luca->id]))
        ->assertInertia(fn (Assert $page) => $page->has('worked_hours.rows', 1)->where('worked_hours.rows.0.name', 'Luca Rossi'));

    $this->actingAs($boss)->get(route('dashboard', ['from' => '2026-11-01', 'to' => '2026-12-31', 'area_id' => $area->id]))
        ->assertInertia(fn (Assert $page) => $page->has('worked_hours.rows', 1)->where('worked_hours.rows.0.name', 'Anna Bianchi')->has('worked_hours.areas', 1));
});

it('lets the area head see only their area in the worked hours, and no area filter', function (): void {
    $area = Area::factory()->create();
    $head = User::factory()->areaHead($area)->create();
    $mine = User::factory()->employee($area)->create();
    $outsider = User::factory()->employee(Area::factory()->create())->create();
    $activity = dashboardActivity(dashboardProject());
    logHours($mine, $activity, '2026-11-10', 60);
    logHours($outsider, $activity, '2026-11-10', 60);

    $this->actingAs($head)->get(route('dashboard', ['from' => '2026-11-01', 'to' => '2026-11-30', 'area_id' => $outsider->area_id, 'user_id' => $outsider->id]))
        ->assertInertia(fn (Assert $page) => $page->has('worked_hours.rows', 0)->has('worked_hours.areas', 0));

    $this->actingAs($head)->get(route('dashboard', ['from' => '2026-11-01', 'to' => '2026-11-30']))
        ->assertInertia(fn (Assert $page) => $page->has('worked_hours.rows', 1)->where('worked_hours.rows.0.id', $mine->id));
});

it('exports the registrations as a CSV with the filters applied', function (): void {
    $area = Area::factory()->create();
    $anna = User::factory()->employee($area)->create(['name' => 'Anna', 'surname' => 'Bianchi']);
    $luca = User::factory()->employee()->create(['name' => 'Luca', 'surname' => 'Rossi']);
    $project = dashboardProject('Sito');
    $other = dashboardProject('Altro');
    logHours($anna, dashboardActivity($project, 'Layout'), '2026-11-10', 90);
    logHours($luca, dashboardActivity($other, 'Codice'), '2026-11-11', 60);
    logHours($luca, dashboardActivity($other, 'Fuori'), '2026-12-11', 60);

    $csv = fn (array $query) => $this->actingAs(User::factory()->boss()->create())->get(route('dashboard.export', $query))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->streamedContent();

    $all = $csv(['from' => '2026-11-01', 'to' => '2026-11-30']);
    expect($all)->toStartWith("\xEF\xBB\xBFData;Progetto;Cliente;Attività;Persona;Area;Inizio;Fine;Ore;Descrizione")
        ->and($all)->toContain('10/11/2026;Sito;')->toContain('Anna Bianchi')->toContain(';1,50;')
        ->and($all)->toContain('Luca Rossi')->not->toContain('Fuori');

    expect($csv(['from' => '2026-11-01', 'to' => '2026-11-30', 'project_id' => $project->id]))->not->toContain('Luca Rossi')
        ->and($csv(['from' => '2026-11-01', 'to' => '2026-11-30', 'area_id' => $area->id]))->not->toContain('Luca Rossi')->toContain('Anna Bianchi');
});

it('lets hr and the area head export (the latter only their area), and not the employee', function (): void {
    $area = Area::factory()->create();
    $head = User::factory()->areaHead($area)->create();
    $mine = User::factory()->employee($area)->create(['name' => 'Anna', 'surname' => 'Bianchi']);
    $outsider = User::factory()->employee(Area::factory()->create())->create(['name' => 'Luca', 'surname' => 'Rossi']);
    $activity = dashboardActivity(dashboardProject());
    logHours($mine, $activity, '2026-11-10', 60);
    logHours($outsider, $activity, '2026-11-10', 60);
    $query = ['from' => '2026-11-01', 'to' => '2026-11-30'];

    $areaCsv = $this->actingAs($head)->get(route('dashboard.export', $query + ['area_id' => $outsider->area_id]))->assertOk()->streamedContent();
    expect($areaCsv)->toContain('Anna Bianchi')->not->toContain('Luca Rossi');

    $this->actingAs(User::factory()->hr()->create())->get(route('dashboard.export', $query))->assertOk();
    $this->actingAs($mine)->get(route('dashboard.export', $query))->assertForbidden();
});

it('requires a login for the dashboard and its export', function (): void {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('dashboard.export'))->assertRedirect(route('login'));
});
