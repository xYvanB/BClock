<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\AppLocale;
use App\Enum\Palette;
use App\Enum\Role;
use App\Enum\TimeFormat;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Valori predefiniti allineati alle colonne del database, disponibili anche prima del refresh.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'dipendente',
        'daily_capacity' => '8.00',
        'locale' => 'it',
        'time_format' => '24h',
        'palette' => 'scuro',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'surname',
        'email',
        'password',
        'role',
        'area_id',
        'daily_capacity',
        'locale',
        'time_format',
        'palette',
        'invited_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'locale' => AppLocale::class,
            'time_format' => TimeFormat::class,
            'palette' => Palette::class,
            'daily_capacity' => 'decimal:2',
            'invited_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * @return HasMany<TimeEntry, $this>
     */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function fullName(): string
    {
        return trim("{$this->name} {$this->surname}");
    }
}
