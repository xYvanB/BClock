<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

class PruneNotificationsCommand extends Command
{
    /** Days after which a notification is deleted. */
    public const KEEP_DAYS = 90;

    protected $signature = 'notifications:prune';

    protected $description = 'Delete the notifications older than 90 days';

    public function handle(): int
    {
        $deleted = DatabaseNotification::where('created_at', '<', now()->subDays(self::KEEP_DAYS))->delete();

        // sent reminders only matter while the event is current
        DB::table('sent_reminders')->where('sent_at', '<', now()->subDays(self::KEEP_DAYS * 2))->delete();

        $this->info("Notifications deleted: {$deleted}.");

        return self::SUCCESS;
    }
}
