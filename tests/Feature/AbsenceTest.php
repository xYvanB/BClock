<?php

declare(strict_types=1);

use App\Actions\Allocations\GenerateActivityAllocations;
use App\Enum\AbsenceStatus;
use App\Enum\AbsenceType;
use App\Models\Absence;
use App\Models\Activity;
use App\Models\Area;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function absencePayload(array $overrides = []): array
{
    return array_merge([
        'type' => 'ferie',
        'starts_on' => '2026-11-09',
        'ends_on' => '2026-11-11',
        'note' => '',
    ], $overrides);
}

it('lets everyone but the boss request their own absence, which starts pending', function (User $user): void {
    $this->actingAs($user)
        ->post(route('absences.store'), absencePayload(['note' => 'Viaggio']))
        ->assertRedirect(route('absences.index'))
        ->assertSessionHasNoErrors();

    $absence = Absence::firstOrFail();
    expect($absence->user_id)->toBe($user->id)
        ->and($absence->status)->toBe(AbsenceStatus::Pending)
        ->and($absence->type)->toBe(AbsenceType::Vacation)
        ->and($absence->note)->toBe('Viaggio')
        ->and($absence->confirmed_by)->toBeNull();
})->with([
    'area head' => fn () => User::factory()->areaHead()->create(),
    'hr' => fn () => User::factory()->hr()->create(),
    'employee' => fn () => User::factory()->employee()->create(),
]);

it('shows each person only their own absences, and hr everyone\'s', function (): void {
    $employee = User::factory()->employee()->create();
    Absence::factory()->for($employee)->create();
    Absence::factory()->create();

    $this->actingAs($employee)->get(route('absences.index'))
        ->assertInertia(fn (Assert $page) => $page->has('absences', 1));

    $this->actingAs(User::factory()->hr()->create())->get(route('absences.index'))
        ->assertInertia(fn (Assert $page) => $page->has('absences', 2));
});

it('does not take the owner from the payload', function (): void {
    $user = User::factory()->employee()->create();
    $other = User::factory()->employee()->create();

    $this->actingAs($user)->post(route('absences.store'), absencePayload(['user_id' => $other->id]));

    expect(Absence::firstOrFail()->user_id)->toBe($user->id);
});

it('validates the absence', function (array $payload, string $field): void {
    $this->actingAs(User::factory()->employee()->create())
        ->post(route('absences.store'), absencePayload($payload))
        ->assertSessionHasErrors($field);
})->with([
    'unknown type' => [['type' => 'malattia'], 'type'],
    'missing start' => [['starts_on' => ''], 'starts_on'],
    'malformed end' => [['ends_on' => '11/11/2026'], 'ends_on'],
    'end before start' => [['starts_on' => '2026-11-11', 'ends_on' => '2026-11-09'], 'ends_on'],
    'note too long' => [['note' => str_repeat('a', 1001)], 'note'],
]);

it('accepts a one-day absence', function (): void {
    $this->actingAs(User::factory()->employee()->create())
        ->post(route('absences.store'), absencePayload(['starts_on' => '2026-11-09', 'ends_on' => '2026-11-09']))
        ->assertSessionHasNoErrors();
});

it('refuses an absence overlapping one of the same person, but not another person\'s', function (): void {
    $user = User::factory()->employee()->create();
    Absence::factory()->for($user)->create(['starts_on' => '2026-11-09', 'ends_on' => '2026-11-11']);
    Absence::factory()->create(['starts_on' => '2026-11-20', 'ends_on' => '2026-11-21']);

    $this->actingAs($user)
        ->post(route('absences.store'), absencePayload(['starts_on' => '2026-11-11', 'ends_on' => '2026-11-13']))
        ->assertSessionHasErrors('starts_on');

    $this->actingAs($user)
        ->post(route('absences.store'), absencePayload(['starts_on' => '2026-11-12', 'ends_on' => '2026-11-13']))
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post(route('absences.store'), absencePayload(['starts_on' => '2026-11-20', 'ends_on' => '2026-11-21']))
        ->assertSessionHasNoErrors();
});

it('lets the owner edit a pending absence without overlapping itself', function (): void {
    $user = User::factory()->employee()->create();
    $absence = Absence::factory()->for($user)->create(['starts_on' => '2026-11-09', 'ends_on' => '2026-11-11']);

    $this->actingAs($user)
        ->put(route('absences.update', $absence), absencePayload(['ends_on' => '2026-11-12']))
        ->assertRedirect(route('absences.index'))
        ->assertSessionHasNoErrors();

    expect($absence->refresh()->type)->toBe(AbsenceType::Vacation)
        ->and($absence->ends_on->toDateString())->toBe('2026-11-12')
        ->and($absence->status)->toBe(AbsenceStatus::Pending);
});

it('refuses editing someone else\'s absence or a confirmed one', function (): void {
    $owner = User::factory()->employee()->create();
    $pending = Absence::factory()->for($owner)->create();
    $confirmed = Absence::factory()->for($owner)->confirmed()->create(['starts_on' => '2026-12-01', 'ends_on' => '2026-12-02']);

    $this->actingAs(User::factory()->employee()->create())
        ->put(route('absences.update', $pending), absencePayload())->assertForbidden();
    $this->actingAs($owner)
        ->put(route('absences.update', $confirmed), absencePayload(['starts_on' => '2026-12-03', 'ends_on' => '2026-12-04']))->assertForbidden();
    $this->actingAs(User::factory()->hr()->create())
        ->put(route('absences.update', $pending), absencePayload())->assertForbidden();
});

it('lets hr confirm a pending absence and records who and when', function (): void {
    $hr = User::factory()->hr()->create();
    $absence = Absence::factory()->create();

    $this->actingAs($hr)
        ->post(route('absences.confirm', $absence))
        ->assertRedirect(route('absences.index'));

    $absence->refresh();
    expect($absence->status)->toBe(AbsenceStatus::Confirmed)
        ->and($absence->confirmed_by)->toBe($hr->id)
        ->and($absence->confirmed_at)->not->toBeNull();
});

it('forbids confirming to everyone but hr, even the boss and the owner', function (User $user): void {
    $absence = Absence::factory()->for($user)->create();

    $this->actingAs($user)->post(route('absences.confirm', $absence))->assertForbidden();

    expect($absence->refresh()->status)->toBe(AbsenceStatus::Pending);
})->with([
    'boss' => fn () => User::factory()->boss()->create(),
    'area head' => fn () => User::factory()->areaHead()->create(),
    'employee' => fn () => User::factory()->employee()->create(),
]);

it('does not confirm an absence twice', function (): void {
    $absence = Absence::factory()->confirmed()->create();

    $this->actingAs(User::factory()->hr()->create())
        ->post(route('absences.confirm', $absence))
        ->assertForbidden();
});

it('lets the owner delete a pending absence but not a confirmed one', function (): void {
    $owner = User::factory()->employee()->create();
    $pending = Absence::factory()->for($owner)->create();
    $confirmed = Absence::factory()->for($owner)->confirmed()->create(['starts_on' => '2026-12-01', 'ends_on' => '2026-12-02']);

    $this->actingAs($owner)->delete(route('absences.destroy', $confirmed))->assertForbidden();
    $this->actingAs($owner)->delete(route('absences.destroy', $pending))->assertRedirect(route('absences.index'));

    $this->assertDatabaseMissing('absences', ['id' => $pending->id]);
    $this->assertDatabaseHas('absences', ['id' => $confirmed->id]);
});

it('lets hr delete any absence, and nobody else delete someone else\'s', function (): void {
    $confirmed = Absence::factory()->confirmed()->create();

    $this->actingAs(User::factory()->boss()->create())->delete(route('absences.destroy', $confirmed))->assertForbidden();
    $this->actingAs(User::factory()->hr()->create())
        ->delete(route('absences.destroy', $confirmed), ['reason' => 'Errore di inserimento'])
        ->assertRedirect(route('absences.index'));

    $this->assertDatabaseCount('absences', 0);
});

it('requires a reason when hr deletes a confirmed absence, and tells the owner', function (): void {
    $owner = User::factory()->employee()->create();
    $confirmed = Absence::factory()->for($owner)->confirmed()->create();
    $hr = User::factory()->hr()->create();

    $this->actingAs($hr)->delete(route('absences.destroy', $confirmed))->assertSessionHasErrors('reason');
    $this->assertDatabaseCount('absences', 1);

    $this->actingAs($hr)->delete(route('absences.destroy', $confirmed), ['reason' => 'Picco di lavoro'])->assertSessionHasNoErrors();

    $this->assertDatabaseCount('absences', 0);
    expect($owner->unreadNotifications)->toHaveCount(1)
        ->and($owner->unreadNotifications->first()->data['message'])->toContain('Picco di lavoro');
});

it('does not require a reason for hr deleting a pending absence', function (): void {
    $pending = Absence::factory()->create();

    $this->actingAs(User::factory()->hr()->create())->delete(route('absences.destroy', $pending))->assertSessionHasNoErrors();

    $this->assertDatabaseCount('absences', 0);
});

it('lets hr reject a pending absence with a reason, which the owner then sees', function (): void {
    $owner = User::factory()->employee()->create();
    $absence = Absence::factory()->for($owner)->create();
    $hr = User::factory()->hr()->create();

    $this->actingAs($hr)->post(route('absences.reject', $absence), [])->assertSessionHasErrors('reason');
    expect($absence->refresh()->status)->toBe(AbsenceStatus::Pending);

    $this->actingAs($hr)->post(route('absences.reject', $absence), ['reason' => 'Troppi assenti'])
        ->assertRedirect(route('absences.index'));

    expect($absence->refresh()->status)->toBe(AbsenceStatus::Rejected)
        ->and($absence->rejection_reason)->toBe('Troppi assenti');

    $this->actingAs($owner)->get(route('absences.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('absences.0.rejection_reason', 'Troppi assenti')
            ->where('absences.0.can_delete', true)
            ->where('absences.0.can_update', false));
});

it('forbids rejecting to everyone but hr, and rejecting a decided absence', function (): void {
    $absence = Absence::factory()->create();

    foreach ([User::factory()->boss()->create(), User::factory()->areaHead()->create(), $absence->user] as $user) {
        $this->actingAs($user)->post(route('absences.reject', $absence), ['reason' => 'x'])->assertForbidden();
    }

    $confirmed = Absence::factory()->confirmed()->create();
    $this->actingAs(User::factory()->hr()->create())
        ->post(route('absences.reject', $confirmed), ['reason' => 'x'])->assertForbidden();
});

it('ignores rejected absences when checking overlaps, and lets the owner delete them', function (): void {
    $user = User::factory()->employee()->create();
    $rejected = Absence::factory()->for($user)->create(['status' => AbsenceStatus::Rejected, 'rejection_reason' => 'No']);

    $this->actingAs($user)->post(route('absences.store'), absencePayload())->assertSessionHasNoErrors();
    $this->actingAs($user)->delete(route('absences.destroy', $rejected))->assertRedirect(route('absences.index'));

    $this->assertDatabaseMissing('absences', ['id' => $rejected->id]);
});

it('asks the boss, not hr, to confirm an absence of hr, and tells every boss', function (): void {
    $hr = User::factory()->hr()->create();
    $bosses = User::factory()->boss()->count(2)->create();
    $other = User::factory()->hr()->create();

    $this->actingAs($hr)->post(route('absences.store'), absencePayload())->assertSessionHasNoErrors();
    $absence = Absence::firstOrFail();

    foreach ($bosses as $boss) {
        expect($boss->unreadNotifications()->count())->toBe(1);
    }
    expect($other->unreadNotifications()->count())->toBe(0);

    // né l'HR stessa né un'altra HR possono confermarla: solo il Capo
    $this->actingAs($hr)->post(route('absences.confirm', $absence))->assertForbidden();
    $this->actingAs($other)->post(route('absences.confirm', $absence))->assertForbidden();
    expect($absence->refresh()->status)->toBe(AbsenceStatus::Pending);

    $this->actingAs($bosses->first())->post(route('absences.confirm', $absence))->assertSessionHasNoErrors();

    expect($absence->refresh()->status)->toBe(AbsenceStatus::Confirmed)
        ->and($absence->confirmed_by)->toBe($bosses->first()->id);
});

it('lets the boss reject an absence of hr with a reason, and shows it to the boss only among the others', function (): void {
    $hr = User::factory()->hr()->create();
    $boss = User::factory()->boss()->create();
    $mine = Absence::factory()->for($boss)->create(['starts_on' => '2026-12-01', 'ends_on' => '2026-12-01']);
    $hrAbsence = Absence::factory()->for($hr)->create();
    $employeeAbsence = Absence::factory()->for(User::factory()->employee()->create())->create(['starts_on' => '2026-12-10', 'ends_on' => '2026-12-10']);

    $this->actingAs($boss)->get(route('absences.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('absences', 2)
            ->where('absences', fn ($rows) => collect($rows)->pluck('id')->sort()->values()->all() === collect([$mine->id, $hrAbsence->id])->sort()->values()->all()));

    $this->actingAs($boss)->post(route('absences.reject', $hrAbsence), ['reason' => 'Periodo di chiusura bilanci'])->assertSessionHasNoErrors();
    expect($hrAbsence->refresh()->status)->toBe(AbsenceStatus::Rejected);

    // la persona non conferma né rifiuta le proprie, e il Capo non decide sulle assenze dei dipendenti
    $this->actingAs($boss)->post(route('absences.confirm', $employeeAbsence))->assertForbidden();
    $this->actingAs($boss)->post(route('absences.confirm', $mine))->assertForbidden();
});

it('shows the boss the notification of an absence of hr and lets them mark it as read', function (): void {
    $hr = User::factory()->hr()->create();
    $boss = User::factory()->boss()->create();
    $this->actingAs($hr)->post(route('absences.store'), absencePayload());

    $this->actingAs($boss)->get(route('absences.index'))
        ->assertInertia(fn (Assert $page) => $page->where('notifications.unread_count', 1)->where('absences.0.approver_label', 'Capo'));

    $this->actingAs($boss)->post(route('notifications.read-all'))->assertRedirect();

    $this->actingAs($boss->fresh())->get(route('absences.index'))
        ->assertInertia(fn (Assert $page) => $page->where('notifications.unread_count', 0));
});

it('lets the area head see the absences of their area, read-only, and not other areas\'', function (): void {
    $area = Area::factory()->create();
    $head = User::factory()->areaHead($area)->create();
    $mine = Absence::factory()->for(User::factory()->employee($area)->create())->create();
    Absence::factory()->for(User::factory()->employee(Area::factory()->create())->create())->create();
    $own = Absence::factory()->for($head)->create(['starts_on' => '2026-12-01', 'ends_on' => '2026-12-02']);

    $this->actingAs($head)->get(route('absences.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('absences', 2)
            ->where('absences', fn ($rows) => collect($rows)->pluck('id')->sort()->values()->all() === collect([$mine->id, $own->id])->sort()->values()->all())
            ->where('absences.1.can_confirm', false));

    $this->actingAs($head)->post(route('absences.confirm', $mine))->assertForbidden();
    $this->actingAs($head)->delete(route('absences.destroy', $mine))->assertForbidden();
});

it('exposes per-row permissions to the page', function (): void {
    $hr = User::factory()->hr()->create();
    Absence::factory()->create();

    $this->actingAs($hr)->get(route('absences.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('absences.0.can_confirm', true)
            ->where('absences.0.can_update', false)
            ->where('absences.0.can_delete', true));
});

it('confirms the absences of the boss on the spot, with nobody to approve them', function (): void {
    $boss = User::factory()->boss()->create();
    User::factory()->hr()->create();

    $this->actingAs($boss)->post(route('absences.store'), absencePayload())->assertSessionHasNoErrors();

    $absence = Absence::firstOrFail();
    expect($absence->status)->toBe(AbsenceStatus::Confirmed)
        ->and($absence->isFullyApproved())->toBeTrue()
        ->and($boss->unreadNotifications()->count())->toBe(0);

    $this->actingAs($boss)->get(route('absences.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('absences.0.can_confirm', false)
            ->where('absences.0.can_approve', false)
            ->where('absences.0.can_reject', false)
            ->where('absences.0.can_update', true)
            ->where('absences.0.can_delete', true));

    $this->actingAs(User::factory()->hr()->create())->post(route('absences.confirm', $absence))->assertForbidden();
});

it('lets the boss edit and delete their own confirmed absence, which stays confirmed', function (): void {
    $boss = User::factory()->boss()->create();
    $this->actingAs($boss)->post(route('absences.store'), absencePayload());
    $absence = Absence::firstOrFail();

    $this->actingAs($boss)->put(route('absences.update', $absence), absencePayload(['ends_on' => '2026-11-12']))->assertSessionHasNoErrors();
    expect($absence->refresh()->status)->toBe(AbsenceStatus::Confirmed)
        ->and($absence->ends_on->toDateString())->toBe('2026-11-12')
        ->and($absence->isFullyApproved())->toBeTrue();

    $this->actingAs($boss)->delete(route('absences.destroy', $absence))->assertSessionHasNoErrors();
    $this->assertDatabaseCount('absences', 0);
});

it('reduces the quotas of the boss as soon as the absence is saved, and gives them back when it is deleted', function (): void {
    $boss = User::factory()->boss()->create();
    $activity = Activity::factory()->create(['starts_on' => '2026-11-09', 'due_on' => '2026-11-13', 'planned_hours' => '10.00']);
    $activity->assignees()->attach($boss);
    app(GenerateActivityAllocations::class)->handle($activity);
    expect($activity->allocations()->count())->toBe(5);

    $this->actingAs($boss)->post(route('absences.store'), absencePayload(['starts_on' => '2026-11-09', 'ends_on' => '2026-11-10']));
    expect($activity->allocations()->count())->toBe(3);

    $this->actingAs($boss)->delete(route('absences.destroy', Absence::firstOrFail()));
    expect($activity->allocations()->count())->toBe(5);
});
