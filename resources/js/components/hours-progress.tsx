import { formatMinutes } from '@/lib/time';
import { AlertTriangle } from 'lucide-react';

interface HoursProgressProps {
    usedMinutes: number;
    plannedHours: number;
}

/** Barra di avanzamento ore usate / previste, con ⚠️ se si supera il previsto (solo avviso). */
export function HoursProgress({ usedMinutes, plannedHours }: HoursProgressProps) {
    const plannedMinutes = plannedHours * 60;
    const overBudget = plannedMinutes > 0 ? usedMinutes > plannedMinutes : usedMinutes > 0;
    const percent = plannedMinutes > 0 ? Math.min(100, Math.round((usedMinutes / plannedMinutes) * 100)) : 0;

    return (
        <div className="grid gap-1" style={{ minWidth: '9rem' }}>
            <span className="inline-flex items-center gap-1 whitespace-nowrap">
                {overBudget && <AlertTriangle className="size-4" aria-label="Ore previste superate" />}
                {formatMinutes(usedMinutes)} / {formatMinutes(plannedMinutes)}
            </span>
            <div
                className="bg-muted h-1.5 overflow-hidden rounded-full"
                role="progressbar"
                aria-valuemin={0}
                aria-valuemax={100}
                aria-valuenow={percent}
            >
                <div className={overBudget ? 'bg-destructive h-full' : 'bg-primary h-full'} style={{ width: `${percent}%` }} />
            </div>
        </div>
    );
}
