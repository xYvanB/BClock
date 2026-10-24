import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { holidayKindLabels, holidaySchema, type HolidayFormData } from '@/lib/schemas/holiday';
import { flattenZodErrors } from '@/lib/zod-errors';
import { type Holiday } from '@/types';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect } from 'react';

interface HolidayFormSheetProps {
    open: boolean;
    holiday: Holiday | null;
    /** Type preselected when creating a new one. */
    defaultKind?: HolidayFormData['kind'];
    onClose: () => void;
}

const fieldClass =
    'border-input bg-background focus-visible:ring-ring h-9 w-full rounded-md border px-3 text-sm focus-visible:ring-1 focus-visible:outline-none';

/** Side drawer to add or edit a holiday or a company closure (Boss and HR). */
export function HolidayFormSheet({ open, holiday, defaultKind = 'festivo', onClose }: HolidayFormSheetProps) {
    const { data, setData, post, put, processing, errors, setError, clearErrors, reset } = useForm<HolidayFormData>({
        date: '',
        name: '',
        kind: defaultKind,
        until: '',
    });

    useEffect(() => {
        if (open) {
            clearErrors();
            setData({ date: holiday?.date ?? '', name: holiday?.name ?? '', kind: holiday?.kind ?? defaultKind, until: '' });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, holiday, defaultKind]);

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        const result = holidaySchema.safeParse(data);
        if (!result.success) {
            const fieldErrors = flattenZodErrors<keyof HolidayFormData>(result.error);
            (Object.keys(fieldErrors) as (keyof HolidayFormData)[]).forEach((field) => setError(field, fieldErrors[field] as string));

            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        };

        if (holiday) {
            put(route('holidays.update', holiday.id), options);
        } else {
            post(route('holidays.store'), options);
        }
    };

    const isNew = holiday === null;

    return (
        <Sheet open={open} onOpenChange={(next) => !next && onClose()}>
            <SheetContent side="right" className="w-full sm:max-w-md">
                <form onSubmit={submit} className="flex h-full flex-col gap-6" noValidate>
                    <SheetHeader>
                        <SheetTitle>{isNew ? 'Nuovo giorno non lavorativo' : 'Modifica giorno non lavorativo'}</SheetTitle>
                        <SheetDescription>Nessuno lavora in questi giorni e non si possono chiedere assenze.</SheetDescription>
                    </SheetHeader>

                    <div className="flex flex-1 flex-col gap-4 px-4">
                        <div className="grid gap-2">
                            <Label htmlFor="holiday-kind">Tipo</Label>
                            <select
                                id="holiday-kind"
                                className={fieldClass}
                                value={data.kind}
                                onChange={(event) => setData('kind', event.target.value as HolidayFormData['kind'])}
                            >
                                {(Object.keys(holidayKindLabels) as (keyof typeof holidayKindLabels)[]).map((kind) => (
                                    <option key={kind} value={kind}>
                                        {holidayKindLabels[kind]}
                                    </option>
                                ))}
                            </select>
                            <p className="text-muted-foreground text-sm">
                                {data.kind === 'festivo'
                                    ? 'Natale, Ognissanti… una festività del calendario.'
                                    : "Un ponte o un'altra chiusura dell'azienda non legata a una festività."}
                            </p>
                            <InputError message={errors.kind} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="holiday-date">{isNew ? 'Dal' : 'Data'}</Label>
                            <Input id="holiday-date" type="date" value={data.date} onChange={(event) => setData('date', event.target.value)} />
                            <InputError message={errors.date} />
                        </div>

                        {isNew && (
                            <div className="grid gap-2">
                                <Label htmlFor="holiday-until">Fino al (facoltativo)</Label>
                                <Input id="holiday-until" type="date" value={data.until} onChange={(event) => setData('until', event.target.value)} />
                                <p className="text-muted-foreground text-sm">
                                    Per un periodo: ogni giorno lavorativo dal primo all'ultimo diventa non lavorativo.
                                </p>
                                <InputError message={errors.until} />
                            </div>
                        )}

                        <div className="grid gap-2">
                            <Label htmlFor="holiday-name">Nome</Label>
                            <Input
                                id="holiday-name"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                aria-invalid={Boolean(errors.name)}
                            />
                            <InputError message={errors.name} />
                        </div>
                    </div>

                    <SheetFooter className="flex-row justify-end gap-2">
                        <Button type="button" variant="outline" onClick={onClose}>
                            Annulla
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Salva
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
