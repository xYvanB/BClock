import { AbsenceFormSheet } from '@/components/absence-form-sheet';
import { FlashMessage } from '@/components/flash-message';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { absenceTypeLabel } from '@/lib/schemas/absence';
import { holidayKindLabels } from '@/lib/schemas/holiday';
import { type Absence, type AppNotification, type BreadcrumbItem, type Holiday, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Check, Pencil, Plus, Settings, Trash2, X } from 'lucide-react';
import { useState } from 'react';

interface AbsencesIndexProps {
    absences: Absence[];
    holidays: Holiday[];
    can_manage_holidays: boolean;
    notifications: AppNotification[];
}

type Tab = 'absences' | 'holidays';

/** Azione dell'HR che richiede un motivo: rifiutare una richiesta o eliminare un'assenza già confermata. */
interface ReasonAction {
    kind: 'reject' | 'delete';
    absence: Absence;
}

const formatDate = (iso: string) => iso.split('-').reverse().join('/');

const formatPeriod = (absence: Absence) => {
    if (absence.type === 'permesso') {
        return `${formatDate(absence.starts_on)} ${absence.starts_time}–${absence.ends_time} (${absence.hours} h)`;
    }

    return absence.starts_on === absence.ends_on
        ? formatDate(absence.starts_on)
        : `${formatDate(absence.starts_on)} – ${formatDate(absence.ends_on)}`;
};

/** Chi ha già dato l'ok a una richiesta ancora in attesa (Capo area solo per i dipendenti). */
const approvalProgress = (absence: Absence): string => {
    const steps = [`${absence.approver_label}: ${absence.hr_approved ? 'ok' : 'da fare'}`];

    if (absence.needs_area_head) {
        steps.unshift(`Capo area: ${absence.area_head_approved ? 'ok' : 'da fare'}`);
    }

    return steps.join(' · ');
};

const statusLabel = { in_attesa: 'In attesa', confermata: 'Confermata', rifiutata: 'Rifiutata' } as const;

const statusVariant = { in_attesa: 'secondary', confermata: 'default', rifiutata: 'destructive' } as const;

/** Assenze (ognuno le proprie; il Capo area anche quelle della sua area, il Capo quelle dell'HR, l'HR tutte) e giorni non lavorativi. */
export default function AbsencesIndex({ absences, holidays, can_manage_holidays, notifications }: AbsencesIndexProps) {
    const { auth } = usePage<SharedData>().props;
    const showPerson = auth.user.role !== 'dipendente';
    const title = 'Assenze';
    const breadcrumbs: BreadcrumbItem[] = [{ title, href: '/absences' }];

    const [tab, setTab] = useState<Tab>('absences');
    const [absenceSheetOpen, setAbsenceSheetOpen] = useState(false);
    const [editingAbsence, setEditingAbsence] = useState<Absence | null>(null);
    const [confirmingDelete, setConfirmingDelete] = useState<string | null>(null);
    const [reasonAction, setReasonAction] = useState<ReasonAction | null>(null);
    const [reason, setReason] = useState('');
    const [reasonError, setReasonError] = useState<string | undefined>();

    const openAbsence = (absence: Absence | null) => {
        setEditingAbsence(absence);
        setAbsenceSheetOpen(true);
    };

    const startReasonAction = (kind: ReasonAction['kind'], absence: Absence) => {
        setReasonAction({ kind, absence });
        setReason('');
        setReasonError(undefined);
    };

    const closeReasonAction = () => setReasonAction(null);

    const submitReason = () => {
        if (!reasonAction) {
            return;
        }

        if (reason.trim() === '') {
            setReasonError('Il motivo è obbligatorio.');

            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: closeReasonAction,
            onError: (errors: Record<string, string>) => setReasonError(errors.reason),
        };

        if (reasonAction.kind === 'reject') {
            router.post(route('absences.reject', reasonAction.absence.id), { reason }, options);
        } else {
            router.delete(route('absences.destroy', reasonAction.absence.id), { ...options, data: { reason } });
        }
    };

    const confirmAbsence = (absence: Absence) => router.post(route('absences.confirm', absence.id), {}, { preserveScroll: true });

    const approveAbsence = (absence: Absence) => router.post(route('absences.approve', absence.id), {}, { preserveScroll: true });

    const markNotificationsRead = () => router.post(route('absences.notifications.read'), {}, { preserveScroll: true });

    const remove = (key: string, url: string) =>
        router.delete(url, { preserveScroll: true, onFinish: () => setConfirmingDelete((current) => (current === key ? null : current)) });

    const deleteControls = (key: string, url: string, label: string) =>
        confirmingDelete === key ? (
            <span className="inline-flex items-center gap-1">
                <span className="text-sm">Eliminare?</span>
                <Button variant="destructive" size="sm" onClick={() => remove(key, url)}>
                    Elimina
                </Button>
                <Button variant="outline" size="sm" onClick={() => setConfirmingDelete(null)}>
                    Annulla
                </Button>
            </span>
        ) : (
            <Button variant="ghost" size="sm" onClick={() => setConfirmingDelete(key)} aria-label={`Elimina ${label}`}>
                <Trash2 /> Elimina
            </Button>
        );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={title} />

            <div className="flex flex-col gap-4 p-4">
                <FlashMessage />

                {notifications.length > 0 && (
                    <Alert role="status">
                        <AlertDescription className="flex flex-col gap-2">
                            {notifications.map((notification) => (
                                <p key={notification.id}>🔔 {notification.message}</p>
                            ))}
                            <div>
                                <Button variant="outline" size="sm" onClick={markNotificationsRead}>
                                    Segna come lette
                                </Button>
                            </div>
                        </AlertDescription>
                    </Alert>
                )}

                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    {tab === 'absences' ? (
                        <Button onClick={() => openAbsence(null)}>
                            <Plus /> Nuova assenza
                        </Button>
                    ) : (
                        can_manage_holidays && (
                            <Button variant="outline" asChild>
                                <Link href={route('company-calendar.edit')}>
                                    <Settings /> Gestisci nelle impostazioni
                                </Link>
                            </Button>
                        )
                    )}
                </div>

                <div role="tablist" className="flex gap-2">
                    <Button
                        role="tab"
                        aria-selected={tab === 'absences'}
                        variant={tab === 'absences' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setTab('absences')}
                    >
                        Assenze
                    </Button>
                    <Button
                        role="tab"
                        aria-selected={tab === 'holidays'}
                        variant={tab === 'holidays' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setTab('holidays')}
                    >
                        Giorni non lavorativi
                    </Button>
                </div>

                {tab === 'absences' && reasonAction && (
                    <div role="alert" className="border-destructive/50 bg-destructive/10 grid gap-2 rounded-md border p-3 text-sm">
                        <p>
                            {reasonAction.kind === 'reject'
                                ? `Rifiuti l'assenza di ${reasonAction.absence.user_name} (${formatPeriod(reasonAction.absence)}). Scrivi il motivo: lo vedrà la persona.`
                                : `Elimini l'assenza già confermata di ${reasonAction.absence.user_name} (${formatPeriod(reasonAction.absence)}). Scrivi il motivo: la persona lo riceverà come notifica.`}
                        </p>
                        <textarea
                            aria-label="Motivo"
                            value={reason}
                            onChange={(event) => setReason(event.target.value)}
                            rows={2}
                            className="border-input bg-background focus-visible:ring-ring rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none"
                        />
                        <InputError message={reasonError} />
                        <div className="flex justify-end gap-2">
                            <Button variant="outline" size="sm" onClick={closeReasonAction}>
                                Annulla
                            </Button>
                            <Button variant="destructive" size="sm" onClick={submitReason}>
                                {reasonAction.kind === 'reject' ? 'Rifiuta' : 'Elimina'}
                            </Button>
                        </div>
                    </div>
                )}

                {tab === 'absences' &&
                    (absences.length === 0 ? (
                        <div className="border-sidebar-border/70 flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center">
                            <p className="text-muted-foreground">{showPerson ? 'Nessuna assenza richiesta' : 'Non hai richiesto assenze'}</p>
                            <Button onClick={() => openAbsence(null)}>Richiedi un&apos;assenza</Button>
                        </div>
                    ) : (
                        <div className="border-sidebar-border/70 overflow-x-auto rounded-xl border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        {showPerson && <th className="px-4 py-3 font-medium">Persona</th>}
                                        <th className="px-4 py-3 font-medium">Tipo</th>
                                        <th className="px-4 py-3 font-medium">Periodo</th>
                                        <th className="px-4 py-3 font-medium">Note</th>
                                        <th className="px-4 py-3 font-medium">Stato</th>
                                        <th className="px-4 py-3" />
                                    </tr>
                                </thead>
                                <tbody>
                                    {absences.map((absence) => (
                                        <tr key={absence.id} className="border-sidebar-border/70 border-t">
                                            {showPerson && (
                                                <td className="px-4 py-3 font-medium">
                                                    {absence.is_own ? `${absence.user_name} (tu)` : absence.user_name}
                                                </td>
                                            )}
                                            <td className="px-4 py-3">{absenceTypeLabel(absence.type)}</td>
                                            <td className="px-4 py-3 whitespace-nowrap">{formatPeriod(absence)}</td>
                                            <td className="text-muted-foreground px-4 py-3">{absence.note}</td>
                                            <td className="px-4 py-3">
                                                <Badge variant={statusVariant[absence.status]}>{statusLabel[absence.status]}</Badge>
                                                {absence.status === 'in_attesa' && (
                                                    <p className="text-muted-foreground mt-1 text-xs">{approvalProgress(absence)}</p>
                                                )}
                                                {absence.rejection_reason && (
                                                    <p className="text-muted-foreground mt-1 text-xs">Motivo: {absence.rejection_reason}</p>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right whitespace-nowrap">
                                                {absence.can_confirm && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => confirmAbsence(absence)}
                                                        aria-label={`Conferma assenza di ${absence.user_name}`}
                                                    >
                                                        <Check /> Conferma
                                                    </Button>
                                                )}
                                                {absence.can_approve && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => approveAbsence(absence)}
                                                        aria-label={`Approva assenza di ${absence.user_name}`}
                                                    >
                                                        <Check /> Approva
                                                    </Button>
                                                )}
                                                {absence.can_reject && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => startReasonAction('reject', absence)}
                                                        aria-label={`Rifiuta assenza di ${absence.user_name}`}
                                                    >
                                                        <X /> Rifiuta
                                                    </Button>
                                                )}
                                                {absence.can_update && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => openAbsence(absence)}
                                                        aria-label="Modifica assenza"
                                                    >
                                                        <Pencil /> Modifica
                                                    </Button>
                                                )}
                                                {absence.can_delete &&
                                                    (absence.status === 'confermata' && !absence.is_own ? (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => startReasonAction('delete', absence)}
                                                            aria-label={`Elimina assenza di ${absence.user_name}`}
                                                        >
                                                            <Trash2 /> Elimina
                                                        </Button>
                                                    ) : (
                                                        deleteControls(`absence-${absence.id}`, route('absences.destroy', absence.id), 'assenza')
                                                    ))}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ))}

                {tab === 'holidays' &&
                    (holidays.length === 0 ? (
                        <div className="border-sidebar-border/70 flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center">
                            <p className="text-muted-foreground">Nessuna festività o chiusura inserita</p>
                        </div>
                    ) : (
                        <div className="border-sidebar-border/70 overflow-x-auto rounded-xl border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">Data</th>
                                        <th className="px-4 py-3 font-medium">Nome</th>
                                        <th className="px-4 py-3 font-medium">Tipo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {holidays.map((holiday) => (
                                        <tr key={holiday.id} className="border-sidebar-border/70 border-t">
                                            <td className="px-4 py-3 whitespace-nowrap">{formatDate(holiday.date)}</td>
                                            <td className="px-4 py-3 font-medium">{holiday.name}</td>
                                            <td className="px-4 py-3">
                                                <Badge variant={holiday.kind === 'chiusura' ? 'secondary' : 'outline'}>
                                                    {holidayKindLabels[holiday.kind]}
                                                </Badge>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ))}
            </div>

            <AbsenceFormSheet open={absenceSheetOpen} absence={editingAbsence} onClose={() => setAbsenceSheetOpen(false)} />
        </AppLayout>
    );
}
