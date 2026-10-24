<?php

declare(strict_types=1);

namespace App\Actions\Areas;

use App\DTO\AreaData;
use App\Models\Area;
use Illuminate\Support\Facades\DB;
use Throwable;

final class CreateArea
{
    /**
     * Creates an area in a transaction.
     *
     * @throws Throwable
     */
    public function handle(AreaData $data): Area
    {
        return DB::transaction(fn (): Area => Area::create([
            'name' => $data->name,
            'description' => $data->description,
        ]));
    }
}
