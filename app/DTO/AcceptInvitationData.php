<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\AppLocale;
use App\Enum\TimeFormat;

/**
 * Dati del primo accesso da invito: password e preferenze.
 */
final readonly class AcceptInvitationData
{
    public function __construct(
        public string $password,
        public AppLocale $locale,
        public TimeFormat $timeFormat,
    ) {}
}
