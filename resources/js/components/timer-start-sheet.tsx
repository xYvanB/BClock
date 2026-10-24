import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { type ActivityOption } from '@/types';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect } from 'react';

interface TimerStartSheetProps {
    open: boolean;
    activities: ActivityOption[];
    onClose: () => void;
}

const fieldClass =
    'border-input bg-background focus-visible:ring-ring h-9 w-full rounded-md border px-3 text-sm focus-visible:ring-1 focus-visible:outline-none';

/** Drawer to start a timer: activity and optional description (F4). */
export function TimerStartSheet({ open, activities, onClose }: TimerStartSheetProps) {
    const { data, setData, post, processing, errors, setError, clearErrors, reset } = useForm({ activity_id: '', description: '' });

    useEffect(() => {
        if (open) {
            clearErrors();
            reset();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        if (data.activity_id === '') {
            setError('activity_id', "Scegli un'attività");

            return;
        }

        post(route('timers.store'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    return (
        <Sheet open={open} onOpenChange={(next) => !next && onClose()}>
            <SheetContent side="right" className="w-full sm:max-w-md">
                <form onSubmit={submit} className="flex h-full flex-col gap-6" noValidate>
                    <SheetHeader>
                        <SheetTitle>Avvia timer</SheetTitle>
                        <SheetDescription>
                            Puoi avere più timer attivi, uno per attività. Allo stop diventa una registrazione che puoi modificare.
                        </SheetDescription>
                    </SheetHeader>

                    <div className="flex flex-1 flex-col gap-4 px-4">
                        <div className="grid gap-2">
                            <Label htmlFor="timer-activity">Attività</Label>
                            <select
                                id="timer-activity"
                                className={fieldClass}
                                value={data.activity_id}
                                onChange={(event) => setData('activity_id', event.target.value)}
                                aria-invalid={Boolean(errors.activity_id)}
                            >
                                <option value="">Scegli un'attività…</option>
                                {activities.map((activity) => (
                                    <option key={activity.id} value={activity.id}>
                                        {activity.project_name} › {activity.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.activity_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="timer-description">Descrizione (facoltativa)</Label>
                            <textarea
                                id="timer-description"
                                value={data.description}
                                onChange={(event) => setData('description', event.target.value)}
                                rows={3}
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
                            Avvia
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
