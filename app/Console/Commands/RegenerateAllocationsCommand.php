<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Allocations\RegenerateAllocations;
use Illuminate\Console\Command;

class RegenerateAllocationsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'allocations:regenerate';

    /**
     * @var string
     */
    protected $description = 'Ricalcola le quote giornaliere di tutte le attività aperte (festivi e assenze confermate compresi)';

    public function handle(RegenerateAllocations $action): int
    {
        $count = $action->handle();

        $this->info("Quote ricalcolate per {$count} attività.");

        return self::SUCCESS;
    }
}
