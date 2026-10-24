import { type WorkCalendar } from '@/types';

/** Lunedì–venerdì, senza chiusure: il calendario di partenza. */
const defaultCalendar: WorkCalendar = { working_weekdays: [1, 2, 3, 4, 5], closures: [] };

/**
 * Data del giorno lavorativo N dall'inizio del progetto: il giorno 1 è la data di inizio (se non è un giorno
 * lavorativo, il primo successivo). Si contano solo i giorni della settimana lavorativi (1 = lunedì … 7 = domenica)
 * che non siano festività o chiusure. Replica `TemplateActivity::dateOfWorkingDay()` lato server: vanno tenute uguali.
 * Le date sono stringhe `YYYY-MM-DD` e il calcolo è in UTC per non dipendere dal fuso orario.
 */
export function dateOfWorkingDay(projectStart: string, day: number, calendar: WorkCalendar = defaultCalendar): string {
    const [year, month, dayOfMonth] = projectStart.split('-').map(Number);
    const date = new Date(Date.UTC(year, month - 1, dayOfMonth));

    const isOff = () => {
        const isoWeekday = date.getUTCDay() === 0 ? 7 : date.getUTCDay();

        return !calendar.working_weekdays.includes(isoWeekday) || calendar.closures.includes(date.toISOString().slice(0, 10));
    };
    const nextDay = () => date.setUTCDate(date.getUTCDate() + 1);

    while (isOff()) {
        nextDay();
    }

    for (let remaining = day - 1; remaining > 0; remaining--) {
        nextDay();

        while (isOff()) {
            nextDay();
        }
    }

    return date.toISOString().slice(0, 10);
}
