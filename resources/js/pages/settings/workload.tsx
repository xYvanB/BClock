import { FlashMessage } from '@/components/flash-message';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { t } from '@/lib/i18n';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Carico di lavoro', href: '/settings/workload' }];

interface Props {
    tolerance_enabled: boolean;
    tolerance_hours: number;
}

/** Workload settings (Boss and HR only): tolerance of the "full" level in the calendar. */
export default function WorkloadSettings({ tolerance_enabled, tolerance_hours }: Props) {
    const { data, setData, patch, processing, errors } = useForm({
        tolerance_enabled,
        tolerance_hours: String(tolerance_hours),
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        patch(route('workload.update'), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Carico di lavoro')} />

            <SettingsLayout>
                <div className="space-y-6">
                    <FlashMessage />
                    <HeadingSmall
                        title={t('Carico di lavoro')}
                        description={t(
                            'Un giorno è "pieno" quando le ore assegnate coincidono con quelle disponibili. Con la tolleranza attiva basta esserci vicini.',
                        )}
                    />

                    <form onSubmit={submit} className="space-y-6" noValidate>
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={data.tolerance_enabled}
                                onChange={(event) => setData('tolerance_enabled', event.target.checked)}
                            />
                            <span className="font-medium">{t('Attiva la tolleranza sul livello "pieno"')}</span>
                        </label>
                        <InputError message={errors.tolerance_enabled} />

                        <div className="grid max-w-xs gap-2">
                            <Label htmlFor="tolerance-hours">{t('Scarto massimo (ore)')}</Label>
                            <Input
                                id="tolerance-hours"
                                type="number"
                                step="0.25"
                                min="0.25"
                                max="4"
                                value={data.tolerance_hours}
                                disabled={!data.tolerance_enabled}
                                onChange={(event) => setData('tolerance_hours', event.target.value)}
                            />
                            <p className="text-muted-foreground text-sm">
                                {t('Con 0,5 ore, su una giornata da 8 ore sono "piene" quelle da 7,5 a 8,5 ore assegnate.')}
                            </p>
                            <InputError message={errors.tolerance_hours} />
                        </div>

                        <Button type="submit" disabled={processing}>
                            {t('Salva')}
                        </Button>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
