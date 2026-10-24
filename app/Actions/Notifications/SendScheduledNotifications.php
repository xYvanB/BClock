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
    /** A due date is "near" when it falls today or within the given days. */
    public const int DUE_SOON_DAYS = 1;

    public function __construct(private readonly NotifyUsers $notify) {}

    /**
     * Sends the time-based reminders: near due date, forgotten timer, a project exceeding its hours.
     * Each reminder reaches each person only once (`sent_reminders` table), even if the command runs every hour.
     *
     * @return int How many reminders were sent
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
                $sent += $this->once($user, "due:{$activity->id}:{$activity->due_on->toDateString()}", NotificationKind::DueSoon, 'Scadenza vicina: «:name» (:date).', ['name' => $activity->name, 'date' => $activity->due_on->format('d/m/Y')], route('calendar.index'));
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
            $sent += $this->once($timer->user, "timer:{$timer->id}", NotificationKind::TimerForgotten, 'Timer attivo da oltre 8 ore: «:name». Ricordati di fermarlo.', ['name' => $timer->activity->name], route('time-entries.index'));
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

            $params = ['name' => $project->name, 'used' => $this->hours($used / 60), 'planned' => $this->hours((float) $project->planned_hours)];

            foreach ($project->members->merge(User::where('role', Role::Boss)->get())->unique('id') as $user) {
                $sent += $this->once($user, "overrun:{$project->id}", NotificationKind::HoursOverrun, 'Sforamento ore: «:name» :used / :planned h.', $params, route('projects.show', $project));
            }
        }

        return $sent;
    }

    /**
     * @param  array<string, string|int|float>  $params
     *
     * Sends the reminder only if this person has not already received it with this key.
     */
    private function once(User $user, string $key, NotificationKind $kind, string $messageKey, array $params, string $url): int
    {
        $inserted = DB::table('sent_reminders')->insertOrIgnore(['user_id' => $user->id, 'key' => $key, 'sent_at' => now()]);

        if ($inserted === 0) {
            return 0;
        }

        $this->notify->handle([$user], $kind, $messageKey, $params, $url);

        return 1;
    }

    private function hours(float $hours): string
    {
        return rtrim(rtrim(number_format($hours, 2, ',', ''), '0'), ',');
    }
}
