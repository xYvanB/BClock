<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Referente di un cliente; `id` è valorizzato solo per i referenti già salvati.
 */
final readonly class ClientContactData
{
    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $email,
        public ?string $phone,
        public bool $isPrimary,
    ) {}
}
