<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\Role;
use Carbon\CarbonImmutable;
use Database\Factories\TemplateActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateActivity extends Model
{
    /** @use HasFactory<TemplateActivityFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_template_id',
        'name',
        'description',
        'planned_hours',
        'start_day',
        'end_day',
        'position',
        'suggested_area_id',
        'suggested_role',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'planned_hours' => 'decimal:2',
            'start_day' => 'integer',
            'end_day' => 'integer',
            'position' => 'integer',
            'suggested_role' => Role::class,
        ];
    }

    /**
     * @return BelongsTo<ProjectTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ProjectTemplate::class, 'project_template_id');
    }

    /**
     * @return BelongsTo<Area, $this>
     */
    public function suggestedArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'suggested_area_id');
    }

    /**
     * Date of working day N: day 1 is the project start date (if it is not a working day,
     * the first one after it). Only working weekdays (`$weekdays`, 1 = Monday … 7 = Sunday,
     * normally Monday–Friday) that are not holidays or closures (`$closedDates`, `Y-m-d` dates) are counted.
     * The same formula is replicated in `resources/js/lib/template-days.ts`.
     *
     * @param  list<int>  $weekdays
     * @param  list<string>  $closedDates
     */
    public static function dateOfWorkingDay(CarbonImmutable $projectStart, int $day, array $weekdays = [1, 2, 3, 4, 5], array $closedDates = []): CarbonImmutable
    {
        $isOff = fn (CarbonImmutable $date): bool => ! in_array($date->dayOfWeekIso, $weekdays, true) || in_array($date->toDateString(), $closedDates, true);

        $date = $projectStart;

        while ($isOff($date)) {
            $date = $date->addDay();
        }

        for ($remaining = $day - 1; $remaining > 0; $remaining--) {
            $date = $date->addDay();

            while ($isOff($date)) {
                $date = $date->addDay();
            }
        }

        return $date;
    }
}
