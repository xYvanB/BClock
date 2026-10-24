<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\DTO\UserData;
use App\Models\User;
use App\Notifications\UserInvited;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class InviteUser
{
    /**
     * Creates the user awaiting an invitation (with a random password nobody knows)
     * and, once the transaction is done, sends them the e-mail with the link to choose the password.
     *
     * @throws Throwable
     */
    public function handle(UserData $data): User
    {
        $user = DB::transaction(fn (): User => User::create([
            'name' => $data->name,
            'surname' => $data->surname,
            'email' => $data->email,
            'role' => $data->role,
            'area_id' => $data->areaId,
            'daily_capacity' => $data->dailyCapacity,
            'password' => Str::random(40),
            'invited_at' => now(),
        ]));

        $user->notify(new UserInvited);

        return $user;
    }
}
