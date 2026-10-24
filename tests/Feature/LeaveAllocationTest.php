<?php

declare(strict_types=1);

use App\Actions\Allocations\GenerateActivityAllocations;
use App\Enum\WorkStatus;
use App\Models\Absence;
use App\Models\Activity;
use App\Models\User;

/**
 * Lunedì 2026-11-09 … venerdì 2026-11-13: cinque giorni lavorativi.
 *
 * @param  list<User>  $assignees
 */
function leaveTestActivity(array $assignees, string $hours): Activity
{
    $activity = Activity::factory()->create([
        'starts_on' => '2026-11-09',
        'due_on' => '2026-11-13',
        'planned_hours' => $hours,
        'status' => WorkStatus::Todo,
    ]);
    $activity->assignees()->attach(collect($assignees)->pluck('id'));

    app(GenerateActivityAllocations::class)->handle($activity);

    return $activity;
}

it('reduces a person\'s share on the day of a confirmed leave in proportion to its hours', function (): void {
    $anna = User::factory()->employee()->create();
    $luca = User::factory()->employee()->create();
    // permesso di 4 ore su 8: peso 0,5 per Luca il lunedì
    Absence::factory()->for($luca)->leave('2026-11-09', '09:00', '13:00')->confirmed()->create();

    // 10 coppie persona-giorno, una a peso 0,5: somma 9,5 -> 1 ora per ogni peso pieno
    $activity = leaveTestActivity([$anna, $luca], '9.50');

    expect((float) $activity->allocations()->where('user_id', $luca->id)->where('date', '2026-11-09')->value('hours'))->toBe(0.5)
        ->and((float) $activity->allocations()->where('user_id', $anna->id)->where('date', '2026-11-09')->value('hours'))->toBe(1.0)
        ->and(round((float) $activity->allocations()->sum('hours'), 2))->toBe(9.5);
});

it('does not reduce anything for a pending leave', function (): void {
    $user = User::factory()->employee()->create();
    Absence::factory()->for($user)->leave()->create();

    $hours = leaveTestActivity([$user], '10.00')->allocations()->pluck('hours')->map(fn ($value) => (float) $value)->unique()->all();

    expect($hours)->toBe([2.0]);
});
