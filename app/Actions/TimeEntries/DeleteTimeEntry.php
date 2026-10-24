<?php

declare(strict_types=1);

namespace App\Actions\TimeEntries;

use App\Models\TimeEntry;
use Illuminate\Support\Facades\DB;
use Throwable;

final class DeleteTimeEntry
{
    /**
     * Deletes a time entry in a transaction.
     *
     * @throws Throwable
     */
    public function handle(TimeEntry $entry): void
    {
        DB::transaction(function () use ($entry): void {
            $entry->delete();
        });
    }
}
