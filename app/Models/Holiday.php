<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\HolidayKind;
use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'date',
        'name',
        'kind',
    ];

    /**
     * Tutte le date non lavorative per festività o chiusura (`Y-m-d`).
     *
     * @return list<string>
     */
    public static function closedDates(): array
    {
        return static::query()->orderBy('date')->get()->map(fn (self $holiday): string => $holiday->date->toDateString())->all();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'kind' => HolidayKind::class,
        ];
    }
}
