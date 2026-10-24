<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Users\InviteUser;
use App\Actions\Users\UpdateUser;
use App\Enum\Role;
use App\Http\Requests\SaveUserRequest;
use App\Models\Area;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class UserController extends Controller
{
    /**
     * Lista utenti: il Capo e l'HR vedono tutti, il Capo area solo la propria area.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();

        return Inertia::render('users/index', [
            'users' => User::query()
                ->with('area:id,name')
                ->when($actor->hasRole(Role::AreaHead), fn ($query) => $query->where('area_id', $actor->area_id))
                ->orderBy('surname')
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'surname' => $user->surname,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'area_id' => $user->area_id,
                    'area_name' => $user->area?->name,
                    'daily_capacity' => (float) $user->daily_capacity,
                    'status' => $user->invited_at === null ? 'attivo' : 'invito_in_attesa',
                    'can_edit' => $actor->can('update', $user),
                    'is_self' => $user->is($actor),
                ]),
            'areas' => Area::query()->orderBy('name')->get(['id', 'name']),
            'assignable_roles' => array_map(fn (Role $role) => $role->value, $actor->role->assignableRoles()),
            'fixed_area_id' => $actor->hasRole(Role::AreaHead) ? $actor->area_id : null,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function store(SaveUserRequest $request, InviteUser $action): RedirectResponse
    {
        $action->handle($request->toDto());

        return to_route('users.index')->with('success', 'Invito inviato.');
    }

    /**
     * @throws Throwable
     */
    public function update(SaveUserRequest $request, User $user, UpdateUser $action): RedirectResponse
    {
        $action->handle($user, $request->toDto());

        return to_route('users.index')->with('success', 'Utente aggiornato.');
    }
}
