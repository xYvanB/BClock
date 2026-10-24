import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { areaSchema, type AreaFormData } from '@/lib/schemas/area';
import { flattenZodErrors } from '@/lib/zod-errors';
import { type Area } from '@/types';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect } from 'react';

interface AreaFormSheetProps {
    open: boolean;
    area: Area | null;
    onClose: () => void;
}

/** Drawer laterale per creare o modificare un'area (F13). */
export function AreaFormSheet({ open, area, onClose }: AreaFormSheetProps) {
    const { data, setData, post, put, processing, errors, setError, clearErrors, reset } = useForm<AreaFormData>({
        name: '',
        description: '',
    });

    useEffect(() => {
        if (open) {
            clearErrors();
            setData({ name: area?.name ?? '', description: area?.description ?? '' });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, area]);

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        const result = areaSchema.safeParse(data);
        if (!result.success) {
            const fieldErrors = flattenZodErrors<keyof AreaFormData>(result.error);
            (Object.keys(fieldErrors) as (keyof AreaFormData)[]).forEach((field) => setError(field, fieldErrors[field] as string));

            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        };

        if (area) {
            put(route('areas.update', area.id), options);
        } else {
            post(route('areas.store'), options);
        }
    };

    return (
        <Sheet open={open} onOpenChange={(next) => !next && onClose()}>
            <SheetContent side="right" className="w-full sm:max-w-md">
                <form onSubmit={submit} className="flex h-full flex-col gap-6" noValidate>
                    <SheetHeader>
                        <SheetTitle>{area ? 'Modifica area' : 'Nuova area'}</SheetTitle>
                        <SheetDescription>Un'area raggruppa le persone di un team.</SheetDescription>
                    </SheetHeader>

                    <div className="flex flex-1 flex-col gap-4 px-4">
                        <div className="grid gap-2">
                            <Label htmlFor="area-name">Nome</Label>
                            <Input
                                id="area-name"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                aria-invalid={Boolean(errors.name)}
                                autoFocus
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="area-description">Descrizione</Label>
                            <textarea
                                id="area-description"
                                value={data.description}
                                onChange={(event) => setData('description', event.target.value)}
                                rows={4}
                                className="border-input bg-background focus-visible:ring-ring rounded-md border px-3 py-2 text-sm focus-visible:ring-1 focus-visible:outline-none"
                            />
                            <InputError message={errors.description} />
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
