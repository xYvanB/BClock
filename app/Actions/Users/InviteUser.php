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
     * Crea l'utente in attesa di invito (con una password casuale che nessuno conosce)
     * e, a transazione conclusa, gli manda l'email con il link per scegliere la password.
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
