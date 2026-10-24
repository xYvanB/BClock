<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\Role;

/**
 * Validated data to invite or edit a user.
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
