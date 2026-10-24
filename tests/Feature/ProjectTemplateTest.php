<?php

declare(strict_types=1);

use App\Enum\Role;
use App\Models\Area;
use App\Models\ProjectTemplate;
use App\Models\TemplateActivity;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function templatePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Sito vetrina',
        'activities' => [
            ['name' => 'Analisi', 'description' => 'Raccolta requisiti', 'planned_hours' => 8, 'start_day' => 1, 'end_day' => 2, 'suggested_area_id' => null, 'suggested_role' => null],
            ['name' => 'Design', 'description' => null, 'planned_hours' => 16, 'start_day' => 3, 'end_day' => 5, 'suggested_area_id' => null, 'suggested_role' => Role::Employee->value],
        ],
    ], $overrides);
}

it('lets boss and area head list templates with their ordered activities', function (User $user): void {
    $template = ProjectTemplate::factory()->create();
    TemplateActivity::factory()->for($template, 'template')->create(['name' => 'Seconda', 'position' => 2]);
    TemplateActivity::factory()->for($template, 'template')->create(['name' => 'Prima', 'position' => 1]);

    $this->actingAs($user)
        ->get(route('templates.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('templates/index')
            ->has('templates', 1)
            ->where('templates.0.activities.0.name', 'Prima')
            ->where('templates.0.activities.1.name', 'Seconda'));
})->with([
    'boss' => fn () => User::factory()->boss()->create(),
    'area head' => fn () => User::factory()->areaHead()->create(),
]);

it('forbids hr and employees from the template page, creation and update', function (User $user): void {
    $template = ProjectTemplate::factory()->create();

    $this->actingAs($user)->get(route('templates.index'))->assertForbidden();
    $this->actingAs($user)->post(route('templates.store'), [])->assertForbidden();
    $this->actingAs($user)->put(route('templates.update', $template), [])->assertForbidden();
})->with([
    'hr' => fn () => User::factory()->hr()->create(),
    'employee' => fn () => User::factory()->employee()->create(),
]);

it('creates a template keeping the order of the submitted activities', function (): void {
    $area = Area::factory()->create();
    $payload = templatePayload();
    $payload['activities'][0]['suggested_area_id'] = $area->id;

    $this->actingAs(User::factory()->areaHead()->create())
        ->post(route('templates.store'), $payload)
        ->assertRedirect(route('templates.index'))
        ->assertSessionHasNoErrors();

    $template = ProjectTemplate::where('name', 'Sito vetrina')->firstOrFail();

    expect($template->activities->pluck('name')->all())->toBe(['Analisi', 'Design'])
        ->and($template->activities->pluck('position')->all())->toBe([1, 2])
        ->and($template->activities[0]->suggested_area_id)->toBe($area->id)
        ->and($template->activities[1]->suggested_role)->toBe(Role::Employee)
        ->and((float) $template->activities[1]->planned_hours)->toBe(16.0);
});

it('replaces the activities when updating, following the new order', function (): void {
    $template = ProjectTemplate::factory()->create(['name' => 'Vecchio']);
    TemplateActivity::factory()->for($template, 'template')->count(3)->create();

    $payload = templatePayload(['name' => 'Nuovo']);
    $payload['activities'] = array_reverse($payload['activities']);

    $this->actingAs(User::factory()->boss()->create())
        ->put(route('templates.update', $template), $payload)
        ->assertRedirect(route('templates.index'))
        ->assertSessionHasNoErrors();

    $template->refresh();

    expect($template->name)->toBe('Nuovo')
        ->and($template->activities->pluck('name')->all())->toBe(['Design', 'Analisi']);
});

it('validates the template fields', function (array $overrides, string $field): void {
    $this->actingAs(User::factory()->boss()->create())
        ->post(route('templates.store'), templatePayload($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'no activities' => [['activities' => []], 'activities'],
    'activity without name' => [['activities' => [['name' => '', 'planned_hours' => 1, 'start_day' => 1, 'end_day' => 1]]], 'activities.0.name'],
    'last day before first day' => [['activities' => [['name' => 'A', 'planned_hours' => 1, 'start_day' => 5, 'end_day' => 3]]], 'activities.0.end_day'],
    'day zero' => [['activities' => [['name' => 'A', 'planned_hours' => 1, 'start_day' => 0, 'end_day' => 3]]], 'activities.0.start_day'],
    'negative hours' => [['activities' => [['name' => 'A', 'planned_hours' => -1, 'start_day' => 1, 'end_day' => 1]]], 'activities.0.planned_hours'],
    'unknown area' => [['activities' => [['name' => 'A', 'planned_hours' => 1, 'start_day' => 1, 'end_day' => 1, 'suggested_area_id' => 999999]]], 'activities.0.suggested_area_id'],
    'unknown role' => [['activities' => [['name' => 'A', 'planned_hours' => 1, 'start_day' => 1, 'end_day' => 1, 'suggested_role' => 'boh']]], 'activities.0.suggested_role'],
]);

it('answers 403, not a validation error, when hr sends an invalid payload', function (): void {
    $this->actingAs(User::factory()->hr()->create())
        ->post(route('templates.store'), ['name' => ''])
        ->assertForbidden();
});
