import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { t } from '@/lib/i18n';
import { userSchema, type UserFormData } from '@/lib/schemas/user';
import { flattenZodErrors } from '@/lib/zod-errors';
import { type AreaOption, type ManagedUser, type Role } from '@/types';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useEffect } from 'react';

export const roleLabels: Record<Role, string> = {
    capo: 'Capo',
    capo_area: 'Capo area',
    hr: 'HR',
    dipendente: 'Dipendente',
};

interface UserFormSheetProps {
    open: boolean;
    user: ManagedUser | null;
    areas: AreaOption[];
    assignableRoles: Role[];
    /** For the area head: the area is fixed and cannot be changed. */
    fixedAreaId: number | null;
    onClose: () => void;
}

const fieldClass =
    'border-input bg-background focus-visible:ring-ring h-9 w-full rounded-md border px-3 text-sm focus-visible:ring-1 focus-visible:outline-none disabled:opacity-60';

/** Side drawer to invite a user (F2) or edit their data. */
export function UserFormSheet({ open, user, areas, assignableRoles, fixedAreaId, onClose }: UserFormSheetProps) {
    const { data, setData, post, put, processing, errors, setError, clearErrors, reset } = useForm<UserFormData>({
        name: '',
        surname: '',
        email: '',
        role: assignableRoles[0] ?? '',
        area_id: fixedAreaId ? String(fixedAreaId) : '',
        daily_capacity: '8',
    });

    useEffect(() => {
        if (open) {
            clearErrors();
            setData({
                name: user?.name ?? '',
                surname: user?.surname ?? '',
                email: user?.email ?? '',
                role: user?.role ?? assignableRoles[0] ?? '',
                area_id: fixedAreaId ? String(fixedAreaId) : user?.area_id ? String(user.area_id) : '',
                daily_capacity: user ? String(user.daily_capacity) : '8',
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, user]);

    const submit: FormEventHandler = (event) => {
        event.preventDefault();

        const result = userSchema.safeParse(data);
        if (!result.success) {
            const fieldErrors = flattenZodErrors<keyof UserFormData>(result.error);
            (Object.keys(fieldErrors) as (keyof UserFormData)[]).forEach((field) => setError(field, fieldErrors[field] as string));

            return;
        }

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        };

        if (user) {
            put(route('users.update', user.id), options);
        } else {
            post(route('users.store'), options);
        }
    };

    return (
        <Sheet open={open} onOpenChange={(next) => !next && onClose()}>
            <SheetContent side="right" className="w-full sm:max-w-md">
                <form onSubmit={submit} className="flex h-full flex-col gap-6" noValidate>
                    <SheetHeader>
                        <SheetTitle>{user ? t('Modifica utente') : t('Nuovo utente')}</SheetTitle>
                        <SheetDescription>
                            {user ? t('Aggiorna i dati della persona.') : t("Riceverà un'email con il link per scegliere la password.")}
                        </SheetDescription>
                    </SheetHeader>

                    <div className="flex flex-1 flex-col gap-4 overflow-y-auto px-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="user-name">{t('Nome')}</Label>
                                <Input id="user-name" value={data.name} onChange={(event) => setData('name', event.target.value)} />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="user-surname">{t('Cognome')}</Label>
                                <Input id="user-surname" value={data.surname} onChange={(event) => setData('surname', event.target.value)} />
                                <InputError message={errors.surname} />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="user-email">{t('Email')}</Label>
                            <Input id="user-email" type="email" value={data.email} onChange={(event) => setData('email', event.target.value)} />
                            <InputError message={errors.email} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="user-role">{t('Ruolo')}</Label>
                            <select id="user-role" className={fieldClass} value={data.role} onChange={(event) => setData('role', event.target.value)}>
                                {(user?.is_self ? [user.role] : assignableRoles).map((role) => (
                                    <option key={role} value={role}>
                                        {t(roleLabels[role])}
                                    </option>
                                ))}
                            </select>
                            {user?.is_self && <p className="text-muted-foreground text-sm">{t('Non puoi cambiare il tuo ruolo.')}</p>}
                            <InputError message={errors.role} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="user-area">{t('Area')}</Label>
                            <select
                                id="user-area"
                                className={fieldClass}
                                value={data.area_id}
                                disabled={fixedAreaId !== null}
                                onChange={(event) => setData('area_id', event.target.value)}
                            >
                                <option value="">{t('Nessuna')}</option>
                                {areas.map((area) => (
                                    <option key={area.id} value={area.id}>
                                        {area.name}
                                    </option>
                                ))}
                            </select>
                            {fixedAreaId !== null && (
                                <p className="text-muted-foreground text-sm">{t("L'area è quella del tuo team e non si può cambiare.")}</p>
                            )}
                            <InputError message={errors.area_id} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="user-capacity">{t('Capacità giornaliera (ore)')}</Label>
                            <Input
                                id="user-capacity"
                                type="number"
                                step="0.5"
                                min="0.5"
                                max="24"
                                value={data.daily_capacity}
                                onChange={(event) => setData('daily_capacity', event.target.value)}
                            />
                            <InputError message={errors.daily_capacity} />
                        </div>
                    </div>

                    <SheetFooter className="flex-row justify-end gap-2">
                        <Button type="button" variant="outline" onClick={onClose}>
                            {t('Annulla')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {user ? t('Salva') : t('Invia invito')}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
