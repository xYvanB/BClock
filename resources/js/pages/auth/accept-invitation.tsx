import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';
import { formatTime } from '@/lib/time';
import { type Locale, type TimeFormat } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

type InvitationForm = {
    password: string;
    password_confirmation: string;
    locale: Locale;
    time_format: TimeFormat;
};

interface Props {
    invitee: { id: number; name: string; email: string };
    options: { locales: Locale[]; time_formats: TimeFormat[] };
}

const localeLabels: Record<Locale, string> = { it: 'Italiano (IT)', en: 'English (EN)' };
const timeFormatLabels: Record<TimeFormat, string> = { '24h': '24 ore', '12h': '12 ore (AM/PM)' };

const fieldClass = 'border-input bg-background focus-visible:ring-ring h-9 w-full rounded-md border px-3 text-sm focus-visible:ring-1 focus-visible:outline-none';

/** First access from an invitation (F2): choice of the password and preferences. */
export default function AcceptInvitation({ invitee, options }: Props) {
    const { data, setData, post, processing, errors } = useForm<InvitationForm>({
        password: '',
        password_confirmation: '',
        locale: 'it',
        time_format: '24h',
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        // The signed URL (with expiry and signature) is the current page's.
        post(window.location.href);
    };

    return (
        <AuthLayout title={`Benvenuto, ${invitee.name}`} description="Scegli la password e le tue preferenze per iniziare">
            <Head title="Primo accesso" />

            <form className="flex flex-col gap-6" onSubmit={submit}>
                <div className="grid gap-6">
                    <div className="grid gap-2">
                        <Label>Email</Label>
                        <p className="text-muted-foreground text-sm">{invitee.email}</p>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">Password</Label>
                        <Input
                            id="password"
                            type="password"
                            required
                            autoFocus
                            autoComplete="new-password"
                            value={data.password}
                            onChange={(event) => setData('password', event.target.value)}
                        />
                        <InputError message={errors.password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">Conferma password</Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            required
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={(event) => setData('password_confirmation', event.target.value)}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="locale">Lingua</Label>
                        <select id="locale" className={fieldClass} value={data.locale} onChange={(event) => setData('locale', event.target.value as Locale)}>
                            {options.locales.map((locale) => (
                                <option key={locale} value={locale}>
                                    {localeLabels[locale]}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.locale} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="time_format">Formato orario</Label>
                        <select
                            id="time_format"
                            className={fieldClass}
                            value={data.time_format}
                            onChange={(event) => setData('time_format', event.target.value as TimeFormat)}
                        >
                            {options.time_formats.map((format) => (
                                <option key={format} value={format}>
                                    {timeFormatLabels[format]}
                                </option>
                            ))}
                        </select>
                        <p className="text-muted-foreground text-sm">Anteprima: {formatTime('14:30', data.time_format)}</p>
                        <InputError message={errors.time_format} />
                    </div>

                    <Button type="submit" className="w-full" disabled={processing}>
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        Inizia
                    </Button>
                </div>
            </form>
        </AuthLayout>
    );
}
