import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { t } from '@/lib/i18n';
import { absenceSchema, absenceTypeOptions, type AbsenceFormData } from '@/lib/schemas/absence';
import { flattenZodErrors } from '@/lib/zod-errors';
import { type Absence } from '@/types';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect } from 'react';

interface AbsenceFormSheetProps {
    open: boolean;
    absence: Absence | null;
    onClose: () => void;
}

const fieldClass =
    'border-input bg-background focus-visible:ring-ring h-9 w-full rounded-md border px-3 text-sm focus-visible:ring-1 focus-visible:outline-none';

/** Side drawer to request or edit one's own absence; it stays pending until it has all the approvals. */
export function AbsenceFormSheet({ open, absence, onClose }: AbsenceFormSheetProps) {
    const { data, setData, post, put, processing, errors, setError, clearErrors, reset } = useForm<AbsenceFormData>({
        type: 'ferie',
        starts_on: '',
        ends_on: '',
        starts_time: '',
        ends_time: '',
        note: '',
    });

    const isLeave = data.type === 'permesso';

    useEffect(() => {
        if (open) {
            clearErrors();
            setData({
                type: absence?.type ?? 'ferie',
                starts_on: absence?.starts_on ?? '',
                ends_on: absence?.ends_on ?? '',
                starts_time: absence?.starts_time ?? '',
                ends_time: absence?.ends_time ?? '',
                note: absence?.note ?? '',
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, absence]);

    /** A leave is within a single day: the end date follows the start date. */
    const changeStartDate = (value: string) =>
        setData((current) => ({ ...current, starts_on: value, ends_on: current.type === 'permesso' ? value : current.ends_on }));

    const changeType = (value: AbsenceFormData['type']) =>
        setData((current) => ({ ...current, type: value, ends_on: value === 'permesso' ? current.starts_on : current.ends_on }));

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        const result = absenceSchema.safeParse(data);
        if (!result.success) {
            const fieldErrors = flattenZodErrors<keyof AbsenceFormData>(result.error);
            (Object.keys(fieldErrors) as (keyof AbsenceFormData)[]).forEach((field) => setError(field, fieldErrors[field] as string));

            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        };

        if (absence) {
            put(route('absences.update', absence.id), options);
        } else {
            post(route('absences.store'), options);
        }
    };

    return (
        <Sheet open={open} onOpenChange={(next) => !next && onClose()}>
            <SheetContent side="right" className="w-full sm:max-w-md">
                <form onSubmit={submit} className="flex h-full flex-col gap-6" noValidate>
                    <SheetHeader>
                        <SheetTitle>{absence ? t('Modifica assenza') : t('Nuova assenza')}</SheetTitle>
                        <SheetDescription>
                            {t("La richiesta va all'HR (e al tuo Capo area, se sei un dipendente). Il tuo carico cambia solo dopo le conferme.")}
                        </SheetDescription>
                    </SheetHeader>

                    <div className="flex flex-1 flex-col gap-4 px-4">
                        <div className="grid gap-2">
                            <Label htmlFor="absence-type">{t('Tipo')}</Label>
                            <select
                                id="absence-type"
                                className={fieldClass}
                                value={data.type}
                                onChange={(event) => changeType(event.target.value as AbsenceFormData['type'])}
                            >
                                {absenceTypeOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {t(option.label)}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.type} />
                        </div>

                        {isLeave ? (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="absence-start">{t('Giorno')}</Label>
                                    <Input
                                        id="absence-start"
                                        type="date"
                                        value={data.starts_on}
                                        onChange={(event) => changeStartDate(event.target.value)}
                                    />
                                    <InputError message={errors.starts_on ?? errors.ends_on} />
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="absence-start-time">{t('Dalle')}</Label>
                                        <Input
                                            id="absence-start-time"
                                            type="time"
                                            value={data.starts_time}
                                            onChange={(event) => setData('starts_time', event.target.value)}
                                        />
                                        <InputError message={errors.starts_time} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="absence-end-time">{t('Alle')}</Label>
                                        <Input
                                            id="absence-end-time"
                                            type="time"
                                            value={data.ends_time}
                                            onChange={(event) => setData('ends_time', event.target.value)}
                                        />
                                        <InputError message={errors.ends_time} />
                                    </div>
                                </div>
                            </>
                        ) : (
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="absence-start">{t('Dal')}</Label>
                                    <Input
                                        id="absence-start"
                                        type="date"
                                        value={data.starts_on}
                                        onChange={(event) => changeStartDate(event.target.value)}
                                    />
                                    <InputError message={errors.starts_on} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="absence-end">{t('Al')}</Label>
                                    <Input
                                        id="absence-end"
                                        type="date"
                                        value={data.ends_on}
                                        onChange={(event) => setData('ends_on', event.target.value)}
                                    />
                                    <InputError message={errors.ends_on} />
                                </div>
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="absence-note">{t('Note')}</Label>
                            <textarea
                                id="absence-note"
                                value={data.note}
                                onChange={(event) => setData('note', event.target.value)}
                                rows={3}
                                className="border-input bg-background focus-visible:ring-ring rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none"
                            />
                            <InputError message={errors.note} />
                        </div>
                    </div>

                    <SheetFooter className="flex-row justify-end gap-2">
                        <Button type="button" variant="outline" onClick={onClose}>
                            {t('Annulla')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {absence ? t('Salva') : t('Invia la richiesta')}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
