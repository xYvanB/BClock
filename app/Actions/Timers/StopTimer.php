<?php

declare(strict_types=1);

namespace App\Actions\Timers;

use App\Actions\TimeEntries\CreateTimeEntry;
use App\DTO\TimeEntryData;
use App\Enum\TimeEntrySource;
use App\Models\Timer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

final class StopTimer
{
    public function __construct(private readonly CreateTimeEntry $createTimeEntry) {}

    /**
     * Ferma il timer e lo trasforma in registrazioni ore modificabili (sorgente "timer"), in transazione.
     *
     * - Gli orari sono nel fuso locale (`app.local_timezone`), come le ore manuali.
     * - Una registrazione sta in un solo giorno: se il timer attraversa la mezzanotte viene diviso per giorno
     *   (il primo giorno finisce alle 23:59, i successivi iniziano alle 00:00).
     * - Sotto il minuto (a inizio e fine troncati al minuto) non si crea nulla.
     * - Il timer si elimina per primo: con due richieste insieme (doppio click) solo una lo trova.
     * - Si registra anche se l'attività è stata archiviata nel frattempo: il lavoro è stato fatto.
     *
     * @return int|null numero di registrazioni create, o null se il timer era già stato fermato
     *
     * @throws Throwable
     */
    public function handle(Timer $timer): ?int
    {
        return DB::transaction(function () use ($timer): ?int {
            if (Timer::whereKey($timer->id)->delete() !== 1) {
                return null;
            }

            $timezone = (string) config('app.local_timezone');
            $cursor = CarbonImmutable::instance($timer->started_at)->setTimezone($timezone)->startOfMinute();
            $end = CarbonImmutable::now($timezone)->startOfMinute();
            $created = 0;

            while ($cursor < $end) {
                $dayEnd = $cursor->endOfDay()->startOfMinute();
                $segmentEnd = $end <= $dayEnd ? $end : $dayEnd;

                if ($segmentEnd > $cursor) {
                    $this->createTimeEntry->handle($timer->user, new TimeEntryData(
                        activityId: $timer->activity_id,
                        date: $cursor->startOfDay(),
                        startedAt: $cursor->format('H:i'),
                        endedAt: $segmentEnd->format('H:i'),
                        description: $timer->description,
                    ), TimeEntrySource::Timer);

                    $created++;
                }

                $cursor = $cursor->addDay()->startOfDay();
            }

            return $created;
        });
    }
}
