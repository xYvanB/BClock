<?php

declare(strict_types=1);

use App\Enum\WorkStatus;
use App\Models\Activity;
use App\Models\Area;
use App\Models\TimeEntry;
use App\Models\Timer;
use App\Models\User;

it('archives an activity and keeps it with its hours', function (): void {
    $activity = Activity::factory()->create(['status' => WorkStatus::InProgress]);
    TimeEntry::factory()->for($activity)->create();

    $this->actingAs(User::factory()->boss()->create())
        ->from(route('activities.index'))
        ->post(route('activities.archive', $activity))
        ->assertRedirect(route('activities.index'));

    expect($activity->refresh()->status)->toBe(WorkStatus::Archived)
        ->and(TimeEntry::count())->toBe(1);
});

it('deletes an activity without hours or timers, with its assignees', function (): void {
    $activity = Activity::factory()->create();
    $activity->assignees()->attach(User::factory()->employee()->create());

    $this->actingAs(User::factory()->boss()->create())
        ->delete(route('activities.destroy', $activity))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseMissing('activities', ['id' => $activity->id]);
    $this->assertDatabaseCount('activity_assignees', 0);
});

it('refuses to delete an activity with hours and keeps it', function (): void {
    $activity = Activity::factory()->create();
    TimeEntry::factory()->for($activity)->create();

    $this->actingAs(User::factory()->boss()->create())
        ->delete(route('activities.destroy', $activity))
        ->assertSessionHasErrors('activity');

    $this->assertDatabaseHas('activities', ['id' => $activity->id]);
});

it('refuses to delete an activity with a running timer and keeps the timer', function (): void {
    $activity = Activity::factory()->create();
    Timer::factory()->for($activity)->create();

    $this->actingAs(User::factory()->boss()->create())
        ->delete(route('activities.destroy', $activity))
        ->assertSessionHasErrors('activity');

    $this->assertDatabaseHas('activities', ['id' => $activity->id]);
    expect(Timer::count())->toBe(1);
});

it('applies the activity permissions to archive and delete: hr and employees get 403', function (User $user): void {
    $activity = Activity::factory()->create();
    $activity->assignees()->attach(User::factory()->employee()->create());

    $this->actingAs($user)->post(route('activities.archive', $activity))->assertForbidden();
    $this->actingAs($user)->delete(route('activities.destroy', $activity))->assertForbidden();

    expect($activity->refresh()->status)->not->toBe(WorkStatus::Archived);
    $this->assertDatabaseHas('activities', ['id' => $activity->id]);
})->with([
    'hr' => fn () => User::factory()->hr()->create(),
    'employee' => fn () => User::factory()->employee()->create(),
]);

it('lets the area head archive and delete only activities whose assignees are all in their area', function (): void {
    $area = Area::factory()->create();
    $head = User::factory()->areaHead()->create(['area_id' => $area->id]);
    $mine = User::factory()->employee()->create(['area_id' => $area->id]);
    $outsider = User::factory()->employee()->create(['area_id' => Area::factory()->create()->id]);

    $own = Activity::factory()->create();
    $own->assignees()->attach($mine);
    $mixed = Activity::factory()->create();
    $mixed->assignees()->attach([$mine->id, $outsider->id]);

    $this->actingAs($head)->post(route('activities.archive', $mixed))->assertForbidden();
    $this->actingAs($head)->delete(route('activities.destroy', $mixed))->assertForbidden();
    $this->assertDatabaseHas('activities', ['id' => $mixed->id]);

    $this->actingAs($head)->post(route('activities.archive', $own))->assertSessionHasNoErrors();
    expect($own->refresh()->status)->toBe(WorkStatus::Archived);

    $this->actingAs($head)->delete(route('activities.destroy', $own))->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('activities', ['id' => $own->id]);
});

it('does not let the archived activity receive hours or timers any more', function (): void {
    $activity = Activity::factory()->create();
    $user = User::factory()->employee()->create();

    $this->actingAs(User::factory()->boss()->create())->post(route('activities.archive', $activity));

    $this->actingAs($user)->post(route('timers.store'), ['activity_id' => $activity->id])->assertSessionHasErrors('activity_id');
    $this->actingAs($user)->post(route('time-entries.store'), [
        'activity_id' => $activity->id,
        'date' => '2026-10-20',
        'started_at' => '09:00',
        'ended_at' => '10:00',
    ])->assertSessionHasErrors('activity_id');
});
