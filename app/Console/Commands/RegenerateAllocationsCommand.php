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
    protected $description = 'Recalculate the daily allocations of all open activities (holidays and confirmed absences included)';

    public function handle(RegenerateAllocations $action): int
    {
        $count = $action->handle();

        $this->info("Allocations recalculated for {$count} activities.");

        return self::SUCCESS;
    }
}
