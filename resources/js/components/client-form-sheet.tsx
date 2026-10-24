import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { clientSchema, type ClientContactFormData, type ClientFormData } from '@/lib/schemas/client';
import { flattenZodErrors } from '@/lib/zod-errors';
import { type ClientContact } from '@/types';
import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useEffect, useState } from 'react';

interface ClientFormSheetProps {
    open: boolean;
    client: { id: number; company_name: string; contacts: ClientContact[] } | null;
    onClose: () => void;
}

const emptyContact = (isPrimary: boolean): ClientContactFormData => ({ id: null, name: '', email: '', phone: '', is_primary: isPrimary });

/** Drawer laterale per creare o modificare un cliente con i suoi referenti (F6). */
export function ClientFormSheet({ open, client, onClose }: ClientFormSheetProps) {
    const { data, setData, post, put, processing, errors, setError, clearErrors, reset } = useForm<ClientFormData>({
        company_name: '',
        contacts: [],
    });

    const [confirmingRemoval, setConfirmingRemoval] = useState<number | null>(null);
    const linkedProjects = (id: number | null) => client?.contacts.find((contact) => contact.id === id)?.projects_count ?? 0;

    // Gli errori annidati del server (es. `contacts.0.email`) non sono nel tipo degli errori del form.
    const fieldErrors = errors as Record<string, string | undefined>;

    useEffect(() => {
        if (open) {
            clearErrors();
            setConfirmingRemoval(null);
            setData({
                company_name: client?.company_name ?? '',
                contacts: (client?.contacts ?? []).map((contact) => ({
                    id: contact.id,
                    name: contact.name,
                    email: contact.email ?? '',
                    phone: contact.phone ?? '',
                    is_primary: contact.is_primary,
                })),
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, client]);

    const updateContact = (index: number, changes: Partial<ClientContactFormData>) => {
        setData(
            'contacts',
            data.contacts.map((contact, position) => (position === index ? { ...contact, ...changes } : contact)),
        );
    };

    const addContact = () => setData('contacts', [...data.contacts, emptyContact(data.contacts.length === 0)]);

    const removeContact = (index: number) => {
        setConfirmingRemoval(null);
        const remaining = data.contacts.filter((_, position) => position !== index);
        // Se si toglie il principale, lo diventa il primo rimasto.
        if (remaining.length > 0 && !remaining.some((contact) => contact.is_primary)) {
            remaining[0] = { ...remaining[0], is_primary: true };
        }
        // Gli errori del server sono indicizzati per posizione: dopo la rimozione non corrispondono più.
        clearErrors();
        setData('contacts', remaining);
    };

    const makePrimary = (index: number) =>
        setData(
            'contacts',
            data.contacts.map((contact, position) => ({ ...contact, is_primary: position === index })),
        );

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        const result = clientSchema.safeParse(data);
        if (!result.success) {
            const zodErrors = flattenZodErrors<string>(result.error);
            Object.entries(zodErrors).forEach(([field, message]) => setError(field as keyof ClientFormData, message as string));

            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        };

        if (client) {
            put(route('clients.update', client.id), options);
        } else {
            post(route('clients.store'), options);
        }
    };

    return (
        <Sheet open={open} onOpenChange={(next) => !next && onClose()}>
            <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-lg">
                <form onSubmit={submit} className="flex h-full flex-col gap-6" noValidate>
                    <SheetHeader>
                        <SheetTitle>{client ? 'Modifica cliente' : 'Nuovo cliente'}</SheetTitle>
                        <SheetDescription>Ragione sociale e persone di riferimento del cliente.</SheetDescription>
                    </SheetHeader>

                    <div className="flex flex-1 flex-col gap-4 px-4">
                        <div className="grid gap-2">
                            <Label htmlFor="client-company-name">Ragione sociale</Label>
                            <Input
                                id="client-company-name"
                                value={data.company_name}
                                onChange={(event) => setData('company_name', event.target.value)}
                                aria-invalid={Boolean(errors.company_name)}
                                autoFocus
                            />
                            <InputError message={errors.company_name} />
                        </div>

                        <div className="grid gap-3">
                            <div className="flex items-center justify-between">
                                <Label>Referenti</Label>
                                <Button type="button" variant="outline" size="sm" onClick={addContact}>
                                    <Plus /> Aggiungi referente
                                </Button>
                            </div>
                            <InputError message={fieldErrors.contacts} />

                            {data.contacts.length === 0 && <p className="text-muted-foreground text-sm">Nessun referente.</p>}

                            {data.contacts.map((contact, index) => (
                                <fieldset key={contact.id ?? `new-${index}`} className="border-sidebar-border/70 grid gap-3 rounded-lg border p-3">
                                    <legend className="sr-only">Referente {index + 1}</legend>

                                    <div className="grid gap-2">
                                        <Label htmlFor={`contact-name-${index}`}>Nome</Label>
                                        <Input
                                            id={`contact-name-${index}`}
                                            value={contact.name}
                                            onChange={(event) => updateContact(index, { name: event.target.value })}
                                            aria-invalid={Boolean(fieldErrors[`contacts.${index}.name`])}
                                        />
                                        <InputError message={fieldErrors[`contacts.${index}.name`]} />
                                    </div>

                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor={`contact-email-${index}`}>Email</Label>
                                            <Input
                                                id={`contact-email-${index}`}
                                                type="email"
                                                value={contact.email}
                                                onChange={(event) => updateContact(index, { email: event.target.value })}
                                                aria-invalid={Boolean(fieldErrors[`contacts.${index}.email`])}
                                            />
                                            <InputError message={fieldErrors[`contacts.${index}.email`]} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor={`contact-phone-${index}`}>Telefono</Label>
                                            <Input
                                                id={`contact-phone-${index}`}
                                                value={contact.phone}
                                                onChange={(event) => updateContact(index, { phone: event.target.value })}
                                                aria-invalid={Boolean(fieldErrors[`contacts.${index}.phone`])}
                                            />
                                            <InputError message={fieldErrors[`contacts.${index}.phone`]} />
                                        </div>
                                    </div>
                                    <InputError message={fieldErrors[`contacts.${index}.id`]} />

                                    <div className="flex items-center justify-between">
                                        <label className="flex items-center gap-2 text-sm">
                                            <input
                                                type="radio"
                                                name="primary-contact"
                                                checked={contact.is_primary}
                                                onChange={() => makePrimary(index)}
                                            />
                                            Referente principale
                                        </label>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => (linkedProjects(contact.id) > 0 ? setConfirmingRemoval(index) : removeContact(index))}
                                            aria-label={`Rimuovi referente ${index + 1}`}
                                        >
                                            <Trash2 /> Rimuovi
                                        </Button>
                                    </div>

                                    {confirmingRemoval === index && (
                                        <div
                                            role="alert"
                                            className="border-destructive/50 bg-destructive/10 grid gap-2 rounded-md border p-3 text-sm"
                                        >
                                            <p>
                                                {contact.name || 'Questo referente'} è collegato a {linkedProjects(contact.id)}{' '}
                                                {linkedProjects(contact.id) === 1 ? 'progetto' : 'progetti'}. Rimuovendolo, il collegamento ai
                                                progetti sparirà.
                                            </p>
                                            <div className="flex justify-end gap-2">
                                                <Button type="button" variant="outline" size="sm" onClick={() => setConfirmingRemoval(null)}>
                                                    Annulla
                                                </Button>
                                                <Button type="button" variant="destructive" size="sm" onClick={() => removeContact(index)}>
                                                    Rimuovi comunque
                                                </Button>
                                            </div>
                                        </div>
                                    )}
                                </fieldset>
                            ))}
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
