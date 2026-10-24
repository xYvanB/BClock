<?php

declare(strict_types=1);

use App\Enum\WorkStatus;
use App\Mcp\Servers\BClockServer;
use App\Mcp\Tools\GetMyWorkloadTool;
use App\Mcp\Tools\ListMyAbsencesTool;
use App\Mcp\Tools\ListMyActivitiesTool;
use App\Mcp\Tools\ListMyTimeEntriesTool;
use App\Mcp\Tools\ListNotificationsTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\LogTimeEntryTool;
use App\Mcp\Tools\MarkNotificationsReadTool;
use App\Mcp\Tools\RequestAbsenceTool;
use App\Mcp\Tools\StartTimerTool;
use App\Mcp\Tools\StopTimerTool;
use App\Models\Absence;
use App\Models\Activity;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\Timer;
use App\Models\User;
use Laravel\Mcp\Server\Attributes\Description;

function mcpActivity(User $person, array $attributes = []): Activity
{
    $project = Project::factory()->create(['starts_on' => '2026-11-01', 'due_on' => '2026-12-31']);
    $activity = Activity::factory()->for($project)->create($attributes + ['name' => 'Sviluppo login', 'starts_on' => '2026-11-09', 'due_on' => '2026-11-13', 'planned_hours' => '10.00']);
    $activity->assignees()->attach($person);

    return $activity;
}

it('refuses every tool without a signed-in person', function (): void {
    BClockServer::tool(ListMyActivitiesTool::class)->assertHasErrors(['Not authenticated.']);
});

it('lists only the open tasks assigned to the person', function (): void {
    $person = User::factory()->employee()->create();
    $mine = mcpActivity($person);
    mcpActivity($person, ['name' => 'Chiusa', 'status' => WorkStatus::Done]);
    mcpActivity(User::factory()->employee()->create(), ['name' => 'Di un altro']);

    $response = BClockServer::actingAs($person)->tool(ListMyActivitiesTool::class);

    $response->assertOk()->assertSee('Sviluppo login')->assertSee((string) $mine->id)->assertDontSee('Chiusa')->assertDontSee('Di un altro');
});

it('logs time through the same rules as the web interface', function (): void {
    $person = User::factory()->employee()->create();
    $activity = mcpActivity($person);

    BClockServer::actingAs($person)->tool(LogTimeEntryTool::class, [
        'activity_id' => $activity->id, 'date' => '2026-11-10', 'started_at' => '09:00', 'ended_at' => '11:30', 'description' => 'Form di login',
    ])->assertOk()->assertSee('150');

    expect(TimeEntry::where('user_id', $person->id)->sole()->duration_minutes)->toBe(150);

    BClockServer::actingAs($person)->tool(LogTimeEntryTool::class, [
        'activity_id' => $activity->id, 'date' => '2026-11-10', 'started_at' => '11:00', 'ended_at' => '10:00',
    ])->assertHasErrors();

    $archived = mcpActivity($person, ['status' => WorkStatus::Archived]);
    BClockServer::actingAs($person)->tool(LogTimeEntryTool::class, [
        'activity_id' => $archived->id, 'date' => '2026-11-10', 'started_at' => '09:00', 'ended_at' => '10:00',
    ])->assertHasErrors();

    expect(TimeEntry::count())->toBe(1);
});

it('lists the own time entries of a period and nobody else\'s', function (): void {
    $person = User::factory()->employee()->create();
    $activity = mcpActivity($person);
    TimeEntry::factory()->create(['user_id' => $person->id, 'activity_id' => $activity->id, 'date' => '2026-11-10', 'duration_minutes' => 90]);
    TimeEntry::factory()->create(['activity_id' => $activity->id, 'date' => '2026-11-10', 'duration_minutes' => 600]);

    $response = BClockServer::actingAs($person)->tool(ListMyTimeEntriesTool::class, ['from' => '2026-11-09', 'to' => '2026-11-13']);

    $response->assertOk()->assertSee('"total_minutes":90');
});

it('starts and stops a timer, one per task', function (): void {
    $person = User::factory()->employee()->create();
    $activity = mcpActivity($person);

    BClockServer::actingAs($person)->tool(StartTimerTool::class, ['activity_id' => $activity->id])->assertOk();
    BClockServer::actingAs($person)->tool(StartTimerTool::class, ['activity_id' => $activity->id])->assertHasErrors();
    expect(Timer::count())->toBe(1);

    BClockServer::actingAs($person)->tool(StopTimerTool::class)->assertOk()->assertSee('time_entries_created');
    expect(Timer::count())->toBe(0);

    BClockServer::actingAs($person)->tool(StopTimerTool::class)->assertHasErrors();
});

it('does not stop the timer of someone else', function (): void {
    $owner = User::factory()->employee()->create();
    $activity = mcpActivity($owner);
    $timer = Timer::factory()->create(['user_id' => $owner->id, 'activity_id' => $activity->id]);

    BClockServer::actingAs(User::factory()->employee()->create())->tool(StopTimerTool::class, ['timer_id' => $timer->id])->assertHasErrors();

    expect(Timer::count())->toBe(1);
});

it('requests an absence that starts pending, and refuses a non-working day', function (): void {
    $hr = User::factory()->hr()->create();
    $person = User::factory()->employee()->create();

    BClockServer::actingAs($person)->tool(RequestAbsenceTool::class, ['type' => 'ferie', 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-11'])
        ->assertOk()->assertSee('in_attesa');

    expect(Absence::sole()->user_id)->toBe($person->id)
        ->and($hr->unreadNotifications()->count())->toBe(1);

    BClockServer::actingAs($person)->tool(RequestAbsenceTool::class, ['type' => 'permesso', 'starts_on' => '2026-11-14', 'ends_on' => '2026-11-14', 'starts_time' => '10:00', 'ends_time' => '12:00'])
        ->assertHasErrors();

    BClockServer::actingAs($person)->tool(ListMyAbsencesTool::class)->assertOk()->assertSee('2026-11-09');
});

it('shows the projects only to who can see them', function (): void {
    Project::factory()->create(['name' => 'Sito Rossi']);

    BClockServer::actingAs(User::factory()->employee()->create())->tool(ListProjectsTool::class)->assertHasErrors();
    BClockServer::actingAs(User::factory()->boss()->create())->tool(ListProjectsTool::class)->assertOk()->assertSee('Sito Rossi');
});

it('reports the workload of the person day by day', function (): void {
    $person = User::factory()->employee()->create();
    mcpActivity($person);

    $response = BClockServer::actingAs($person)->tool(GetMyWorkloadTool::class, ['from' => '2026-11-09', 'to' => '2026-11-10']);

    $response->assertOk()->assertSee('2026-11-09')->assertSee('capacity_hours');

    BClockServer::actingAs($person)->tool(GetMyWorkloadTool::class, ['from' => '2026-01-01', 'to' => '2026-12-31'])->assertHasErrors();
});

it('lists the notifications and marks them as read', function (): void {
    $person = User::factory()->employee()->create();
    $this->actingAs(User::factory()->boss()->create())->post(route('activities.store'), [
        'project_id' => Project::factory()->create(['starts_on' => '2026-11-01', 'due_on' => '2026-12-31'])->id,
        'name' => 'Nuova', 'description' => '', 'starts_on' => '2026-11-09', 'due_on' => '2026-11-13', 'planned_hours' => 5, 'status' => 'da_fare', 'assignee_ids' => [$person->id],
    ]);

    BClockServer::actingAs($person)->tool(ListNotificationsTool::class)->assertOk()->assertSee('"unread_count":1');
    BClockServer::actingAs($person)->tool(MarkNotificationsReadTool::class)->assertOk()->assertSee('"marked_as_read":1');

    expect($person->unreadNotifications()->count())->toBe(0);
});

it('answers in the language of the person', function (): void {
    $person = User::factory()->employee()->create(['locale' => 'en']);
    $activity = mcpActivity($person);
    BClockServer::actingAs($person)->tool(StartTimerTool::class, ['activity_id' => $activity->id]);

    BClockServer::actingAs($person)->tool(StartTimerTool::class, ['activity_id' => $activity->id])
        ->assertHasErrors(['You already have an active timer on this task.']);
});

it('has the tools declared with a description for the AI', function (): void {
    foreach ([ListMyActivitiesTool::class, LogTimeEntryTool::class, RequestAbsenceTool::class] as $tool) {
        BClockServer::tool($tool)->assertDescription((new ReflectionClass($tool))->getAttributes(Description::class)[0]->getArguments()[0]);
    }
});
