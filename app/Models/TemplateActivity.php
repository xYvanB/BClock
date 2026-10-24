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
     * Data del giorno lavorativo N: il giorno 1 è la data di inizio del progetto (se cade
     * di sabato o domenica, il primo lunedì successivo); si contano solo lunedì–venerdì.
     * La stessa formula è replicata in `resources/js/lib/template-days.ts`.
     */
    public static function dateOfWorkingDay(CarbonImmutable $projectStart, int $day): CarbonImmutable
    {
        $date = $projectStart;

        while ($date->isWeekend()) {
            $date = $date->addDay();
        }

        for ($remaining = $day - 1; $remaining > 0; $remaining--) {
            $date = $date->addDay();

            while ($date->isWeekend()) {
                $date = $date->addDay();
            }
        }

        return $date;
    }
}
