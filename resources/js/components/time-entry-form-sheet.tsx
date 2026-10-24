import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { t } from '@/lib/i18n';
import { timeEntrySchema, type TimeEntryFormData } from '@/lib/schemas/time-entry';
import { today } from '@/lib/time';
import { flattenZodErrors } from '@/lib/zod-errors';
import { type ActivityOption, type TimeEntryItem } from '@/types';
import { router, useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect } from 'react';

interface TimeEntryFormSheetProps {
    open: boolean;
    entry: (TimeEntryItem & { date: string }) | null;
    activities: ActivityOption[];
    onClose: () => void;
}

const fieldClass =
    'border-input bg-background focus-visible:ring-ring h-9 w-full rounded-md border px-3 text-sm focus-visible:ring-1 focus-visible:outline-none';

/** Side drawer to log, edit or delete hours (F3, F5). */
export function TimeEntryFormSheet({ open, entry, activities, onClose }: TimeEntryFormSheetProps) {
    const { data, setData, post, put, processing, errors, setError, clearErrors, reset } = useForm<TimeEntryFormData>({
        activity_id: '',
        date: today(),
        started_at: '09:00',
        ended_at: '10:00',
        description: '',
    });

    useEffect(() => {
        if (open) {
            clearErrors();
            setData({
                activity_id: entry ? String(entry.activity_id) : '',
                date: entry?.date ?? today(),
                started_at: entry?.started_at ?? '09:00',
                ended_at: entry?.ended_at ?? '10:00',
                description: entry?.description ?? '',
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, entry]);

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        const result = timeEntrySchema.safeParse(data);
        if (!result.success) {
            const fieldErrors = flattenZodErrors<keyof TimeEntryFormData>(result.error);
            (Object.keys(fieldErrors) as (keyof TimeEntryFormData)[]).forEach((field) => setError(field, fieldErrors[field] as string));

            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        };

        if (entry) {
            put(route('time-entries.update', entry.id), options);
        } else {
            post(route('time-entries.store'), options);
        }
    };

    const remove = () => {
        if (entry && window.confirm(t('Eliminare questa registrazione?'))) {
            router.delete(route('time-entries.destroy', entry.id), { preserveScroll: true, onSuccess: onClose });
        }
    };

    return (
        <Sheet open={open} onOpenChange={(next) => !next && onClose()}>
            <SheetContent side="right" className="w-full sm:max-w-md">
                <form onSubmit={submit} className="flex h-full flex-col gap-6" noValidate>
                    <SheetHeader>
                        <SheetTitle>{entry ? t('Modifica registrazione') : t('Nuova registrazione')}</SheetTitle>
                        <SheetDescription>{t('Le sovrapposizioni di orario sono ammesse e le ore passate restano modificabili.')}</SheetDescription>
                    </SheetHeader>

                    <div className="flex flex-1 flex-col gap-4 overflow-y-auto px-4">
                        <div className="grid gap-2">
                            <Label htmlFor="te-activity">{t('Attività')}</Label>
                            <select
                                id="te-activity"
                                className={fieldClass}
                                value={data.activity_id}
                                onChange={(event) => setData('activity_id', event.target.value)}
                                aria-invalid={Boolean(errors.activity_id)}
                            >
                                <option value="">{t("Scegli un'attività…")}</option>
                                {activities.map((activity) => (
                                    <option key={activity.id} value={activity.id}>
                                        {activity.project_name} › {activity.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.activity_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="te-date">{t('Data')}</Label>
                            <Input id="te-date" type="date" value={data.date} onChange={(event) => setData('date', event.target.value)} />
                            <InputError message={errors.date} />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="te-start">{t('Ora inizio')}</Label>
                                <Input
                                    id="te-start"
                                    type="time"
                                    value={data.started_at}
                                    onChange={(event) => setData('started_at', event.target.value)}
                                />
                                <InputError message={errors.started_at} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="te-end">{t('Ora fine')}</Label>
                                <Input id="te-end" type="time" value={data.ended_at} onChange={(event) => setData('ended_at', event.target.value)} />
                                <InputError message={errors.ended_at} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="te-description">{t('Descrizione')}</Label>
                            <textarea
                                id="te-description"
                                rows={3}
                                value={data.description}
                                onChange={(event) => setData('description', event.target.value)}
                                className="border-input bg-background focus-visible:ring-ring rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none"
                            />
                            <InputError message={errors.description} />
                        </div>
                    </div>

                    <SheetFooter className="flex-row justify-between gap-2">
                        {entry ? (
                            <Button type="button" variant="outline" onClick={remove}>
                                {t('❌ Elimina')}
                            </Button>
                        ) : (
                            <span />
                        )}
                        <div className="flex gap-2">
                            <Button type="button" variant="outline" onClick={onClose}>
                                {t('Annulla')}
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {t('Salva')}
                            </Button>
                        </div>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
