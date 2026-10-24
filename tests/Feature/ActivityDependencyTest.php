<?php

declare(strict_types=1);

use App\Models\Activity;
use App\Models\Project;
use App\Models\User;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function dependencyPayload(Activity $activity, array $overrides = []): array
{
    return array_merge([
        'name' => $activity->name,
        'starts_on' => $activity->starts_on->toDateString(),
        'due_on' => $activity->due_on->toDateString(),
        'planned_hours' => '8',
        'status' => 'da_fare',
        'assignee_ids' => [User::factory()->employee()->create()->id],
    ], $overrides);
}

/**
 * Tre attività dello stesso progetto, tutte dentro le sue date.
 *
 * @return array{Project, Activity, Activity, Activity}
 */
function projectWithActivities(): array
{
    $project = Project::factory()->create(['starts_on' => '2026-11-01', 'due_on' => '2026-12-31']);
    $make = fn (string $name) => Activity::factory()->for($project)->create(['name' => $name, 'starts_on' => '2026-11-09', 'due_on' => '2026-11-13']);

    return [$project, $make('A'), $make('B'), $make('C')];
}

it('saves the activities this one depends on', function (): void {
    [, $a, $b, $c] = projectWithActivities();
    $boss = User::factory()->boss()->create();

    $this->actingAs($boss)->put(route('activities.update', $c), dependencyPayload($c, ['dependency_ids' => [$a->id, $b->id]]))
        ->assertSessionHasNoErrors();

    expect($c->dependencies()->pluck('activities.id')->sort()->values()->all())->toBe([$a->id, $b->id])
        ->and($a->dependents()->pluck('activities.id')->all())->toBe([$c->id]);

    $this->actingAs($boss)->put(route('activities.update', $c), dependencyPayload($c, ['dependency_ids' => [$a->id]]));
    expect($c->dependencies()->pluck('activities.id')->all())->toBe([$a->id]);

    $this->actingAs($boss)->put(route('activities.update', $c), dependencyPayload($c, ['dependency_ids' => []]));
    expect($c->dependencies()->count())->toBe(0);
});

it('leaves the dependencies alone when the request does not mention them', function (): void {
    [, $a, , $c] = projectWithActivities();
    $c->dependencies()->attach($a);

    $this->actingAs(User::factory()->boss()->create())->put(route('activities.update', $c), dependencyPayload($c))->assertSessionHasNoErrors();

    expect($c->dependencies()->count())->toBe(1);
});

it('can set the dependencies when the activity is created', function (): void {
    [$project, $a] = projectWithActivities();

    $this->actingAs(User::factory()->boss()->create())->post(route('activities.store'), dependencyPayload($a, [
        'project_id' => $project->id,
        'name' => 'D',
        'dependency_ids' => [$a->id],
    ]))->assertSessionHasNoErrors();

    expect(Activity::where('name', 'D')->firstOrFail()->dependencies()->pluck('activities.id')->all())->toBe([$a->id]);
});

it('refuses a dependency on itself, on another project or on a missing activity', function (): void {
    [, $a, , $c] = projectWithActivities();
    $foreign = Activity::factory()->create();

    foreach ([[$c->id], [$foreign->id], [999999]] as $ids) {
        $this->actingAs(User::factory()->boss()->create())
            ->put(route('activities.update', $c), dependencyPayload($c, ['dependency_ids' => $ids]))
            ->assertSessionHasErrors('dependency_ids.0');
    }

    expect($c->dependencies()->count())->toBe(0);
});

it('refuses dependencies that would form a cycle, direct or not', function (): void {
    [, $a, $b, $c] = projectWithActivities();
    $b->dependencies()->attach($a);   // B dipende da A
    $c->dependencies()->attach($b);   // C dipende da B

    $boss = User::factory()->boss()->create();

    // A dipende da C: A → C → B → A
    $this->actingAs($boss)->put(route('activities.update', $a), dependencyPayload($a, ['dependency_ids' => [$c->id]]))
        ->assertSessionHasErrors('dependency_ids');
    // A dipende da B: ciclo diretto
    $this->actingAs($boss)->put(route('activities.update', $a), dependencyPayload($a, ['dependency_ids' => [$b->id]]))
        ->assertSessionHasErrors('dependency_ids');

    expect($a->dependencies()->count())->toBe(0);

    // dipendere da un'attività non collegata va bene
    $this->actingAs($boss)->put(route('activities.update', $c), dependencyPayload($c, ['dependency_ids' => [$a->id, $b->id]]))
        ->assertSessionHasNoErrors();
});

it('exposes the dependencies and the options to the activity pages', function (): void {
    [, $a, , $c] = projectWithActivities();
    $c->dependencies()->attach($a);

    $this->actingAs(User::factory()->boss()->create())->get(route('activities.index'))
        ->assertInertia(fn ($page) => $page
            ->has('dependency_options', 3)
            ->where('activities', fn ($rows) => collect($rows)->firstWhere('id', $c->id)['dependency_ids'] === [$a->id]));
});

it('removes the dependency when one of the two activities is deleted', function (): void {
    [, $a, , $c] = projectWithActivities();
    $c->dependencies()->attach($a);

    $this->actingAs(User::factory()->boss()->create())->delete(route('activities.destroy', $a))->assertSessionHasNoErrors();

    expect($c->dependencies()->count())->toBe(0);
});
