import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { holidaySchema, type HolidayFormData } from '@/lib/schemas/holiday';
import { flattenZodErrors } from '@/lib/zod-errors';
import { type Holiday } from '@/types';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect } from 'react';

interface HolidayFormSheetProps {
    open: boolean;
    holiday: Holiday | null;
    onClose: () => void;
}

/** Drawer laterale per aggiungere o modificare un festivo (solo HR). */
export function HolidayFormSheet({ open, holiday, onClose }: HolidayFormSheetProps) {
    const { data, setData, post, put, processing, errors, setError, clearErrors, reset } = useForm<HolidayFormData>({
        date: '',
        name: '',
    });

    useEffect(() => {
        if (open) {
            clearErrors();
            setData({ date: holiday?.date ?? '', name: holiday?.name ?? '' });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, holiday]);

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

    return (
        <Sheet open={open} onOpenChange={(next) => !next && onClose()}>
            <SheetContent side="right" className="w-full sm:max-w-md">
                <form onSubmit={submit} className="flex h-full flex-col gap-6" noValidate>
                    <SheetHeader>
                        <SheetTitle>{holiday ? 'Modifica festivo' : 'Nuovo festivo'}</SheetTitle>
                        <SheetDescription>Un festivo non è un giorno lavorativo per nessuno.</SheetDescription>
                    </SheetHeader>

                    <div className="flex flex-1 flex-col gap-4 px-4">
                        <div className="grid gap-2">
                            <Label htmlFor="holiday-date">Data</Label>
                            <Input id="holiday-date" type="date" value={data.date} onChange={(event) => setData('date', event.target.value)} />
                            <InputError message={errors.date} />
                        </div>

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
