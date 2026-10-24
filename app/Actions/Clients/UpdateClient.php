<?php

declare(strict_types=1);

namespace App\Actions\Clients;

use App\DTO\ClientContactData;
use App\DTO\ClientData;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Throwable;

final class UpdateClient
{
    /**
     * Updates the customer and syncs the contacts by id: projects reference them,
     * so they are not recreated; those no longer present are deleted.
     *
     * @throws Throwable
     */
    public function handle(Client $client, ClientData $data): Client
    {
        return DB::transaction(function () use ($client, $data): Client {
            $client->update(['company_name' => $data->companyName]);

            $contacts = $data->contactsWithPrimary();
            $keptIds = array_values(array_filter(array_map(fn (ClientContactData $contact): ?int => $contact->id, $contacts)));

            $client->contacts()->whereNotIn('id', $keptIds)->delete();

            foreach ($contacts as $contact) {
                $attributes = [
                    'name' => $contact->name,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'is_primary' => $contact->isPrimary,
                ];

                if ($contact->id !== null) {
                    $client->contacts()->whereKey($contact->id)->update($attributes);
                } else {
                    $client->contacts()->create($attributes);
                }
            }

            return $client;
        });
    }
}
