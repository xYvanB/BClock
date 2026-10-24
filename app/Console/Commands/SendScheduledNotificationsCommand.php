<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Notifications\SendScheduledNotifications;
use Illuminate\Console\Command;
use Throwable;

class SendScheduledNotificationsCommand extends Command
{
    protected $signature = 'notifications:remind';

    protected $description = 'Manda i promemoria programmati: scadenze vicine, timer dimenticati, ore di progetto sforate';

    /**
     * @throws Throwable
     */
    public function handle(SendScheduledNotifications $action): int
    {
        $this->info('Promemoria mandati: '.$action->handle().'.');

        return self::SUCCESS;
    }
}
