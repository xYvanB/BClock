<?php

declare(strict_types=1);

namespace App\Actions\Holidays;

use App\Models\Holiday;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DeleteHoliday
{
    /**
     * @throws Throwable
     */
    public function handle(Holiday $holiday): void
    {
        DB::transaction(fn () => $holiday->delete());
    }
}
