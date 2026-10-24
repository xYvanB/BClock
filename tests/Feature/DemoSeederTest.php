<?php

declare(strict_types=1);

use App\Enum\AbsenceStatus;
use App\Enum\WorkStatus;
use App\Models\Absence;
use App\Models\Activity;
use App\Models\TimeEntry;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;

it('seeds a coherent demo data set', function (): void {
    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBeGreaterThanOrEqual(14)
        ->and(Activity::count())->toBeGreaterThanOrEqual(20)
        ->and(TimeEntry::count())->toBeGreaterThan(50);

    // ogni attività aperta ha le quote che sommano alle ore previste
    Activity::whereIn('status', [WorkStatus::Todo, WorkStatus::InProgress, WorkStatus::WaitingClient])->get()->each(function (Activity $activity): void {
        expect(round((float) $activity->allocations()->sum('hours'), 2))->toBe(round((float) $activity->planned_hours, 2), $activity->name);
    });

    // le attività stanno dentro il loro progetto, e le dipendenze nello stesso progetto
    Activity::with(['project', 'dependencies'])->get()->each(function (Activity $activity): void {
        expect($activity->starts_on->toDateString() >= $activity->project->starts_on->toDateString())->toBeTrue($activity->name)
            ->and($activity->due_on->toDateString() <= $activity->project->due_on->toDateString())->toBeTrue($activity->name);
        $activity->dependencies->each(fn (Activity $dependency) => expect($dependency->project_id)->toBe($activity->project_id));
    });

    // nessuna persona ha registrazioni sovrapposte
    TimeEntry::all()->groupBy(fn (TimeEntry $entry): string => $entry->user_id.'|'.$entry->date->toDateString())->each(function ($entries): void {
        $sorted = $entries->sortBy('started_at')->values();
        foreach ($sorted as $index => $entry) {
            if ($index > 0) {
                expect($entry->started_at >= $sorted[$index - 1]->ended_at)->toBeTrue();
            }
        }
    });

    // le assenze confermate dei dipendenti hanno la doppia approvazione; ci sono tutti gli stati
    Absence::where('status', AbsenceStatus::Confirmed)->get()->each(fn (Absence $absence) => expect($absence->isFullyApproved())->toBeTrue());
    expect(Absence::pluck('status')->unique()->count())->toBe(3);
});

it('does not duplicate the demo data when run again', function (): void {
    $this->seed(DatabaseSeeder::class);
    $users = User::count();
    $activities = Activity::count();

    $this->seed(DemoDataSeeder::class);

    expect(User::count())->toBe($users)->and(Activity::count())->toBe($activities);
});
