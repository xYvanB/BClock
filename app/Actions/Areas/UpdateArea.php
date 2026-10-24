<?php

declare(strict_types=1);

namespace App\Actions\Areas;

use App\DTO\AreaData;
use App\Models\Area;
use Illuminate\Support\Facades\DB;
use Throwable;

final class UpdateArea
{
    /**
     * Aggiorna un'area in transazione.
     *
     * @throws Throwable
     */
    public function handle(Area $area, AreaData $data): Area
    {
        return DB::transaction(function () use ($area, $data): Area {
            $area->update([
                'name' => $data->name,
                'description' => $data->description,
            ]);

            return $area;
        });
    }
}
