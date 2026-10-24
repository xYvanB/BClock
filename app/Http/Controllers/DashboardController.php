<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Dashboard\DayHoursReport;
use App\Actions\Dashboard\ProjectHoursReport;
use App\Actions\Dashboard\WorkedHoursReport;
use App\Enum\Role;
use App\Http\Requests\ExportTimeEntriesRequest;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    /** Maximum days of a period, to avoid building huge tables. */
    private const int MAX_PERIOD_DAYS = 366;

    /**
     * Dashboard (F11): different cards per role, each with its own filters.
     * - Hours per project and hours per person in a day: Boss, area head (their area) and Employee (their own);
     * - Worked hours: all roles (HR sees only this one).
     */
    public function index(
        Request $request,
        ProjectHoursReport $projectHours,
        DayHoursReport $dayHours,
        WorkedHoursReport $workedHours,
    ): Response {
        $user = $request->user();
        $today = CarbonImmutable::today(config('app.local_timezone'));

        [$from, $to] = $this->period($request, $today);
        $day = $this->date($request->query('day')) ?? $today->toDateString();

        $showsProjectCards = ! $user->hasRole(Role::Hr);

        return Inertia::render('dashboard', [
            'filters' => [
                'from' => $from,
                'to' => $to,
                'project_id' => $request->integer('project_id') ?: null,
                'day' => $day,
                'user_ids' => array_values(array_filter(array_map('intval', (array) $request->query('user_ids', [])))),
                'user_id' => $request->integer('user_id') ?: null,
                'area_id' => $request->integer('area_id') ?: null,
                'granularity' => $request->query('granularity', 'week'),
            ],
            'project_hours' => $showsProjectCards
                ? $projectHours->handle($user, $from, $to, $request->integer('project_id') ?: null)
                : null,
            'day_hours' => $showsProjectCards
                ? $dayHours->handle($user, $day, array_values(array_filter(array_map('intval', (array) $request->query('user_ids', [])))))
                : null,
            'worked_hours' => $workedHours->handle(
                $user,
                $from,
                $to,
                (string) $request->query('granularity', 'week'),
                $request->integer('user_id') ?: null,
                $user->hasRole(Role::Boss, Role::Hr) ? ($request->integer('area_id') ?: null) : null,
            ),
            'can_export' => $user->hasRole(Role::Boss, Role::AreaHead, Role::Hr),
        ]);
    }

    /**
     * CSV export of the entries in the period, filterable by project and area (the area head sees only their own).
     */
    public function export(ExportTimeEntriesRequest $request): StreamedResponse
    {
        $user = $request->user();
        $today = CarbonImmutable::today(config('app.local_timezone'));
        [$from, $to] = $this->period($request, $today);
        $areaId = $user->hasRole(Role::Boss, Role::Hr) ? ($request->integer('area_id') ?: null) : null;
        $projectId = $request->integer('project_id') ?: null;

        $entries = TimeEntry::query()
            ->visibleTo($user)
            ->with(['user:id,name,surname,area_id', 'user.area:id,name', 'activity:id,name,project_id', 'activity.project:id,name,client_id', 'activity.project.client:id,company_name'])
            ->whereBetween('time_entries.date', [$from, $to])
            ->when($areaId, fn ($query, $id) => $query->whereIn('time_entries.user_id', User::query()->where('area_id', $id)->select('id')))
            ->when($projectId, fn ($query, $id) => $query->whereHas('activity', fn ($q) => $q->where('project_id', $id)))
            ->orderBy('time_entries.date')
            ->orderBy('time_entries.started_at');

        return response()->streamDownload(function () use ($entries): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Data', 'Progetto', 'Cliente', 'Attività', 'Persona', 'Area', 'Inizio', 'Fine', 'Ore', 'Descrizione'], ';');

            $safe = fn (?string $text): ?string => $text !== null && preg_match('/^[=+\-@\t\r]/', $text) === 1 ? "'".$text : $text;

            $entries->chunk(500, function ($chunk) use ($out, $safe): void {
                foreach ($chunk as $entry) {
                    fputcsv($out, [
                        $entry->date->format('d/m/Y'),
                        $safe($entry->activity->project->name),
                        $safe($entry->activity->project->client->company_name),
                        $safe($entry->activity->name),
                        $safe($entry->user->fullName()),
                        $safe($entry->user->area?->name),
                        substr($entry->started_at, 0, 5),
                        substr($entry->ended_at, 0, 5),
                        number_format($entry->duration_minutes / 60, 2, ',', ''),
                        $safe($entry->description),
                    ], ';');
                }
            });

            fclose($out);
        }, "ore-{$from}-{$to}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Requested period (default: the current month); the end does not precede the start and the period has a cap.
     *
     * @return array{string, string}
     */
    private function period(Request $request, CarbonImmutable $today): array
    {
        $from = $this->date($request->query('from')) ?? $today->startOfMonth()->toDateString();
        $to = $this->date($request->query('to')) ?? $today->endOfMonth()->toDateString();

        if ($to < $from) {
            $to = $from;
        }

        $latest = CarbonImmutable::parse($from)->addDays(self::MAX_PERIOD_DAYS - 1)->toDateString();

        return [$from, min($to, $latest)];
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->toDateString() === $value ? $value : null;
    }
}
