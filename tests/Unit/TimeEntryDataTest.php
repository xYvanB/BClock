<?php

declare(strict_types=1);

use App\DTO\TimeEntryData;
use Carbon\CarbonImmutable;

it('computes the duration in minutes', function (string $start, string $end, int $expected): void {
    $data = new TimeEntryData(1, CarbonImmutable::parse('2026-10-23'), $start, $end, null);

    expect($data->durationMinutes())->toBe($expected);
})->with([
    'morning' => ['09:00', '12:30', 210],
    'one minute' => ['13:59', '14:00', 1],
    'whole day' => ['00:00', '23:59', 1439],
]);

it('does not depend on the current clock', function (): void {
    CarbonImmutable::setTestNow('2026-10-23 10:00:59');

    $data = new TimeEntryData(1, CarbonImmutable::parse('2026-10-23'), '09:00', '12:30', null);

    expect($data->durationMinutes())->toBe(210);

    CarbonImmutable::setTestNow();
});
