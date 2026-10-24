<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Tipi di notifica che la persona può ricevere nell'app e per e-mail.
 */
enum NotificationKind: string
{
    case TaskAssigned = 'task_assegnato';
    case ActivityMoved = 'attivita_spostata';
    case AbsenceDecision = 'assenza_esito';
    case AbsenceRequested = 'assenza_richiesta';
    case QuotaMoved = 'quota_spostata';
    case DueSoon = 'scadenza_vicina';
    case TimerForgotten = 'timer_dimenticato';
    case HoursOverrun = 'sforamento_ore';

    public function label(): string
    {
        return match ($this) {
            self::TaskAssigned => 'Task assegnato a me',
            self::ActivityMoved => 'Date di un\'attività mia spostate',
            self::AbsenceDecision => 'Permesso approvato, rifiutato o eliminato',
            self::AbsenceRequested => 'Nuova richiesta da approvare (o modificata)',
            self::QuotaMoved => 'Una mia quota è stata spostata da altri',
            self::DueSoon => 'Scadenza vicina',
            self::TimerForgotten => 'Timer attivo da oltre 8 ore',
            self::HoursOverrun => 'Sforamento ore del progetto',
        };
    }

    /**
     * Le richieste da approvare non si possono spegnere nell'app: chi deve approvare deve saperlo.
     */
    public function isMandatoryInApp(): bool
    {
        return $this === self::AbsenceRequested;
    }

    public function defaultInApp(): bool
    {
        return true;
    }

    public function defaultMail(): bool
    {
        return match ($this) {
            self::AbsenceDecision, self::AbsenceRequested => true,
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
