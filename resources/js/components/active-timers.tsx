import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { formatElapsed } from '@/lib/elapsed';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { AlertTriangle, Square, Timer } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

/**
 * Topbar badge with the number of active timers; opens the panel with one timer per row
 * (activity, project, elapsed time, Stop). After the hour limit a timer is flagged as forgotten:
 * a warning only, no automatic stop.
 */
export function ActiveTimers() {
    const { timers, timer_warning_minutes, server_now } = usePage<SharedData>().props;
    const [now, setNow] = useState(() => Date.now());

    // Difference between the server clock and the browser's, for a correct elapsed time.
    const skew = useMemo(() => Date.parse(server_now) - Date.now(), [server_now]);

    useEffect(() => {
        if (timers.length === 0) {
            return;
        }

        setNow(Date.now());
        const interval = window.setInterval(() => setNow(Date.now()), 1000);

        return () => window.clearInterval(interval);
    }, [timers.length]);

    const elapsedOf = (startedAt: string) => now + skew - Date.parse(startedAt);
    const isForgotten = (startedAt: string) => elapsedOf(startedAt) >= timer_warning_minutes * 60_000;
    const anyForgotten = timers.some((timer) => isForgotten(timer.started_at));

    const stop = (id: number) => router.post(route('timers.stop', id), {}, { preserveScroll: true });

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="sm" className="relative gap-1" aria-label={`Timer attivi: ${timers.length}`}>
                    <Timer className="size-4" />
                    {timers.length > 0 && (
                        <span className="bg-primary text-primary-foreground inline-flex min-w-5 items-center justify-center rounded-full px-1.5 text-xs">
                            {timers.length}
                        </span>
                    )}
                    {anyForgotten && <AlertTriangle className="text-destructive size-4" aria-label="Timer attivo da molte ore" />}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-96 max-w-[calc(100vw-2rem)] p-2">
                <DropdownMenuLabel>Timer attivi</DropdownMenuLabel>
                <DropdownMenuSeparator />
                {timers.length === 0 ? (
                    <p className="text-muted-foreground px-2 py-3 text-sm">Nessun timer attivo. Avviane uno dalla pagina Ore.</p>
                ) : (
                    <ul className="grid gap-2">
                        {timers.map((timer) => (
                            <li key={timer.id} className="border-sidebar-border/70 flex items-center justify-between gap-3 rounded-md border p-2">
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium">{timer.activity_name}</p>
                                    <p className="text-muted-foreground truncate text-xs">
                                        {timer.project_name}
                                        {timer.description && ` · ${timer.description}`}
                                    </p>
                                    {isForgotten(timer.started_at) && (
                                        <p className="text-destructive mt-1 flex items-center gap-1 text-xs">
                                            <AlertTriangle className="size-3" /> Attivo da molte ore: l'hai dimenticato?
                                        </p>
                                    )}
                                </div>
                                <div className="flex shrink-0 items-center gap-2">
                                    <span className="font-mono text-sm tabular-nums">{formatElapsed(elapsedOf(timer.started_at))}</span>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => stop(timer.id)}
                                        aria-label={`Ferma il timer di ${timer.activity_name}`}
                                    >
                                        <Square /> Stop
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
