<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\AppLocale;
use App\Enum\TimeFormat;

/**
 * First-access data from an invitation: password and preferences.
 */
final readonly class AcceptInvitationData
{
    public function __construct(
        public string $password,
        public AppLocale $locale,
        public TimeFormat $timeFormat,
    ) {}
}
