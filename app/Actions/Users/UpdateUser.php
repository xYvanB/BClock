<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\DTO\UserData;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

final class UpdateUser
{
    /**
     * Updates a user's data in a transaction.
     *
     * @throws Throwable
     */
    public function handle(User $user, UserData $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $user->update([
                'name' => $data->name,
                'surname' => $data->surname,
                'email' => $data->email,
                'role' => $data->role,
                'area_id' => $data->areaId,
                'daily_capacity' => $data->dailyCapacity,
            ]);

            return $user;
        });
    }
}
