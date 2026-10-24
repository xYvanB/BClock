<?php

declare(strict_types=1);

use App\Enum\WorkStatus;
use App\Models\Activity;
use App\Models\Area;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\TemplateActivity;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Template con due attività: giorni lavorativi 1–2 e 3–5.
 *
 * @return array{0: ProjectTemplate, 1: TemplateActivity, 2: TemplateActivity}
 */
function twoActivityTemplate(): array
{
    $template = ProjectTemplate::factory()->create(['name' => 'Sito vetrina']);
    $analysis = TemplateActivity::factory()->for($template, 'template')->create([
        'name' => 'Analisi', 'description' => 'Requisiti', 'planned_hours' => 8, 'start_day' => 1, 'end_day' => 2, 'position' => 1,
    ]);
    $design = TemplateActivity::factory()->for($template, 'template')->create([
        'name' => 'Design', 'description' => null, 'planned_hours' => 16, 'start_day' => 3, 'end_day' => 5, 'position' => 2,
    ]);

    return [$template, $analysis, $design];
}

function projectFromTemplatePayload(Client $client, ProjectTemplate $template, array $assignees, array $overrides = []): array
{
    return array_merge([
        'client_id' => $client->id,
        'template_id' => $template->id,
        'template_assignees' => $assignees,
        'name' => 'Sito Verdi',
        'description' => null,
        'starts_on' => '2026-11-06',
        'due_on' => '2026-12-31',
        'planned_hours' => 100,
        'status' => WorkStatus::Todo->value,
        'member_ids' => [],
        'contact_ids' => [],
    ], $overrides);
}

it('creates the activities of the template with working-day dates, hours, status and assignees', function (): void {
    [$template, $analysis, $design] = twoActivityTemplate();
    $client = Client::factory()->create();
    $anna = User::factory()->employee()->create();
    $luca = User::factory()->employee()->create();

    // Il progetto parte venerdì 6 novembre: giorno 1 = ven 6, 2 = lun 9, 3 = mar 10, 5 = gio 12.
    $this->actingAs(User::factory()->boss()->create())
        ->post(route('projects.store'), projectFromTemplatePayload($client, $template, [
            $analysis->id => [$anna->id],
            $design->id => [$anna->id, $luca->id],
        ]))
        ->assertSessionHasNoErrors();

    $project = Project::where('name', 'Sito Verdi')->firstOrFail();
    $activities = $project->activities()->orderBy('starts_on')->get();

    expect($project->project_template_id)->toBe($template->id)
        ->and($activities)->toHaveCount(2)
        ->and($activities[0]->name)->toBe('Analisi')
        ->and($activities[0]->starts_on->toDateString())->toBe('2026-11-06')
        ->and($activities[0]->due_on->toDateString())->toBe('2026-11-09')
        ->and($activities[1]->starts_on->toDateString())->toBe('2026-11-10')
        ->and($activities[1]->due_on->toDateString())->toBe('2026-11-12')
        ->and((float) $activities[1]->planned_hours)->toBe(16.0)
        ->and($activities[0]->status)->toBe(WorkStatus::Todo)
        ->and($activities[0]->description)->toBe('Requisiti')
        ->and($activities[0]->assignees->pluck('id')->all())->toBe([$anna->id])
        ->and($activities[1]->assignees->pluck('id')->sort()->values()->all())->toBe([$anna->id, $luca->id]);
});

it('creates a project without template as before', function (): void {
    $client = Client::factory()->create();

    $this->actingAs(User::factory()->boss()->create())
        ->post(route('projects.store'), projectFromTemplatePayload($client, ProjectTemplate::factory()->create(), [], [
            'template_id' => null,
            'template_assignees' => [],
        ]))
        ->assertSessionHasNoErrors();

    $project = Project::where('name', 'Sito Verdi')->firstOrFail();

    expect($project->project_template_id)->toBeNull()
        ->and($project->activities)->toBeEmpty();
});

it('accepts a template that ends exactly on the project due date', function (): void {
    [$template, $analysis, $design] = twoActivityTemplate();
    $person = User::factory()->employee()->create();

    $this->actingAs(User::factory()->boss()->create())
        ->post(route('projects.store'), projectFromTemplatePayload(Client::factory()->create(), $template, [
            $analysis->id => [$person->id],
            $design->id => [$person->id],
        ], ['due_on' => '2026-11-12']))
        ->assertSessionHasNoErrors();
});

it('rejects a template that runs past the project due date and creates nothing', function (): void {
    [$template, $analysis, $design] = twoActivityTemplate();
    $person = User::factory()->employee()->create();

    $this->actingAs(User::factory()->boss()->create())
        ->post(route('projects.store'), projectFromTemplatePayload(Client::factory()->create(), $template, [
            $analysis->id => [$person->id],
            $design->id => [$person->id],
        ], ['due_on' => '2026-11-11']))
        ->assertSessionHasErrors('template_id');

    $this->assertDatabaseCount('projects', 0);
    $this->assertDatabaseCount('activities', 0);
});

it('requires at least one assignee for every template activity', function (): void {
    [$template, $analysis, $design] = twoActivityTemplate();
    $person = User::factory()->employee()->create();

    $this->actingAs(User::factory()->boss()->create())
        ->post(route('projects.store'), projectFromTemplatePayload(Client::factory()->create(), $template, [
            $analysis->id => [$person->id],
        ]))
        ->assertSessionHasErrors("template_assignees.{$design->id}");

    $this->assertDatabaseCount('projects', 0);
});

it('lets the area head pick only people of their own area for template activities', function (): void {
    [$template, $analysis, $design] = twoActivityTemplate();
    $area = Area::factory()->create();
    $head = User::factory()->areaHead()->create(['area_id' => $area->id]);
    $mine = User::factory()->employee()->create(['area_id' => $area->id]);
    $outsider = User::factory()->employee()->create(['area_id' => Area::factory()->create()->id]);
    $client = Client::factory()->create();

    $this->actingAs($head)
        ->post(route('projects.store'), projectFromTemplatePayload($client, $template, [
            $analysis->id => [$mine->id],
            $design->id => [$outsider->id],
        ]))
        ->assertSessionHasErrors("template_assignees.{$design->id}.0");

    $this->assertDatabaseCount('projects', 0);

    $this->actingAs($head)
        ->post(route('projects.store'), projectFromTemplatePayload($client, $template, [
            $analysis->id => [$mine->id],
            $design->id => [$mine->id],
        ]))
        ->assertSessionHasNoErrors();

    expect(Activity::count())->toBe(2);
});

it('does not let hr use templates: the field is rejected and the options are empty', function (): void {
    [$template, $analysis, $design] = twoActivityTemplate();
    $person = User::factory()->employee()->create();
    $hr = User::factory()->hr()->create();

    $this->actingAs($hr)
        ->post(route('projects.store'), projectFromTemplatePayload(Client::factory()->create(), $template, [
            $analysis->id => [$person->id],
            $design->id => [$person->id],
        ]))
        ->assertSessionHasErrors('template_id');

    $this->assertDatabaseCount('projects', 0);

    $this->actingAs($hr)
        ->get(route('projects.index'))
        ->assertInertia(fn (Assert $page) => $page->has('templates', 0));
});

it('does not accept a template when updating a project', function (): void {
    [$template] = twoActivityTemplate();
    $project = Project::factory()->create();

    $this->actingAs(User::factory()->boss()->create())
        ->put(route('projects.update', $project), [
            'name' => 'Altro',
            'starts_on' => $project->starts_on->toDateString(),
            'due_on' => $project->due_on->toDateString(),
            'planned_hours' => 10,
            'status' => $project->status->value,
            'member_ids' => [],
            'contact_ids' => [],
            'template_id' => $template->id,
        ])
        ->assertSessionHasErrors('template_id');
});

it('offers templates and assignable people on the project list, and shows the origin template on the detail', function (): void {
    [$template] = twoActivityTemplate();
    $project = Project::factory()->create(['project_template_id' => $template->id]);
    $boss = User::factory()->boss()->create();

    $this->actingAs($boss)
        ->get(route('projects.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('templates', 1)
            ->has('templates.0.activities', 2)
            ->where('templates.0.activities.0.start_day', 1)
            ->has('assignees'));

    $this->actingAs($boss)
        ->get(route('projects.show', $project))
        ->assertInertia(fn (Assert $page) => $page->where('project.template_name', 'Sito vetrina'));
});

it('filters the project list by projects having activities assigned to people of an area', function (): void {
    $design = Area::factory()->create();
    $development = Area::factory()->create();
    $designer = User::factory()->employee()->create(['area_id' => $design->id]);
    $developer = User::factory()->employee()->create(['area_id' => $development->id]);

    $designProject = Project::factory()->create();
    Activity::factory()->for($designProject)->create()->assignees()->attach($designer);
    $mixedProject = Project::factory()->create();
    Activity::factory()->for($mixedProject)->create()->assignees()->attach([$designer->id, $developer->id]);
    $devProject = Project::factory()->create();
    Activity::factory()->for($devProject)->create()->assignees()->attach($developer);
    Project::factory()->create();

    $this->actingAs(User::factory()->boss()->create())
        ->get(route('projects.index', ['area_id' => $design->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('projects', 2)
            ->where('filters.area_id', (string) $design->id)
            ->has('areas', 2));
});
