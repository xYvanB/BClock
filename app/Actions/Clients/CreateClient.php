<?php

declare(strict_types=1);

namespace App\Actions\Clients;

use App\DTO\ClientData;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Throwable;

final class CreateClient
{
    /**
     * Crea un cliente con i suoi referenti in transazione.
     *
     * @throws Throwable
     */
    public function handle(ClientData $data): Client
    {
        return DB::transaction(function () use ($data): Client {
            $client = Client::create(['company_name' => $data->companyName]);

            foreach ($data->contactsWithPrimary() as $contact) {
                $client->contacts()->create([
                    'name' => $contact->name,
                    'email' => $contact->email,
                    'phone' => $contact->phone,
                    'is_primary' => $contact->isPrimary,
                ]);
            }

            return $client;
        });
    }
}
