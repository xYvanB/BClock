<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * A customer contact; `id` is set only for contacts already saved.
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
