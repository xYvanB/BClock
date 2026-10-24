<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\Role;

/**
 * Dati validati per invitare o modificare un utente.
 */
final readonly class UserData
{
    public function __construct(
        public string $name,
        public string $surname,
        public string $email,
        public Role $role,
        public ?int $areaId,
        public float $dailyCapacity,
    ) {}
}
