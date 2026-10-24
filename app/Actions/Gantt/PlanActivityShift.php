<?php

declare(strict_types=1);

namespace App\Actions\Gantt;

use App\Enum\WorkStatus;
use App\Models\Activity;
use App\Models\Holiday;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class PlanActivityShift
{
    public const string MODE_MOVE = 'move';

    public const string MODE_RESIZE_START = 'resize_start';

    public const string MODE_RESIZE_END = 'resize_end';

    /** @var array<string, true>|null */
    private ?array $holidays = null;

    /**
     * Calcola, senza modificare nulla, cosa succede se si sposta o si ridimensiona un'attività nel Gantt.
     *
     * - `move`: l'attività si sposta di tanti giorni lavorativi quanti ne separano il vecchio inizio dal nuovo
     *   (inizio e scadenza insieme);
     * - `resize_end`: cambia solo la scadenza;
     * - `resize_start`: cambia solo l'inizio.
     *
     * Con `move` e `resize_end` tutte le attività che dipendono da questa, direttamente o no, si spostano dello stesso
     * numero di giorni lavorativi. Il piano elenca i cambi, le scadenze di progetto da estendere e i motivi che
     * impediscono tutto lo spostamento (attività chiuse nella catena, permessi mancanti, date incoerenti): se c'è
     * anche un solo blocco non si sposta niente.
     *
     * @return array{
     *     mode: string,
     *     shift: int,
     *     changes: list<array{activity_id: int, name: string, project: string, starts_on: string, due_on: string, new_starts_on: string, new_due_on: string, shift_manual: bool}>,
     *     projects: list<array{project_id: int, name: string, due_on: string, new_due_on: string}>,
     *     blockers: list<string>
     * }
     */
    public function handle(User $user, Activity $activity, string $mode, string $startsOn, string $dueOn): array
    {
        $plan = ['mode' => $mode, 'shift' => 0, 'changes' => [], 'projects' => [], 'blockers' => []];

        if (! in_array($mode, [self::MODE_MOVE, self::MODE_RESIZE_START, self::MODE_RESIZE_END], true)) {
            return $plan + ['blockers' => ['Operazione non riconosciuta.']];
        }

        $oldStart = $activity->starts_on->toDateString();
        $oldDue = $activity->due_on->toDateString();

        [$newStart, $newDue, $shift] = match ($mode) {
            self::MODE_MOVE => (function () use ($oldStart, $oldDue, $startsOn): array {
                $shift = $this->workingDaysBetween($oldStart, $startsOn);

                return [$this->shiftWorkingDays($oldStart, $shift), $this->shiftWorkingDays($oldDue, $shift), $shift];
            })(),
            self::MODE_RESIZE_END => [$oldStart, $dueOn, $this->workingDaysBetween($oldDue, $dueOn)],
            default => [$startsOn, $oldDue, 0],
        };

        $plan['shift'] = $shift;

        if ($newStart > $newDue) {
            $plan['blockers'][] = 'La scadenza non può precedere l\'inizio.';

            return $plan;
        }

        if ($newStart === $oldStart && $newDue === $oldDue) {
            return $plan;
        }

        $cascades = $mode !== self::MODE_RESIZE_START && $shift !== 0;
        $chain = $cascades ? $this->dependentsOf($activity) : collect();

        $moves = collect([[$activity, $newStart, $newDue, $mode === self::MODE_MOVE]]);
        foreach ($chain as $dependent) {
            $moves->push([
                $dependent,
                $this->shiftWorkingDays($dependent->starts_on->toDateString(), $shift),
                $this->shiftWorkingDays($dependent->due_on->toDateString(), $shift),
                true,
            ]);
        }

        $extensions = [];

        foreach ($moves as [$moved, $movedStart, $movedDue, $shiftManual]) {
            /** @var Activity $moved */
            if (in_array($moved->status, [WorkStatus::Done, WorkStatus::Archived], true)) {
                $plan['blockers'][] = "«{$moved->name}» è {$this->statusLabel($moved->status)} e non si può spostare.";
            }

            if (! $user->can('update', $moved)) {
                $plan['blockers'][] = "Non hai i permessi per spostare «{$moved->name}».";
            }

            $project = $moved->project;

            if ($movedStart < $project->starts_on->toDateString()) {
                $plan['blockers'][] = "«{$moved->name}» inizierebbe prima dell'inizio del progetto «{$project->name}».";
            }

            if ($movedDue > $project->due_on->toDateString()) {
                $extensions[$project->id] = [
                    'project_id' => $project->id,
                    'name' => $project->name,
                    'due_on' => $project->due_on->toDateString(),
                    'new_due_on' => max($movedDue, $extensions[$project->id]['new_due_on'] ?? ''),
                ];
            }

            $plan['changes'][] = [
                'activity_id' => $moved->id,
                'name' => $moved->name,
                'project' => $project->name,
                'starts_on' => $moved->starts_on->toDateString(),
                'due_on' => $moved->due_on->toDateString(),
                'new_starts_on' => $movedStart,
                'new_due_on' => $movedDue,
                'shift_manual' => $shiftManual && $shift !== 0,
            ];
        }

        $plan['projects'] = array_values($extensions);

        return $plan;
    }

    /**
     * Sposta una data di `$days` giorni lavorativi (indietro se negativo); weekend e festivi non contano.
     */
    public function shiftWorkingDays(string $date, int $days): string
    {
        $current = CarbonImmutable::parse($date);
        $step = $days <=> 0;

        for ($remaining = abs($days); $remaining > 0;) {
            $current = $current->addDays($step);

            if ($this->isWorkingDay($current)) {
                $remaining--;
            }
        }

        return $current->toDateString();
    }

    /**
     * Giorni lavorativi con segno per andare da `$from` a `$to`: in avanti quelli in (from, to], indietro quelli in [to, from).
     */
    public function workingDaysBetween(string $from, string $to): int
    {
        if ($from === $to) {
            return 0;
        }

        $count = 0;

        if ($to > $from) {
            for ($day = CarbonImmutable::parse($from)->addDay(); $day->toDateString() <= $to; $day = $day->addDay()) {
                $count += $this->isWorkingDay($day) ? 1 : 0;
            }

            return $count;
        }

        for ($day = CarbonImmutable::parse($to); $day->toDateString() < $from; $day = $day->addDay()) {
            $count += $this->isWorkingDay($day) ? 1 : 0;
        }

        return -$count;
    }

    /**
     * Tutte le attività che dipendono da questa, direttamente o no (senza ripetizioni).
     *
     * @return Collection<int, Activity>
     */
    private function dependentsOf(Activity $activity): Collection
    {
        $found = collect();
        $queue = [$activity];

        while ($queue !== []) {
            $current = array_pop($queue);

            foreach ($current->dependents()->with('project')->get() as $dependent) {
                if ($dependent->id !== $activity->id && ! $found->has($dependent->id)) {
                    $found->put($dependent->id, $dependent);
                    $queue[] = $dependent;
                }
            }
        }

        return $found->values();
    }

    private function isWorkingDay(CarbonImmutable $day): bool
    {
        $this->holidays ??= Holiday::query()->get()
            ->mapWithKeys(fn (Holiday $holiday): array => [$holiday->date->toDateString() => true])
            ->all();

        return ! $day->isWeekend() && ! isset($this->holidays[$day->toDateString()]);
    }

    private function statusLabel(WorkStatus $status): string
    {
        return $status === WorkStatus::Done ? 'completata' : 'archiviata';
    }
}
