<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\DTO\WorkloadSettingsData;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Throwable;

final class UpdateWorkloadSettings
{
    /**
     * Salva le impostazioni del carico in transazione.
     *
     * @throws Throwable
     */
    public function handle(WorkloadSettingsData $data): void
    {
        DB::transaction(function () use ($data): void {
            Setting::write(Setting::LOAD_TOLERANCE_ENABLED, $data->toleranceEnabled ? '1' : '0');
            Setting::write(Setting::LOAD_TOLERANCE_HOURS, (string) $data->toleranceHours);
        });
    }
}
