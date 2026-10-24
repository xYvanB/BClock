import { AbsenceFormSheet } from '@/components/absence-form-sheet';
import { FlashMessage } from '@/components/flash-message';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { t } from '@/lib/i18n';
import { absenceTypeLabel } from '@/lib/schemas/absence';
import { holidayKindLabels } from '@/lib/schemas/holiday';
import { type Absence, type BreadcrumbItem, type Holiday, type SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Check, Pencil, Plus, Settings, Trash2, X } from 'lucide-react';
import { useState } from 'react';

interface AbsencesIndexProps {
    absences: Absence[];
    holidays: Holiday[];
    can_manage_holidays: boolean;
}

type Tab = 'absences' | 'holidays';

/** HR action that requires a reason: rejecting a request or deleting an already confirmed absence. */
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

/** Who already approved a still-pending request (area head only for employees). */
const approvalProgress = (absence: Absence): string => {
    const steps = [`${absence.approver_label}: ${absence.hr_approved ? 'ok' : 'da fare'}`];

    if (absence.needs_area_head) {
        steps.unshift(`Capo area: ${absence.area_head_approved ? 'ok' : 'da fare'}`);
    }

    return steps.join(' · ');
};

const statusLabel = { in_attesa: 'In attesa', confermata: 'Confermata', rifiutata: 'Rifiutata' } as const;

const statusVariant = { in_attesa: 'secondary', confermata: 'default', rifiutata: 'destructive' } as const;

/** Absences (everyone their own; the area head also their area's, the Boss HR's, HR everyone's) and non-working days. */
export default function AbsencesIndex({ absences, holidays, can_manage_holidays }: AbsencesIndexProps) {
    const { auth } = usePage<SharedData>().props;
    const showPerson = auth.user.role !== 'dipendente';
    const title = t('Assenze');
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
            setReasonError(t('Il motivo è obbligatorio.'));

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

    const remove = (key: string, url: string) =>
        router.delete(url, { preserveScroll: true, onFinish: () => setConfirmingDelete((current) => (current === key ? null : current)) });

    const deleteControls = (key: string, url: string, label: string) =>
        confirmingDelete === key ? (
            <span className="inline-flex items-center gap-1">
                <span className="text-sm">{t('Eliminare?')}</span>
                <Button variant="destructive" size="sm" onClick={() => remove(key, url)}>
                    {t('Elimina')}
                </Button>
                <Button variant="outline" size="sm" onClick={() => setConfirmingDelete(null)}>
                    {t('Annulla')}
                </Button>
            </span>
        ) : (
            <Button variant="ghost" size="sm" onClick={() => setConfirmingDelete(key)} aria-label={t('Elimina :label', { label })}>
                <Trash2 /> {t('Elimina')}
            </Button>
        );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={title} />

            <div className="flex flex-col gap-4 p-4">
                <FlashMessage />

                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    {tab === 'absences' ? (
                        <Button onClick={() => openAbsence(null)}>
                            <Plus /> {t('Nuova assenza')}
                        </Button>
                    ) : (
                        can_manage_holidays && (
                            <Button variant="outline" asChild>
                                <Link href={route('company-calendar.edit')}>
                                    <Settings /> {t('Gestisci nelle impostazioni')}
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
                        {t('Assenze')}
                    </Button>
                    <Button
                        role="tab"
                        aria-selected={tab === 'holidays'}
                        variant={tab === 'holidays' ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => setTab('holidays')}
                    >
                        {t('Giorni non lavorativi')}
                    </Button>
                </div>

                {tab === 'absences' && reasonAction && (
                    <div role="alert" className="border-destructive/50 bg-destructive/10 grid gap-2 rounded-md border p-3 text-sm">
                        <p>
                            {reasonAction.kind === 'reject'
                                ? t("Rifiuti l'assenza di :name (:period). Scrivi il motivo: lo vedrà la persona.", {
                                      name: reasonAction.absence.user_name,
                                      period: formatPeriod(reasonAction.absence),
                                  })
                                : t("Elimini l'assenza già confermata di :name (:period). Scrivi il motivo: la persona lo riceverà come notifica.", {
                                      name: reasonAction.absence.user_name,
                                      period: formatPeriod(reasonAction.absence),
                                  })}
                        </p>
                        <textarea
                            aria-label={t('Motivo')}
                            value={reason}
                            onChange={(event) => setReason(event.target.value)}
                            rows={2}
                            className="border-input bg-background focus-visible:ring-ring rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none"
                        />
                        <InputError message={reasonError} />
                        <div className="flex justify-end gap-2">
                            <Button variant="outline" size="sm" onClick={closeReasonAction}>
                                {t('Annulla')}
                            </Button>
                            <Button variant="destructive" size="sm" onClick={submitReason}>
                                {reasonAction.kind === 'reject' ? t('Rifiuta') : t('Elimina')}
                            </Button>
                        </div>
                    </div>
                )}

                {tab === 'absences' &&
                    (absences.length === 0 ? (
                        <div className="border-sidebar-border/70 flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center">
                            <p className="text-muted-foreground">{showPerson ? t('Nessuna assenza richiesta') : t('Non hai richiesto assenze')}</p>
                            <Button onClick={() => openAbsence(null)}>{t("Richiedi un'assenza")}</Button>
                        </div>
                    ) : (
                        <div className="border-sidebar-border/70 overflow-x-auto rounded-xl border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        {showPerson && <th className="px-4 py-3 font-medium">{t('Persona')}</th>}
                                        <th className="px-4 py-3 font-medium">{t('Tipo')}</th>
                                        <th className="px-4 py-3 font-medium">{t('Periodo')}</th>
                                        <th className="px-4 py-3 font-medium">{t('Note')}</th>
                                        <th className="px-4 py-3 font-medium">{t('Stato')}</th>
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
                                                <Badge variant={statusVariant[absence.status]}>{t(statusLabel[absence.status])}</Badge>
                                                {absence.status === 'in_attesa' && (
                                                    <p className="text-muted-foreground mt-1 text-xs">{approvalProgress(absence)}</p>
                                                )}
                                                {absence.rejection_reason && (
                                                    <p className="text-muted-foreground mt-1 text-xs">
                                                        {t('Motivo: :reason', { reason: absence.rejection_reason })}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right whitespace-nowrap">
                                                {absence.can_confirm && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => confirmAbsence(absence)}
                                                        aria-label={t('Conferma assenza di :name', { name: absence.user_name })}
                                                    >
                                                        <Check /> {t('Conferma')}
                                                    </Button>
                                                )}
                                                {absence.can_approve && (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => approveAbsence(absence)}
                                                        aria-label={t('Approva assenza di :name', { name: absence.user_name })}
                                                    >
                                                        <Check /> {t('Approva')}
                                                    </Button>
                                                )}
                                                {absence.can_reject && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => startReasonAction('reject', absence)}
                                                        aria-label={t('Rifiuta assenza di :name', { name: absence.user_name })}
                                                    >
                                                        <X /> {t('Rifiuta')}
                                                    </Button>
                                                )}
                                                {absence.can_update && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => openAbsence(absence)}
                                                        aria-label={t('Modifica assenza')}
                                                    >
                                                        <Pencil /> {t('Modifica')}
                                                    </Button>
                                                )}
                                                {absence.can_delete &&
                                                    (absence.status === 'confermata' && !absence.is_own ? (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => startReasonAction('delete', absence)}
                                                            aria-label={t('Elimina assenza di :name', { name: absence.user_name })}
                                                        >
                                                            <Trash2 /> {t('Elimina')}
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
                            <p className="text-muted-foreground">{t('Nessuna festività o chiusura inserita')}</p>
                        </div>
                    ) : (
                        <div className="border-sidebar-border/70 overflow-x-auto rounded-xl border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/50 text-left">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">{t('Data')}</th>
                                        <th className="px-4 py-3 font-medium">{t('Nome')}</th>
                                        <th className="px-4 py-3 font-medium">{t('Tipo')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {holidays.map((holiday) => (
                                        <tr key={holiday.id} className="border-sidebar-border/70 border-t">
                                            <td className="px-4 py-3 whitespace-nowrap">{formatDate(holiday.date)}</td>
                                            <td className="px-4 py-3 font-medium">{holiday.name}</td>
                                            <td className="px-4 py-3">
                                                <Badge variant={holiday.kind === 'chiusura' ? 'secondary' : 'outline'}>
                                                    {t(holidayKindLabels[holiday.kind])}
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
