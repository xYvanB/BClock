<?php

declare(strict_types=1);

use App\Enum\TimeEntrySource;
use App\Models\Activity;
use App\Models\TimeEntry;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function validEntry(Activity $activity, array $overrides = []): array
{
    return array_merge([
        'activity_id' => $activity->id,
        'date' => now()->toDateString(),
        'started_at' => '09:00',
        'ended_at' => '12:30',
        'description' => 'Revisione con il cliente',
    ], $overrides);
}

it('requires authentication', function (): void {
    $this->get(route('time-entries.index'))->assertRedirect(route('login'));
});

it('creates a manual entry computing the duration', function (): void {
    $user = User::factory()->create();
    $activity = Activity::factory()->create();

    $this->actingAs($user)
        ->post(route('time-entries.store'), validEntry($activity))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $entry = TimeEntry::sole();
    expect($entry->user_id)->toBe($user->id)
        ->and($entry->duration_minutes)->toBe(210)
        ->and($entry->source)->toBe(TimeEntrySource::Manual);
});

it('rejects invalid entries', function (array $overrides, string $field): void {
    $activity = Activity::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('time-entries.store'), validEntry($activity, $overrides))
        ->assertSessionHasErrors($field);

    expect(TimeEntry::count())->toBe(0);
})->with([
    'end before start' => [['started_at' => '14:00', 'ended_at' => '13:00'], 'ended_at'],
    'end equals start' => [['started_at' => '14:00', 'ended_at' => '14:00'], 'ended_at'],
    'bad time format' => [['started_at' => '9am'], 'started_at'],
    'unknown activity' => [['activity_id' => 9999], 'activity_id'],
    'missing date' => [['date' => ''], 'date'],
]);

it('does not accept an archived activity', function (): void {
    $archived = Activity::factory()->archived()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('time-entries.store'), validEntry($archived))
        ->assertSessionHasErrors('activity_id');
});

it('still saves an entry that exceeds the planned hours', function (): void {
    $activity = Activity::factory()->create(['planned_hours' => 1]);

    $this->actingAs(User::factory()->create())
        ->post(route('time-entries.store'), validEntry($activity))
        ->assertSessionHasNoErrors();

    expect(TimeEntry::count())->toBe(1);
});

it('edits a past entry of the same user', function (): void {
    $user = User::factory()->create();
    $entry = TimeEntry::factory()->for($user)->create(['date' => now()->subMonths(2)->toDateString()]);

    $this->actingAs($user)
        ->put(route('time-entries.update', $entry), validEntry($entry->activity, [
            'date' => $entry->date->toDateString(),
            'ended_at' => '10:00',
        ]))
        ->assertSessionHasNoErrors();

    expect($entry->refresh()->duration_minutes)->toBe(60);
});

it('does not let a user touch the entries of another user', function (): void {
    $entry = TimeEntry::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($other)
        ->put(route('time-entries.update', $entry), validEntry($entry->activity, ['date' => $entry->date->toDateString()]))
        ->assertForbidden();
    $this->actingAs($other)->delete(route('time-entries.destroy', $entry))->assertForbidden();

    expect(TimeEntry::count())->toBe(1);
});

it('deletes an own entry', function (): void {
    $user = User::factory()->create();
    $entry = TimeEntry::factory()->for($user)->create();

    $this->actingAs($user)->delete(route('time-entries.destroy', $entry))->assertRedirect();

    expect(TimeEntry::count())->toBe(0);
});

it('lists only my entries grouped by day with daily totals', function (): void {
    $user = User::factory()->create();
    $activity = Activity::factory()->create();
    TimeEntry::factory()->for($user)->for($activity)->create(['duration_minutes' => 120, 'started_at' => '08:00:00', 'ended_at' => '10:00:00']);
    TimeEntry::factory()->for($user)->for($activity)->create(['duration_minutes' => 60, 'started_at' => '11:00:00', 'ended_at' => '12:00:00']);
    TimeEntry::factory()->for($activity)->create();

    $this->actingAs($user)
        ->get(route('time-entries.index', ['from' => now()->toDateString(), 'to' => now()->toDateString()]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('time-entries/index')
            ->has('days', 1)
            ->where('days.0.total_minutes', 180)
            ->has('days.0.entries', 2));
});

it('flags the activity and the project when hours exceed the plan', function (): void {
    $user = User::factory()->create();
    $activity = Activity::factory()->create(['planned_hours' => 2]);
    TimeEntry::factory()->for($user)->for($activity)->create(['duration_minutes' => 180]);

    $this->actingAs($user)
        ->get(route('time-entries.index', ['from' => now()->toDateString(), 'to' => now()->toDateString()]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('days.0.entries.0.activity_over_budget', true)
            ->where('days.0.entries.0.project_over_budget', false));
});
