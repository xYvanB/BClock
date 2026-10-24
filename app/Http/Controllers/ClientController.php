<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Clients\CreateClient;
use App\Actions\Clients\UpdateClient;
use App\Enum\WorkStatus;
use App\Http\Requests\SaveClientRequest;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ClientController extends Controller
{
    /**
     * A project is "active" if it is neither completed nor archived.
     */
    private const array INACTIVE_STATUSES = [WorkStatus::Done, WorkStatus::Archived];

    public function index(): Response
    {
        $this->authorize('viewAny', Client::class);

        return Inertia::render('clients/index', [
            'clients' => Client::query()
                ->with(['contacts' => fn ($query) => $query->withCount('projects')->orderByDesc('is_primary')->orderBy('name')])
                ->withCount(['projects as active_projects_count' => fn ($query) => $query->whereNotIn('status', self::INACTIVE_STATUSES)])
                ->orderBy('company_name')
                ->get()
                ->map(fn (Client $client) => [
                    'id' => $client->id,
                    'company_name' => $client->company_name,
                    'active_projects_count' => $client->active_projects_count,
                    'primary_contact' => $client->contacts->firstWhere('is_primary', true)?->name,
                    'contacts' => $client->contacts->map(fn (ClientContact $contact) => $this->contactPayload($contact))->values(),
                ]),
        ]);
    }

    public function show(Client $client): Response
    {
        $this->authorize('view', $client);

        return Inertia::render('clients/show', [
            'client' => [
                'id' => $client->id,
                'company_name' => $client->company_name,
                'contacts' => $client->contacts()->withCount('projects')->orderByDesc('is_primary')->orderBy('name')->get()
                    ->map(fn (ClientContact $contact) => $this->contactPayload($contact))->values(),
                'projects' => $client->projects()->orderBy('name')->get()
                    ->map(fn (Project $project) => [
                        'id' => $project->id,
                        'name' => $project->name,
                        'status' => $project->status->value,
                        'due_on' => $project->due_on?->toDateString(),
                    ])->values(),
            ],
        ]);
    }

    /**
     * @throws Throwable
     */
    public function store(SaveClientRequest $request, CreateClient $action): RedirectResponse
    {
        $action->handle($request->toDto());

        return to_route('clients.index')->with('success', 'Cliente creato.');
    }

    /**
     * @throws Throwable
     */
    public function update(SaveClientRequest $request, Client $client, UpdateClient $action): RedirectResponse
    {
        $action->handle($client, $request->toDto());

        return back()->with('success', 'Cliente aggiornato.');
    }

    /**
     * @return array{id: int, name: string, email: ?string, phone: ?string, is_primary: bool, projects_count: int}
     */
    private function contactPayload(ClientContact $contact): array
    {
        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'is_primary' => $contact->is_primary,
            'projects_count' => $contact->projects_count,
        ];
    }
}
