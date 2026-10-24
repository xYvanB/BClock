/**
 * Data del giorno lavorativo N dall'inizio del progetto: il giorno 1 è la data di inizio
 * (se cade di sabato o domenica, il primo lunedì successivo); si contano solo lunedì–venerdì.
 * Replica `TemplateActivity::dateOfWorkingDay()` lato server: vanno tenute uguali.
 * Le date sono stringhe `YYYY-MM-DD` e il calcolo è in UTC per non dipendere dal fuso orario.
 */
export function dateOfWorkingDay(projectStart: string, day: number): string {
    const [year, month, dayOfMonth] = projectStart.split('-').map(Number);
    const date = new Date(Date.UTC(year, month - 1, dayOfMonth));

    const isWeekend = () => date.getUTCDay() === 0 || date.getUTCDay() === 6;
    const nextDay = () => date.setUTCDate(date.getUTCDate() + 1);

    while (isWeekend()) {
        nextDay();
    }

    for (let remaining = day - 1; remaining > 0; remaining--) {
        nextDay();

        while (isWeekend()) {
            nextDay();
        }
    }

    return date.toISOString().slice(0, 10);
}
