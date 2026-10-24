<?php

declare(strict_types=1);

use App\Actions\Timers\StartTimer;
use App\Actions\Timers\StopTimer;
use App\DTO\TimerData;
use App\Enum\TimeEntrySource;
use App\Models\Activity;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\Timer;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(fn () => $this->travelBack());

it('lets every role start a timer', function (User $user): void {
    $activity = Activity::factory()->create();

    $this->actingAs($user)
        ->post(route('timers.store'), ['activity_id' => $activity->id, 'description' => 'Revisione'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $timer = Timer::where('user_id', $user->id)->firstOrFail();

    expect($timer->activity_id)->toBe($activity->id)
        ->and($timer->description)->toBe('Revisione');
})->with([
    'boss' => fn () => User::factory()->boss()->create(),
    'area head' => fn () => User::factory()->areaHead()->create(),
    'hr' => fn () => User::factory()->hr()->create(),
    'employee' => fn () => User::factory()->employee()->create(),
]);

it('requires login to start a timer', function (): void {
    $this->post(route('timers.store'), ['activity_id' => Activity::factory()->create()->id])
        ->assertRedirect(route('login'));
});

it('allows several timers on different activities but only one per activity', function (): void {
    $user = User::factory()->employee()->create();
    [$first, $second] = Activity::factory()->count(2)->create();

    $this->actingAs($user)->post(route('timers.store'), ['activity_id' => $first->id])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('timers.store'), ['activity_id' => $second->id])->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('timers.store'), ['activity_id' => $first->id])->assertSessionHasErrors('activity_id');

    expect(Timer::where('user_id', $user->id)->count())->toBe(2);
});

it('lets two people run a timer on the same activity', function (): void {
    $activity = Activity::factory()->create();

    $this->actingAs(User::factory()->employee()->create())->post(route('timers.store'), ['activity_id' => $activity->id]);
    $this->actingAs(User::factory()->employee()->create())->post(route('timers.store'), ['activity_id' => $activity->id])
        ->assertSessionHasNoErrors();

    expect(Timer::count())->toBe(2);
});

it('validates the timer start', function (array $payload, string $field): void {
    $this->actingAs(User::factory()->employee()->create())
        ->post(route('timers.store'), $payload)
        ->assertSessionHasErrors($field);
})->with([
    'missing activity' => [[], 'activity_id'],
    'unknown activity' => [['activity_id' => 999999], 'activity_id'],
    'description too long' => [['activity_id' => 1, 'description' => str_repeat('a', 1001)], 'description'],
]);

it('does not start a timer on an archived activity', function (): void {
    $activity = Activity::factory()->archived()->create();

    $this->actingAs(User::factory()->employee()->create())
        ->post(route('timers.store'), ['activity_id' => $activity->id])
        ->assertSessionHasErrors('activity_id');
});

it('turns a stopped timer into a time entry in local time and removes the timer', function (): void {
    $user = User::factory()->employee()->create();
    $activity = Activity::factory()->create();

    // July: Rome is UTC+2, so 06:00 UTC is 08:00 local.
    $this->travelTo('2026-07-15 06:00:00');
    $this->actingAs($user)->post(route('timers.store'), ['activity_id' => $activity->id, 'description' => 'Mockup']);
    $timer = Timer::firstOrFail();

    $this->travelTo('2026-07-15 07:30:00');
    $this->actingAs($user)->post(route('timers.stop', $timer))->assertSessionHas('success', 'Timer fermato: registrazione creata.');

    $entry = TimeEntry::firstOrFail();

    expect(Timer::count())->toBe(0)
        ->and($entry->user_id)->toBe($user->id)
        ->and($entry->activity_id)->toBe($activity->id)
        ->and($entry->date->toDateString())->toBe('2026-07-15')
        ->and(substr((string) $entry->started_at, 0, 5))->toBe('08:00')
        ->and(substr((string) $entry->ended_at, 0, 5))->toBe('09:30')
        ->and($entry->duration_minutes)->toBe(90)
        ->and($entry->description)->toBe('Mockup')
        ->and($entry->source)->toBe(TimeEntrySource::Timer);
});

it('lets the user edit the entry created by the timer', function (): void {
    $user = User::factory()->employee()->create();
    $timer = Timer::factory()->for($user)->create(['started_at' => now()->subHour()]);

    $this->actingAs($user)->post(route('timers.stop', $timer));
    $entry = TimeEntry::firstOrFail();

    $this->actingAs($user)
        ->put(route('time-entries.update', $entry), [
            'activity_id' => $entry->activity_id,
            'date' => $entry->date->toDateString(),
            'started_at' => '09:00',
            'ended_at' => '10:00',
            'description' => 'Corretto',
        ])
        ->assertSessionHasNoErrors();

    expect($entry->refresh()->description)->toBe('Corretto');
});

it('splits a timer that crosses midnight, one entry per local day', function (): void {
    $user = User::factory()->employee()->create();
    $activity = Activity::factory()->create();

    // 21:30 UTC = 23:30 in Rome (July); it stops at 08:15 local the next day.
    $this->travelTo('2026-07-15 21:30:00');
    Timer::factory()->for($user)->for($activity)->create(['started_at' => now()]);
    $this->travelTo('2026-07-16 06:15:00');

    $this->actingAs($user)
        ->post(route('timers.stop', Timer::firstOrFail()))
        ->assertSessionHas('success', fn (string $message) => str_contains($message, '2 registrazioni'));

    $entries = TimeEntry::orderBy('date')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries[0]->date->toDateString())->toBe('2026-07-15')
        ->and(substr((string) $entries[0]->started_at, 0, 5))->toBe('23:30')
        ->and(substr((string) $entries[0]->ended_at, 0, 5))->toBe('23:59')
        ->and($entries[1]->date->toDateString())->toBe('2026-07-16')
        ->and(substr((string) $entries[1]->started_at, 0, 5))->toBe('00:00')
        ->and(substr((string) $entries[1]->ended_at, 0, 5))->toBe('08:15')
        ->and($entries->pluck('source')->unique()->all())->toBe([TimeEntrySource::Timer]);
});

it('splits a timer over several days including a full middle day', function (): void {
    $user = User::factory()->employee()->create();

    $this->travelTo('2026-07-15 21:30:00');
    $timer = Timer::factory()->for($user)->create(['started_at' => now()]);
    $this->travelTo('2026-07-17 07:00:00');

    $this->actingAs($user)->post(route('timers.stop', $timer));

    $entries = TimeEntry::orderBy('date')->get();

    expect($entries)->toHaveCount(3)
        ->and($entries[1]->date->toDateString())->toBe('2026-07-16')
        ->and(substr((string) $entries[1]->started_at, 0, 5))->toBe('00:00')
        ->and(substr((string) $entries[1]->ended_at, 0, 5))->toBe('23:59')
        ->and($entries[2]->date->toDateString())->toBe('2026-07-17')
        ->and(substr((string) $entries[2]->ended_at, 0, 5))->toBe('09:00');
});

it('creates nothing for a timer shorter than a minute and says so', function (): void {
    $user = User::factory()->employee()->create();

    $this->travelTo('2026-07-15 06:00:05');
    $timer = Timer::factory()->for($user)->create(['started_at' => now()]);
    $this->travelTo('2026-07-15 06:00:40');

    $this->actingAs($user)
        ->post(route('timers.stop', $timer))
        ->assertSessionHas('success', fn (string $message) => str_contains($message, 'inferiore a un minuto'));

    expect(Timer::count())->toBe(0)->and(TimeEntry::count())->toBe(0);
});

it('truncates start and end to the minute', function (): void {
    $user = User::factory()->employee()->create();

    $this->travelTo('2026-07-15 06:00:45');
    $timer = Timer::factory()->for($user)->create(['started_at' => now()]);
    $this->travelTo('2026-07-15 06:01:10');

    $this->actingAs($user)->post(route('timers.stop', $timer));

    expect(TimeEntry::firstOrFail()->duration_minutes)->toBe(1);
});

it('records the time even if the activity was archived while the timer ran', function (): void {
    $user = User::factory()->employee()->create();
    $timer = Timer::factory()->for($user)->create(['started_at' => now()->subMinutes(45)]);
    $timer->activity->update(['status' => 'archiviato']);

    $this->actingAs($user)->post(route('timers.stop', $timer));

    expect(TimeEntry::count())->toBe(1)->and(TimeEntry::firstOrFail()->duration_minutes)->toBeGreaterThanOrEqual(44);
});

it('creates a single entry when the same timer is stopped twice', function (): void {
    $user = User::factory()->employee()->create();
    $timer = Timer::factory()->for($user)->create(['started_at' => now()->subHour()]);
    $action = app(StopTimer::class);

    expect($action->handle($timer))->toBe(1)
        ->and($action->handle($timer))->toBeNull()
        ->and(TimeEntry::count())->toBe(1);

    $this->actingAs($user)->post(route('timers.stop', $timer))->assertNotFound();
    expect(TimeEntry::count())->toBe(1);
});

it('forbids stopping the timer of someone else and keeps it running', function (): void {
    $timer = Timer::factory()->create();

    $this->actingAs(User::factory()->boss()->create())
        ->post(route('timers.stop', $timer))
        ->assertForbidden();

    expect(Timer::count())->toBe(1)->and(TimeEntry::count())->toBe(0);
});

it('shares only the active timers of the current user, with the warning threshold and server time', function (): void {
    $user = User::factory()->employee()->create();
    $mine = Timer::factory()->for($user)->create(['description' => 'Mio']);
    Timer::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('timers', 1)
            ->where('timers.0.id', $mine->id)
            ->where('timers.0.activity_name', $mine->activity->name)
            ->where('timers.0.project_name', $mine->activity->project->name)
            ->where('timers.0.description', 'Mio')
            ->has('timers.0.started_at')
            ->where('timer_warning_minutes', 480)
            ->has('server_now'));
});

it('refuses to delete a project while a timer runs on one of its activities', function (): void {
    $project = Project::factory()->create();
    Timer::factory()->for(Activity::factory()->for($project))->create();

    $this->actingAs(User::factory()->boss()->create())
        ->delete(route('projects.destroy', $project))
        ->assertSessionHasErrors('project');

    $this->assertDatabaseHas('projects', ['id' => $project->id]);
    expect(Timer::count())->toBe(1);
});

it('turns a simultaneous second start on the same activity into a validation error, not a server error', function (): void {
    $user = User::factory()->employee()->create();
    $activity = Activity::factory()->create();
    $action = app(StartTimer::class);

    $action->handle($user, new TimerData($activity->id, null));

    expect(fn () => $action->handle($user, new TimerData($activity->id, null)))->toThrow(ValidationException::class)
        ->and(Timer::count())->toBe(1);
});
