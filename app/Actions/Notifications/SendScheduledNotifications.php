<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Enum\NotificationKind;
use App\Enum\Role;
use App\Enum\WorkStatus;
use App\Models\Activity;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Models\Timer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

final class SendScheduledNotifications
{
    /** Una scadenza è "vicina" quando cade oggi o nei giorni indicati. */
    public const int DUE_SOON_DAYS = 1;

    public function __construct(private readonly NotifyUsers $notify) {}

    /**
     * Manda i promemoria che dipendono dal tempo: scadenza vicina, timer dimenticato, sforamento delle ore di un progetto.
     * Ogni promemoria arriva una volta sola a ciascuna persona (tabella `sent_reminders`), anche se il comando gira ogni ora.
     *
     * @return int Quanti promemoria sono stati mandati
     *
     * @throws Throwable
     */
    public function handle(): int
    {
        return DB::transaction(fn (): int => $this->dueSoon() + $this->forgottenTimers() + $this->overrunProjects());
    }

    private function dueSoon(): int
    {
        $today = now(config('app.local_timezone'))->startOfDay();
        $sent = 0;

        $activities = Activity::query()
            ->whereNotIn('status', [WorkStatus::Done, WorkStatus::Archived])
            ->whereBetween('due_on', [$today->toDateString(), $today->copy()->addDays(self::DUE_SOON_DAYS)->toDateString()])
            ->with('assignees')
            ->get();

        foreach ($activities as $activity) {
            foreach ($activity->assignees as $user) {
                $sent += $this->once($user, "due:{$activity->id}:{$activity->due_on->toDateString()}", NotificationKind::DueSoon, "Scadenza vicina: «{$activity->name}» ({$activity->due_on->format('d/m/Y')}).", route('calendar.index'));
            }
        }

        return $sent;
    }

    private function forgottenTimers(): int
    {
        $sent = 0;

        $timers = Timer::query()
            ->where('started_at', '<=', now()->subMinutes(Timer::WARNING_AFTER_MINUTES))
            ->with(['user', 'activity'])
            ->get();

        foreach ($timers as $timer) {
            $sent += $this->once($timer->user, "timer:{$timer->id}", NotificationKind::TimerForgotten, "Timer attivo da oltre 8 ore: «{$timer->activity->name}». Ricordati di fermarlo.", route('time-entries.index'));
        }

        return $sent;
    }

    private function overrunProjects(): int
    {
        $sent = 0;

        $projects = Project::query()
            ->where('planned_hours', '>', 0)
            ->whereNotIn('status', [WorkStatus::Archived])
            ->with('members')
            ->get();

        foreach ($projects as $project) {
            $used = (int) TimeEntry::query()
                ->join('activities', 'activities.id', '=', 'time_entries.activity_id')
                ->where('activities.project_id', $project->id)
                ->sum('time_entries.duration_minutes');

            if ($used <= (float) $project->planned_hours * 60) {
                continue;
            }

            $message = sprintf('Sforamento ore: «%s» %s / %s h.', $project->name, $this->hours($used / 60), $this->hours((float) $project->planned_hours));

            foreach ($project->members->merge(User::where('role', Role::Boss)->get())->unique('id') as $user) {
                $sent += $this->once($user, "overrun:{$project->id}", NotificationKind::HoursOverrun, $message, route('projects.show', $project));
            }
        }

        return $sent;
    }

    /**
     * Manda il promemoria solo se questa persona non l'ha già ricevuto con questa chiave.
     */
    private function once(User $user, string $key, NotificationKind $kind, string $message, string $url): int
    {
        $inserted = DB::table('sent_reminders')->insertOrIgnore(['user_id' => $user->id, 'key' => $key, 'sent_at' => now()]);

        if ($inserted === 0) {
            return 0;
        }

        $this->notify->handle([$user], $kind, $message, $url);

        return 1;
    }

    private function hours(float $hours): string
    {
        return rtrim(rtrim(number_format($hours, 2, ',', ''), '0'), ',');
    }
}
