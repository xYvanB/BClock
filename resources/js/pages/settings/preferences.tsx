import { FlashMessage } from '@/components/flash-message';
import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { t } from '@/lib/i18n';
import { applyPalette, contrastRatio, customColorFields, type CustomColors } from '@/lib/palette';
import { formatTime } from '@/lib/time';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Locale, type Palette, type SharedData, type TimeFormat } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useEffect, useRef } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Preferenze', href: '/settings/preferences' }];

const localeLabels: Record<Locale, string> = { it: 'Italiano (IT)', en: 'English (EN)' };
const timeFormatLabels: Record<TimeFormat, string> = { '24h': '24 ore', '12h': '12 ore (AM/PM)' };
const paletteLabels: Record<Palette, { name: string; hint: string; swatches: [string, string, string] }> = {
    wireframe: { name: 'Wireframe', hint: 'Scala di grigi', swatches: ['#ffffff', '#858585', '#222222'] },
    scuro: { name: 'BClock scuro', hint: 'Scuro con accenti oro', swatches: ['#0a0a0f', '#1d1d33', '#c9a84c'] },
    chiaro: { name: 'BClock chiaro', hint: 'Chiaro con accenti oro', swatches: ['#faf8f3', '#ffffff', '#7d6219'] },
    personalizzata: { name: 'Personalizzata', hint: 'Parte come lo scuro, colori modificabili', swatches: ['#0a0a0f', '#26264a', '#e8c97a'] },
};

interface PreferencesForm {
    locale: Locale;
    time_format: TimeFormat;
    palette: Palette;
    custom_colors: CustomColors;
    [key: string]: string | CustomColors;
}

interface Props {
    options: { locales: Locale[]; time_formats: TimeFormat[]; palettes: Palette[]; custom_defaults: CustomColors };
}

function OptionGroup<T extends string>({
    legend,
    name,
    value,
    options,
    onChange,
    children,
    className,
}: {
    legend: string;
    name: string;
    value: T;
    options: { value: T; label: string; hint?: string; extra?: React.ReactNode }[];
    onChange: (next: T) => void;
    children?: React.ReactNode;
    className?: string;
}) {
    return (
        <fieldset className="space-y-3">
            <legend className="text-sm font-medium">{legend}</legend>
            <div className={cn('flex flex-wrap gap-3', className)}>
                {options.map((option) => (
                    <label
                        key={option.value}
                        className={cn(
                            'focus-within:ring-ring flex cursor-pointer flex-col gap-1 rounded-lg border p-3 text-sm focus-within:ring-2',
                            value === option.value ? 'border-primary bg-accent' : 'border-input',
                        )}
                    >
                        <span className="flex items-center gap-2">
                            <input
                                type="radio"
                                name={name}
                                value={option.value}
                                checked={value === option.value}
                                onChange={() => onChange(option.value)}
                            />
                            <span className="font-medium">{t(option.label)}</span>
                        </span>
                        {option.hint && <span className="text-muted-foreground">{t(option.hint)}</span>}
                        {option.extra}
                    </label>
                ))}
            </div>
            {children}
        </fieldset>
    );
}

/** Preferenze personali (F14): lingua, formato orario e palette dell'interfaccia. */
export default function Preferences({ options }: Props) {
    const { auth } = usePage<SharedData>().props;
    const saved = auth.user.palette;

    const { data, setData, patch, processing, errors } = useForm<PreferencesForm>({
        locale: auth.user.locale,
        time_format: auth.user.time_format,
        palette: auth.user.palette,
        custom_colors: { ...options.custom_defaults, ...(auth.user.custom_colors ?? {}) },
    });

    // Leaving without saving goes back to the saved palette (read from the ref: after a save it is already the new one).
    const savedRef = useRef({ palette: saved, colors: auth.user.custom_colors });
    savedRef.current = { palette: saved, colors: auth.user.custom_colors };
    useEffect(() => () => applyPalette(savedRef.current.palette, savedRef.current.colors), []);

    const choosePalette = (palette: Palette) => {
        setData('palette', palette);
        applyPalette(palette, data.custom_colors);
    };

    const chooseColor = (key: string, value: string) => {
        const colors = { ...data.custom_colors, [key]: value };
        setData('custom_colors', colors);
        applyPalette('personalizzata', colors);
    };

    const resetColors = () => {
        setData('custom_colors', options.custom_defaults);
        applyPalette('personalizzata', options.custom_defaults);
    };

    const textContrast = contrastRatio(data.custom_colors.text, data.custom_colors.bg);

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        patch(route('preferences.update'), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('Preferenze')} />

            <SettingsLayout>
                <form onSubmit={submit} className="space-y-8">
                    <HeadingSmall title={t('Preferenze')} description={t("Lingua, formato degli orari e colori dell'interfaccia")} />
                    <FlashMessage />

                    <OptionGroup
                        legend={t('Lingua')}
                        name="locale"
                        value={data.locale}
                        options={options.locales.map((locale) => ({ value: locale, label: localeLabels[locale] }))}
                        onChange={(locale) => setData('locale', locale)}
                    >
                        <p className="text-muted-foreground text-sm">{t('La lingua si applica subito.')}</p>
                    </OptionGroup>

                    <OptionGroup
                        legend={t('Formato orario')}
                        name="time_format"
                        value={data.time_format}
                        options={options.time_formats.map((format) => ({ value: format, label: timeFormatLabels[format] }))}
                        onChange={(format) => setData('time_format', format)}
                    >
                        <p className="text-muted-foreground text-sm">{t('Anteprima: :value', { value: formatTime('14:30', data.time_format) })}</p>
                    </OptionGroup>

                    <OptionGroup
                        legend={t('Palette')}
                        name="palette"
                        value={data.palette}
                        options={options.palettes.map((palette) => ({
                            value: palette,
                            label: paletteLabels[palette].name,
                            hint: paletteLabels[palette].hint,
                            extra: (
                                <span className="mt-1 flex gap-1" aria-hidden="true">
                                    {paletteLabels[palette].swatches.map((color) => (
                                        <span key={color} className="border-input h-5 w-5 rounded border" style={{ backgroundColor: color }} />
                                    ))}
                                </span>
                            ),
                        }))}
                        onChange={choosePalette}
                    >
                        <p className="text-muted-foreground text-sm">{t("L'anteprima è immediata; si conferma con Salva.")}</p>
                    </OptionGroup>

                    {data.palette === 'personalizzata' && (
                        <fieldset className="space-y-3">
                            <legend className="text-sm font-medium">{t('Colori della palette personalizzata')}</legend>
                            <div className="grid gap-3 sm:grid-cols-2">
                                {customColorFields.map((field) => (
                                    <label key={field.key} className="border-input flex items-center gap-3 rounded-lg border p-2 text-sm">
                                        <input
                                            type="color"
                                            value={data.custom_colors[field.key]}
                                            onChange={(event) => chooseColor(field.key, event.target.value)}
                                            className="h-9 w-12 shrink-0 cursor-pointer rounded border-0 bg-transparent p-0"
                                        />
                                        <span className="grid">
                                            <span className="font-medium">{t(field.label)}</span>
                                            <span className="text-muted-foreground">
                                                {t(field.hint)} · {data.custom_colors[field.key]}
                                            </span>
                                        </span>
                                    </label>
                                ))}
                            </div>
                            {textContrast < 4.5 && (
                                <p role="alert" className="text-destructive text-sm">
                                    {t('Il testo e lo sfondo hanno poco contrasto (:ratio:1): il testo si leggerà male. Meglio almeno 4,5:1.', {
                                        ratio: textContrast.toFixed(1),
                                    })}
                                </p>
                            )}
                            <Button type="button" variant="outline" size="sm" onClick={resetColors}>
                                {t('Ripristina i colori di partenza')}
                            </Button>
                        </fieldset>
                    )}

                    {(errors.locale || errors.time_format || errors.palette) && (
                        <p role="alert" className="text-destructive text-sm">
                            {errors.locale ?? errors.time_format ?? errors.palette}
                        </p>
                    )}

                    <Button type="submit" disabled={processing}>
                        {t('Salva preferenze')}
                    </Button>
                </form>
            </SettingsLayout>
        </AppLayout>
    );
}
