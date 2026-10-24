<?php

declare(strict_types=1);

namespace App\DTO;

use Carbon\CarbonImmutable;

/**
 * Dati validati per creare o modificare una registrazione ore.
 * Gli orari sono nel formato H:i, la durata viene calcolata dall'Action.
 */
final readonly class TimeEntryData
{
    public function __construct(
        public int $activityId,
        public CarbonImmutable $date,
        public string $startedAt,
        public string $endedAt,
        public ?string $description,
    ) {}

    /**
     * Minuti tra inizio e fine, nello stesso giorno. Calcolo diretto su ore e minuti,
     * senza passare dall'orologio corrente (che riempirebbe secondi e data).
     */
    public function durationMinutes(): int
    {
        return $this->minutesOfDay($this->endedAt) - $this->minutesOfDay($this->startedAt);
    }

    private function minutesOfDay(string $time): int
    {
        [$hours, $minutes] = array_map(intval(...), explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
