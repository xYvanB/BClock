import { FlashMessage } from '@/components/flash-message';
import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { t } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Notifiche', href: '/settings/notifications' }];

interface Preference {
    kind: string;
    label: string;
    mandatory_in_app: boolean;
    app: boolean;
    mail: boolean;
}

interface Props {
    preferences: Preference[];
}

/** For each notification type: receive it in the app and/or by e-mail. Approval requests always stay on in the app. */
export default function NotificationSettings({ preferences }: Props) {
    const { data, setData, put, processing } = useForm<Record<string, { app: boolean; mail: boolean }>>(
        Object.fromEntries(preferences.map((preference) => [preference.kind, { app: preference.app, mail: preference.mail }])),
    );

    const toggle = (kind: string, channel: 'app' | 'mail', value: boolean) => setData(kind, { ...data[kind], [channel]: value });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        put(route('notification-preferences.update'), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Notifiche')} />

            <SettingsLayout>
                <div className="space-y-6">
                    <FlashMessage />
                    <HeadingSmall
                        title={t('Notifiche')}
                        description={t("Scegli quali avvisi ricevere nell'app (campanello) e quali anche per e-mail.")}
                    />

                    <form onSubmit={submit} className="space-y-6" noValidate>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="text-muted-foreground text-left">
                                    <th className="py-2 font-normal">{t('Tipo di evento')}</th>
                                    <th className="w-24 py-2 font-normal">{t("Nell'app")}</th>
                                    <th className="w-24 py-2 font-normal">{t('E-mail')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {preferences.map((preference) => (
                                    <tr key={preference.kind} className="border-t">
                                        <td className="py-3">{t(preference.label)}</td>
                                        <td>
                                            <input
                                                type="checkbox"
                                                aria-label={`${preference.label}: nell'app`}
                                                checked={preference.mandatory_in_app || data[preference.kind].app}
                                                disabled={preference.mandatory_in_app}
                                                onChange={(event) => toggle(preference.kind, 'app', event.target.checked)}
                                            />
                                        </td>
                                        <td>
                                            <input
                                                type="checkbox"
                                                aria-label={`${preference.label}: e-mail`}
                                                checked={data[preference.kind].mail}
                                                onChange={(event) => toggle(preference.kind, 'mail', event.target.checked)}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>

                        <p className="text-muted-foreground text-xs">
                            {t(
                                "Le richieste da approvare arrivano sempre nell'app. Le e-mail partono con il servizio di posta configurato nel file .env (MAIL_MAILER e relative voci) e, con la coda attiva, serve `php artisan queue:work`.",
                            )}
                        </p>

                        <Button type="submit" disabled={processing}>
                            {t('Salva')}
                        </Button>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
