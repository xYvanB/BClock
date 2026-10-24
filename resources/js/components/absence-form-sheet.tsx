import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
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

/** Drawer laterale per richiedere o modificare una propria assenza; resta in attesa finché l'HR non la conferma. */
export function AbsenceFormSheet({ open, absence, onClose }: AbsenceFormSheetProps) {
    const { data, setData, post, put, processing, errors, setError, clearErrors, reset } = useForm<AbsenceFormData>({
        type: 'ferie',
        starts_on: '',
        ends_on: '',
        note: '',
    });

    useEffect(() => {
        if (open) {
            clearErrors();
            setData({
                type: absence?.type ?? 'ferie',
                starts_on: absence?.starts_on ?? '',
                ends_on: absence?.ends_on ?? '',
                note: absence?.note ?? '',
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, absence]);

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
                        <SheetTitle>{absence ? 'Modifica assenza' : 'Nuova assenza'}</SheetTitle>
                        <SheetDescription>La richiesta va all'HR. Il tuo carico cambia solo dopo la conferma.</SheetDescription>
                    </SheetHeader>

                    <div className="flex flex-1 flex-col gap-4 px-4">
                        <div className="grid gap-2">
                            <Label htmlFor="absence-type">Tipo</Label>
                            <select
                                id="absence-type"
                                className={fieldClass}
                                value={data.type}
                                onChange={(event) => setData('type', event.target.value as AbsenceFormData['type'])}
                            >
                                {absenceTypeOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.type} />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="absence-start">Dal</Label>
                                <Input
                                    id="absence-start"
                                    type="date"
                                    value={data.starts_on}
                                    onChange={(event) => setData('starts_on', event.target.value)}
                                />
                                <InputError message={errors.starts_on} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="absence-end">Al</Label>
                                <Input
                                    id="absence-end"
                                    type="date"
                                    value={data.ends_on}
                                    onChange={(event) => setData('ends_on', event.target.value)}
                                />
                                <InputError message={errors.ends_on} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="absence-note">Note</Label>
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
                            Annulla
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {absence ? 'Salva' : "Invia all'HR"}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
