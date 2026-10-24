import { FlashMessage } from '@/components/flash-message';
import HeadingSmall from '@/components/heading-small';
import { HolidayFormSheet } from '@/components/holiday-form-sheet';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { holidayKindLabels, type HolidayFormData } from '@/lib/schemas/holiday';
import { type BreadcrumbItem, type Holiday } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Calendario aziendale', href: '/settings/company-calendar' }];

const weekdayLabels = ['Lunedì', 'Martedì', 'Mercoledì', 'Giovedì', 'Venerdì', 'Sabato', 'Domenica'];

interface Props {
    weekdays: number[];
    closures: Holiday[];
}

const formatDate = (iso: string) => iso.split('-').reverse().join('/');

/** Company calendar (Boss and HR only): working weekdays, holidays and extra closures. */
export default function CompanyCalendar({ weekdays, closures }: Props) {
    const { data, setData, put, processing, errors } = useForm<{ weekdays: number[] }>({ weekdays });
    const [sheetOpen, setSheetOpen] = useState(false);
    const [editing, setEditing] = useState<Holiday | null>(null);
    const [kind, setKind] = useState<HolidayFormData['kind']>('festivo');
    const [confirmingDelete, setConfirmingDelete] = useState<number | null>(null);

    const toggleDay = (day: number) =>
        setData('weekdays', data.weekdays.includes(day) ? data.weekdays.filter((current) => current !== day) : [...data.weekdays, day].sort());

    const save: FormEventHandler = (event) => {
        event.preventDefault();
        put(route('company-calendar.update'), { preserveScroll: true });
    };

    const open = (holiday: Holiday | null, newKind: HolidayFormData['kind'] = 'festivo') => {
        setEditing(holiday);
        setKind(newKind);
        setSheetOpen(true);
    };

    const remove = (id: number) => router.delete(route('holidays.destroy', id), { preserveScroll: true, onFinish: () => setConfirmingDelete(null) });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Calendario aziendale" />

            <SettingsLayout>
                <div className="space-y-10">
                    <FlashMessage />

                    <form onSubmit={save} className="space-y-4" noValidate>
                        <HeadingSmall
                            title="Giorni lavorativi"
                            description="I giorni della settimana in cui si lavora. Gli altri sono non lavorativi: niente quote, niente ore previste e non si possono chiedere assenze."
                        />
                        <fieldset className="flex flex-wrap gap-3">
                            <legend className="sr-only">Giorni lavorativi</legend>
                            {weekdayLabels.map((label, index) => (
                                <label
                                    key={label}
                                    className="border-input flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm"
                                >
                                    <input type="checkbox" checked={data.weekdays.includes(index + 1)} onChange={() => toggleDay(index + 1)} />
                                    {label}
                                </label>
                            ))}
                        </fieldset>
                        <InputError message={errors.weekdays} />
                        <p className="text-muted-foreground text-sm">Salvando si ricalcolano le quote giornaliere di tutte le attività aperte.</p>
                        <Button type="submit" disabled={processing || data.weekdays.length === 0}>
                            Salva i giorni lavorativi
                        </Button>
                    </form>

                    <section className="space-y-4">
                        <HeadingSmall
                            title="Festività e chiusure"
                            description="Giorni in cui l'azienda è chiusa: festività del calendario e chiusure extra, come i ponti."
                        />
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" size="sm" onClick={() => open(null, 'festivo')}>
                                <Plus /> Nuova festività
                            </Button>
                            <Button variant="outline" size="sm" onClick={() => open(null, 'chiusura')}>
                                <Plus /> Nuova chiusura extra
                            </Button>
                        </div>

                        {closures.length === 0 ? (
                            <p className="text-muted-foreground border-sidebar-border/70 rounded-md border border-dashed p-6 text-center text-sm">
                                Nessuna festività o chiusura inserita
                            </p>
                        ) : (
                            <div className="border-sidebar-border/70 overflow-x-auto rounded-xl border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">Data</th>
                                            <th className="px-4 py-3 font-medium">Nome</th>
                                            <th className="px-4 py-3 font-medium">Tipo</th>
                                            <th className="px-4 py-3" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {closures.map((closure) => (
                                            <tr key={closure.id} className="border-sidebar-border/70 border-t">
                                                <td className="px-4 py-3 whitespace-nowrap">{formatDate(closure.date)}</td>
                                                <td className="px-4 py-3 font-medium">{closure.name}</td>
                                                <td className="px-4 py-3">
                                                    <Badge variant={closure.kind === 'chiusura' ? 'secondary' : 'outline'}>
                                                        {holidayKindLabels[closure.kind]}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3 text-right whitespace-nowrap">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => open(closure)}
                                                        aria-label={`Modifica ${closure.name}`}
                                                    >
                                                        <Pencil /> Modifica
                                                    </Button>
                                                    {confirmingDelete === closure.id ? (
                                                        <span className="inline-flex items-center gap-1">
                                                            <span className="text-sm">Eliminare?</span>
                                                            <Button variant="destructive" size="sm" onClick={() => remove(closure.id)}>
                                                                Elimina
                                                            </Button>
                                                            <Button variant="outline" size="sm" onClick={() => setConfirmingDelete(null)}>
                                                                Annulla
                                                            </Button>
                                                        </span>
                                                    ) : (
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => setConfirmingDelete(closure.id)}
                                                            aria-label={`Elimina ${closure.name}`}
                                                        >
                                                            <Trash2 /> Elimina
                                                        </Button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                </div>
            </SettingsLayout>

            <HolidayFormSheet open={sheetOpen} holiday={editing} defaultKind={kind} onClose={() => setSheetOpen(false)} />
        </AppLayout>
    );
}
