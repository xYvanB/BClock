<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Dati validati per creare o modificare un cliente con i suoi referenti.
 */
final readonly class ClientData
{
    /**
     * @param  list<ClientContactData>  $contacts
     */
    public function __construct(
        public string $companyName,
        public array $contacts,
    ) {}

    /**
     * Referenti con esattamente un principale: se nessuno lo è, lo diventa il primo.
     *
     * @return list<ClientContactData>
     */
    public function contactsWithPrimary(): array
    {
        $hasPrimary = collect($this->contacts)->contains(fn (ClientContactData $contact): bool => $contact->isPrimary);

        return array_values(array_map(
            fn (ClientContactData $contact, int $position): ClientContactData => new ClientContactData(
                id: $contact->id,
                name: $contact->name,
                email: $contact->email,
                phone: $contact->phone,
                isPrimary: $hasPrimary ? $contact->isPrimary : $position === 0,
            ),
            $this->contacts,
            array_keys($this->contacts),
        ));
    }
}
