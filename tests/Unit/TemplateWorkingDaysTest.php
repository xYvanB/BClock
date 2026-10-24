<?php

declare(strict_types=1);

use App\Models\TemplateActivity;
use Carbon\CarbonImmutable;

it('counts working days from the project start, day 1 being the start date', function (string $start, int $day, string $expected): void {
    expect(TemplateActivity::dateOfWorkingDay(CarbonImmutable::parse($start), $day)->toDateString())->toBe($expected);
})->with([
    'day 1 is the start (Monday)' => ['2026-11-02', 1, '2026-11-02'],
    'day 5 is the Friday' => ['2026-11-02', 5, '2026-11-06'],
    'day 6 skips the weekend' => ['2026-11-02', 6, '2026-11-09'],
    'start on a Friday: day 2 is Monday' => ['2026-11-06', 2, '2026-11-09'],
    'start on a Friday: day 4' => ['2026-11-06', 4, '2026-11-11'],
    'start on a Saturday: day 1 is Monday' => ['2026-11-07', 1, '2026-11-09'],
    'start on a Sunday: day 3' => ['2026-11-08', 3, '2026-11-11'],
    'two full weeks' => ['2026-11-02', 11, '2026-11-16'],
]);

it('follows the working weekdays and the closed dates of the company', function (string $start, int $day, array $weekdays, array $closed, string $expected): void {
    expect(TemplateActivity::dateOfWorkingDay(CarbonImmutable::parse($start), $day, $weekdays, $closed)->toDateString())->toBe($expected);
})->with([
    'saturday is a working day' => ['2026-11-02', 6, [1, 2, 3, 4, 5, 6], [], '2026-11-07'],
    'only four days a week' => ['2026-11-02', 5, [1, 2, 3, 4], [], '2026-11-09'],
    'a closed day is skipped' => ['2026-11-02', 3, [1, 2, 3, 4, 5], ['2026-11-04'], '2026-11-05'],
    'the start on a closed day moves to the next working day' => ['2026-11-02', 1, [1, 2, 3, 4, 5], ['2026-11-02', '2026-11-03'], '2026-11-04'],
    'a closure across the weekend' => ['2026-11-05', 3, [1, 2, 3, 4, 5], ['2026-11-06', '2026-11-09'], '2026-11-11'],
]);
