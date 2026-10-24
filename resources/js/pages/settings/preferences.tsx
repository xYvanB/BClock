import { FlashMessage } from '@/components/flash-message';
import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { applyPalette } from '@/lib/palette';
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
    [key: string]: string;
}

interface Props {
    options: { locales: Locale[]; time_formats: TimeFormat[]; palettes: Palette[] };
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
                            <input type="radio" name={name} value={option.value} checked={value === option.value} onChange={() => onChange(option.value)} />
                            <span className="font-medium">{option.label}</span>
                        </span>
                        {option.hint && <span className="text-muted-foreground">{option.hint}</span>}
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
    });

    // Se si esce senza salvare, torna alla palette salvata (letta dal ref: dopo un salvataggio è già quella nuova).
    const savedRef = useRef(saved);
    savedRef.current = saved;
    useEffect(() => () => applyPalette(savedRef.current), []);

    const choosePalette = (palette: Palette) => {
        setData('palette', palette);
        applyPalette(palette);
    };

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        patch(route('preferences.update'), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Preferenze" />

            <SettingsLayout>
                <form onSubmit={submit} className="space-y-8">
                    <HeadingSmall title="Preferenze" description="Lingua, formato degli orari e colori dell'interfaccia" />
                    <FlashMessage />

                    <OptionGroup
                        legend="Lingua"
                        name="locale"
                        value={data.locale}
                        options={options.locales.map((locale) => ({ value: locale, label: localeLabels[locale] }))}
                        onChange={(locale) => setData('locale', locale)}
                    >
                        <p className="text-muted-foreground text-sm">La lingua si salva da subito; i testi inglesi arriveranno con la traduzione dell'interfaccia.</p>
                    </OptionGroup>

                    <OptionGroup
                        legend="Formato orario"
                        name="time_format"
                        value={data.time_format}
                        options={options.time_formats.map((format) => ({ value: format, label: timeFormatLabels[format] }))}
                        onChange={(format) => setData('time_format', format)}
                    >
                        <p className="text-muted-foreground text-sm">Anteprima: {formatTime('14:30', data.time_format)}</p>
                    </OptionGroup>

                    <OptionGroup
                        legend="Palette"
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
                        <p className="text-muted-foreground text-sm">L'anteprima è immediata; si conferma con Salva.</p>
                    </OptionGroup>

                    {(errors.locale || errors.time_format || errors.palette) && (
                        <p role="alert" className="text-destructive text-sm">
                            {errors.locale ?? errors.time_format ?? errors.palette}
                        </p>
                    )}

                    <Button type="submit" disabled={processing}>
                        Salva preferenze
                    </Button>
                </form>
            </SettingsLayout>
        </AppLayout>
    );
}
