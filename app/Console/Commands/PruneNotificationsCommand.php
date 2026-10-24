<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class PruneNotificationsCommand extends Command
{
    /** Giorni dopo i quali una notifica si cancella. */
    public const KEEP_DAYS = 90;

    protected $signature = 'notifications:prune';

    protected $description = 'Cancella le notifiche più vecchie di 90 giorni';

    public function handle(): int
    {
        $deleted = DatabaseNotification::where('created_at', '<', now()->subDays(self::KEEP_DAYS))->delete();

        $this->info("Notifiche cancellate: {$deleted}.");

        return self::SUCCESS;
    }
}
