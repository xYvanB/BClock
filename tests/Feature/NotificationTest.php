<?php

declare(strict_types=1);

use App\Console\Commands\PruneNotificationsCommand;
use App\Enum\NotificationKind;
use App\Enum\WorkStatus;
use App\Mail\AppNotificationMail;
use App\Models\Absence;
use App\Models\Activity;
use App\Models\Area;
use App\Models\Project;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

function notificationActivityPayload(Project $project, array $assigneeIds, array $overrides = []): array
{
    return array_merge([
        'project_id' => $project->id,
        'name' => 'Sviluppo login',
        'description' => '',
        'starts_on' => '2026-11-09',
        'due_on' => '2026-11-13',
        'planned_hours' => 10,
        'status' => WorkStatus::Todo->value,
        'assignee_ids' => $assigneeIds,
    ], $overrides);
}

function notificationProject(): Project
{
    return Project::factory()->create(['starts_on' => '2026-11-01', 'due_on' => '2026-12-31']);
}

it('tells the new assignees, but not the person who assigns them', function (): void {
    $boss = User::factory()->boss()->create();
    $person = User::factory()->employee()->create();

    $this->actingAs($boss)->post(route('activities.store'), notificationActivityPayload(notificationProject(), [$person->id, $boss->id]))
        ->assertSessionHasNoErrors();

    expect($person->unreadNotifications()->count())->toBe(1)
        ->and($person->unreadNotifications()->first()->data)->toMatchArray(['kind' => 'task_assegnato', 'message' => 'Ti è stato assegnato «Sviluppo login».'])
        ->and($boss->unreadNotifications()->count())->toBe(0);
});

it('tells only who is added when an assignee changes, and who stays when the dates change', function (): void {
    $boss = User::factory()->boss()->create();
    [$stays, $added] = User::factory()->employee()->count(2)->create();
    $project = notificationProject();
    $activity = Activity::factory()->for($project)->create(['name' => 'Sviluppo login', 'starts_on' => '2026-11-09', 'due_on' => '2026-11-13', 'planned_hours' => '10.00']);
    $activity->assignees()->attach($stays);

    $payload = notificationActivityPayload($project, [$stays->id, $added->id], ['starts_on' => '2026-11-10', 'due_on' => '2026-11-16']);
    unset($payload['project_id']);

    $this->actingAs($boss)->put(route('activities.update', $activity), $payload)->assertSessionHasNoErrors();

    expect($added->unreadNotifications()->get()->pluck('data.kind')->all())->toBe(['task_assegnato'])
        ->and($stays->unreadNotifications()->get()->pluck('data.kind')->all())->toBe(['attivita_spostata'])
        ->and($stays->unreadNotifications()->first()->data['message'])->toContain('10/11/2026')->toContain('(era dal 09/11/2026 al 13/11/2026)');

    $this->actingAs($boss)->put(route('activities.update', $activity), $payload)->assertSessionHasNoErrors();
    expect($stays->unreadNotifications()->count())->toBe(1);
});

it('tells the assignees once for every activity moved from the Gantt', function (): void {
    $boss = User::factory()->boss()->create();
    $person = User::factory()->employee()->create();
    $project = notificationProject();
    $a = Activity::factory()->for($project)->create(['name' => 'A', 'starts_on' => '2026-11-09', 'due_on' => '2026-11-11', 'planned_hours' => '10.00']);
    $b = Activity::factory()->for($project)->create(['name' => 'B', 'starts_on' => '2026-11-12', 'due_on' => '2026-11-13', 'planned_hours' => '10.00']);
    $a->assignees()->attach([$person->id, $boss->id]);
    $b->assignees()->attach($person);
    $b->dependencies()->attach($a);

    $this->actingAs($boss)->post(route('gantt.shift', $a), ['mode' => 'move', 'starts_on' => '2026-11-11', 'due_on' => '2026-11-13'])
        ->assertSessionHasNoErrors();

    expect($person->unreadNotifications()->count())->toBe(2)
        ->and($person->unreadNotifications()->get()->pluck('data.message')->implode(' '))->toContain('«A»')->toContain('«B»')
        ->and($boss->unreadNotifications()->count())->toBe(0);
});

it('tells the owner who approved, then that the absence is confirmed, and the rejection with its reason', function (): void {
    $area = Area::factory()->create();
    $head = User::factory()->areaHead($area)->create();
    $hr = User::factory()->hr()->create();
    $employee = User::factory()->employee($area)->create();
    $this->actingAs($employee)->post(route('absences.store'), ['type' => 'ferie', 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-11', 'note' => '']);
    $absence = Absence::firstOrFail();

    expect($head->unreadNotifications()->get()->pluck('data.kind')->all())->toBe(['assenza_richiesta'])
        ->and($hr->unreadNotifications()->get()->pluck('data.kind')->all())->toBe(['assenza_richiesta']);

    $this->actingAs($head)->post(route('absences.approve', $absence));
    expect($employee->unreadNotifications()->get()->pluck('data.message')->implode(' | '))->toContain('(Capo area)')->toContain('manca ancora una conferma');

    $this->actingAs($hr)->post(route('absences.confirm', $absence));
    expect($employee->unreadNotifications()->count())->toBe(2)
        ->and($employee->unreadNotifications()->get()->pluck('data.message')->implode(' | '))->toContain('ora è confermata');

    $other = Absence::factory()->create(['user_id' => $employee->id, 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-02']);
    $this->actingAs($hr)->post(route('absences.reject', $other), ['reason' => 'Periodo già coperto']);
    expect($employee->unreadNotifications()->get()->pluck('data.message')->implode(' | '))->toContain('«Periodo già coperto»');
});

it('opens the linked page, marks only the own notification as read, and refuses the ones of others', function (): void {
    $person = User::factory()->employee()->create();
    $other = User::factory()->employee()->create();
    $this->actingAs(User::factory()->boss()->create())->post(route('activities.store'), notificationActivityPayload(notificationProject(), [$person->id, $other->id]));

    $mine = $person->unreadNotifications()->firstOrFail();
    $theirs = $other->unreadNotifications()->firstOrFail();

    $this->actingAs($person)->post(route('notifications.read', $theirs->id))->assertNotFound();
    expect($theirs->fresh()->read_at)->toBeNull();

    $this->actingAs($person)->post(route('notifications.read', $mine->id))->assertRedirect('/activities');
    expect($mine->fresh()->read_at)->not->toBeNull();
});

it('shares the unread count and the latest notifications, and lists them all with an unread filter', function (): void {
    $person = User::factory()->employee()->create();
    $this->actingAs(User::factory()->boss()->create())->post(route('activities.store'), notificationActivityPayload(notificationProject(), [$person->id]));
    $person->unreadNotifications()->first()->markAsRead();
    $this->actingAs(User::factory()->boss()->create())->post(route('activities.store'), notificationActivityPayload(notificationProject(), [$person->id], ['name' => 'Altro']));

    $this->actingAs($person)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('notifications.unread_count', 1)->has('notifications.items', 2));

    $this->actingAs($person)->get(route('notifications.index'))
        ->assertInertia(fn (Assert $page) => $page->component('notifications/index')->has('notifications.data', 2));
    $this->actingAs($person)->get(route('notifications.index', ['filter' => 'unread']))
        ->assertInertia(fn (Assert $page) => $page->has('notifications.data', 1)->where('filter', 'unread'));
});

it('sends the e-mail only to who wants it, after the action', function (): void {
    Mail::fake();
    $boss = User::factory()->boss()->create();
    [$wants, $default] = User::factory()->employee()->count(2)->create();
    $wants->update(['notification_preferences' => ['task_assegnato' => ['app' => true, 'mail' => true]]]);

    $this->actingAs($boss)->post(route('activities.store'), notificationActivityPayload(notificationProject(), [$wants->id, $default->id]));

    Mail::assertQueued(AppNotificationMail::class, 1);
    Mail::assertQueued(AppNotificationMail::class, fn (AppNotificationMail $mail): bool => $mail->hasTo($wants->email));
});

it('keeps the approval requests on in the app whatever is saved, and saves the other choices', function (): void {
    $person = User::factory()->employee()->create();
    $payload = collect(NotificationKind::cases())->mapWithKeys(fn (NotificationKind $kind): array => [$kind->value => ['app' => false, 'mail' => $kind === NotificationKind::TaskAssigned]])->all();

    $this->actingAs($person)->get(route('notification-preferences.edit'))->assertOk();
    $this->actingAs($person)->put(route('notification-preferences.update'), $payload)->assertSessionHasNoErrors();

    $person->refresh();
    expect($person->wantsNotification(NotificationKind::AbsenceRequested, 'app'))->toBeTrue()
        ->and($person->wantsNotification(NotificationKind::TaskAssigned, 'app'))->toBeFalse()
        ->and($person->wantsNotification(NotificationKind::TaskAssigned, 'mail'))->toBeTrue();

    $this->actingAs($person)->put(route('notification-preferences.update'), ['task_assegnato' => ['app' => true]])->assertSessionHasErrors();
});

it('skips the in-app notification of who turned it off', function (): void {
    $person = User::factory()->employee()->create(['notification_preferences' => ['task_assegnato' => ['app' => false, 'mail' => false]]]);

    $this->actingAs(User::factory()->boss()->create())->post(route('activities.store'), notificationActivityPayload(notificationProject(), [$person->id]));

    expect($person->unreadNotifications()->count())->toBe(0);
});

it('prunes the notifications older than 90 days', function (): void {
    $person = User::factory()->employee()->create();
    $this->actingAs(User::factory()->boss()->create())->post(route('activities.store'), notificationActivityPayload(notificationProject(), [$person->id]));
    DatabaseNotification::query()->update(['created_at' => now()->subDays(PruneNotificationsCommand::KEEP_DAYS + 1)]);
    $this->actingAs(User::factory()->boss()->create())->post(route('activities.store'), notificationActivityPayload(notificationProject(), [$person->id], ['name' => 'Recente']));

    $this->artisan('notifications:prune')->assertSuccessful();

    expect(DatabaseNotification::count())->toBe(1);
});
