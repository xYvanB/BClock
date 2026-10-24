import { type WorkCalendar } from '@/types';

/** Monday–Friday, no closures: the starting calendar. */
const defaultCalendar: WorkCalendar = { working_weekdays: [1, 2, 3, 4, 5], closures: [] };

/**
 * Date of working day N from the project start: day 1 is the start date (if it is not a working day,
 * the first one after it). Only working weekdays (1 = Monday … 7 = Sunday) that are not holidays or closures
 * are counted. Mirrors `TemplateActivity::dateOfWorkingDay()` on the server: they must stay equal.
 * Dates are `YYYY-MM-DD` strings and the computation is in UTC so it does not depend on the timezone.
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
