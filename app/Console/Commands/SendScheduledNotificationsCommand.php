<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Notifications\SendScheduledNotifications;
use Illuminate\Console\Command;
use Throwable;

class SendScheduledNotificationsCommand extends Command
{
    protected $signature = 'notifications:remind';

    protected $description = 'Send the scheduled reminders: near due dates, forgotten timers, projects over their hours';

    /**
     * @throws Throwable
     */
    public function handle(SendScheduledNotifications $action): int
    {
        $this->info('Reminders sent: '.$action->handle().'.');

        return self::SUCCESS;
    }
}
