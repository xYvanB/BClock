<?php

declare(strict_types=1);

use App\Enum\AbsenceStatus;
use App\Enum\AbsenceType;
use App\Models\Absence;
use App\Models\Area;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function leaveRequest(array $overrides = []): array
{
    return array_merge([
        'type' => 'permesso',
        'starts_on' => '2026-11-09',
        'ends_on' => '2026-11-09',
        'starts_time' => '09:00',
        'ends_time' => '11:30',
        'note' => '',
    ], $overrides);
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function vacationRequest(array $overrides = []): array
{
    return array_merge(['type' => 'ferie', 'starts_on' => '2026-11-09', 'ends_on' => '2026-11-11', 'note' => ''], $overrides);
}

it('stores a leave with its times and computes its hours', function (): void {
    $this->actingAs(User::factory()->employee()->create())
        ->post(route('absences.store'), leaveRequest())
        ->assertSessionHasNoErrors();

    $absence = Absence::firstOrFail();
    expect($absence->type)->toBe(AbsenceType::Leave)
        ->and($absence->leaveHours())->toBe(2.5)
        ->and($absence->timeLabel($absence->starts_time))->toBe('09:00');
});

it('validates the leave times', function (array $overrides, string $field): void {
    $this->actingAs(User::factory()->employee()->create())
        ->post(route('absences.store'), leaveRequest($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'missing start time' => [['starts_time' => ''], 'starts_time'],
    'missing end time' => [['ends_time' => ''], 'ends_time'],
    'malformed time' => [['starts_time' => '9am'], 'starts_time'],
    'end before start' => [['starts_time' => '11:00', 'ends_time' => '10:00'], 'ends_time'],
    'more than the daily capacity' => [['starts_time' => '08:00', 'ends_time' => '17:00'], 'ends_time'],
    'more than one day' => [['ends_on' => '2026-11-10'], 'ends_on'],
]);

it('ignores times sent with a vacation', function (): void {
    $this->actingAs(User::factory()->employee()->create())
        ->post(route('absences.store'), vacationRequest(['starts_time' => '09:00', 'ends_time' => '10:00']))
        ->assertSessionHasNoErrors();

    expect(Absence::firstOrFail()->starts_time)->toBeNull();
});

it('allows two leaves on the same day only when their times do not overlap, and none over a vacation', function (): void {
    $user = User::factory()->employee()->create();
    Absence::factory()->for($user)->leave('2026-11-09', '09:00', '11:00')->create();

    $this->actingAs($user)->post(route('absences.store'), leaveRequest(['starts_time' => '10:00', 'ends_time' => '12:00']))
        ->assertSessionHasErrors('starts_on');
    $this->actingAs($user)->post(route('absences.store'), leaveRequest(['starts_time' => '11:00', 'ends_time' => '12:00']))
        ->assertSessionHasNoErrors();
    $this->actingAs($user)->post(route('absences.store'), vacationRequest(['starts_on' => '2026-11-09', 'ends_on' => '2026-11-09']))
        ->assertSessionHasErrors('starts_on');
});

it('tells the area head when an employee of their area requests an absence, and nobody else', function (): void {
    $area = Area::factory()->create();
    $head = User::factory()->areaHead($area)->create();
    $otherHead = User::factory()->areaHead()->create();
    $employee = User::factory()->employee($area)->create();

    $this->actingAs($employee)->post(route('absences.store'), leaveRequest());

    expect($head->unreadNotifications()->count())->toBe(1)
        ->and($head->unreadNotifications()->first()->data['message'])->toContain($employee->fullName())
        ->and($otherHead->unreadNotifications()->count())->toBe(0);

    // an HR does not go through the area head
    $this->actingAs(User::factory()->hr()->create())->post(route('absences.store'), vacationRequest());
    expect($head->unreadNotifications()->count())->toBe(1);
});

it('needs both the area head and hr for an employee, in any order', function (bool $hrFirst): void {
    $area = Area::factory()->create();
    $head = User::factory()->areaHead($area)->create();
    $hr = User::factory()->hr()->create();
    $absence = Absence::factory()->for(User::factory()->employee($area)->create())->create();

    $steps = [
        fn () => $this->actingAs($head)->post(route('absences.approve', $absence))->assertSessionHasNoErrors(),
        fn () => $this->actingAs($hr)->post(route('absences.confirm', $absence))->assertSessionHasNoErrors(),
    ];
    if ($hrFirst) {
        $steps = array_reverse($steps);
    }

    $steps[0]();
    expect($absence->refresh()->status)->toBe(AbsenceStatus::Pending);

    $steps[1]();
    expect($absence->refresh()->status)->toBe(AbsenceStatus::Confirmed)
        ->and($absence->isApprovedByAreaHead())->toBeTrue()
        ->and($absence->isApprovedByHr())->toBeTrue();
})->with([
    'area head first' => [false],
    'hr first' => [true],
]);

it('lets only the area head of the employee approve, and only once', function (): void {
    $area = Area::factory()->create();
    $head = User::factory()->areaHead($area)->create();
    $absence = Absence::factory()->for(User::factory()->employee($area)->create())->create();

    foreach ([User::factory()->areaHead()->create(), User::factory()->hr()->create(), User::factory()->boss()->create(), $absence->user] as $user) {
        $this->actingAs($user)->post(route('absences.approve', $absence))->assertForbidden();
    }

    $this->actingAs($head)->post(route('absences.approve', $absence))->assertSessionHasNoErrors();
    $this->actingAs($head)->post(route('absences.approve', $absence))->assertForbidden();
    $this->actingAs($head)->post(route('absences.confirm', $absence))->assertForbidden();
});

it('does not ask the area head for people who are not employees of an area', function (): void {
    $area = Area::factory()->create();
    $head = User::factory()->areaHead($area)->create();
    $loneEmployee = Absence::factory()->for(User::factory()->employee()->create())->create();
    $colleagueHead = Absence::factory()->for(User::factory()->areaHead($area)->create())->create(['starts_on' => '2026-12-01', 'ends_on' => '2026-12-01']);

    $this->actingAs($head)->post(route('absences.approve', $loneEmployee))->assertForbidden();
    $this->actingAs($head)->post(route('absences.approve', $colleagueHead))->assertForbidden();

    $this->actingAs(User::factory()->hr()->create())->post(route('absences.confirm', $loneEmployee));
    expect($loneEmployee->refresh()->status)->toBe(AbsenceStatus::Confirmed);
});

it('lets the area head reject an employee absence with a reason, but not one of another area', function (): void {
    $area = Area::factory()->create();
    $head = User::factory()->areaHead($area)->create();
    $absence = Absence::factory()->for(User::factory()->employee($area)->create())->create();

    $this->actingAs($head)->post(route('absences.reject', $absence), [])->assertSessionHasErrors('reason');
    $this->actingAs($head)->post(route('absences.reject', $absence), ['reason' => 'Consegna vicina'])->assertSessionHasNoErrors();

    expect($absence->refresh()->status)->toBe(AbsenceStatus::Rejected);

    $outsider = Absence::factory()->for(User::factory()->employee(Area::factory()->create())->create())->create(['starts_on' => '2026-12-01', 'ends_on' => '2026-12-01']);
    $this->actingAs($head)->post(route('absences.reject', $outsider), ['reason' => 'x'])->assertForbidden();
});

it('clears the approvals when the owner edits the request', function (): void {
    $area = Area::factory()->create();
    $employee = User::factory()->employee($area)->create();
    $absence = Absence::factory()->for($employee)->create();
    $this->actingAs(User::factory()->areaHead($area)->create())->post(route('absences.approve', $absence));

    $this->actingAs($employee)->put(route('absences.update', $absence->refresh()), vacationRequest(['ends_on' => '2026-11-12']))->assertSessionHasNoErrors();

    expect($absence->refresh()->isApprovedByAreaHead())->toBeFalse();
});

it('exposes the approval progress and the leave hours to the page', function (): void {
    $area = Area::factory()->create();
    $head = User::factory()->areaHead($area)->create();
    Absence::factory()->for(User::factory()->employee($area)->create())->leave()->create();

    $this->actingAs($head)->get(route('absences.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('absences.0.needs_area_head', true)
            ->where('absences.0.area_head_approved', false)
            ->where('absences.0.hr_approved', false)
            ->where('absences.0.hours', 2)
            ->where('absences.0.starts_time', '09:00')
            ->where('absences.0.can_approve', true)
            ->where('absences.0.can_confirm', false)
            ->where('absences.0.can_reject', true));
});
