import { z } from 'zod';

const date = z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Data non valida');

/**
 * Immediate client-side checks. The server remains the source of truth (SaveHolidayRequest).
 * `until` is the last day of a closure period (empty = a single day).
 */
export const holidaySchema = z
    .object({
        date,
        name: z.string().trim().min(1, 'Il nome è obbligatorio').max(120, 'Massimo 120 caratteri'),
        kind: z.enum(['festivo', 'chiusura']),
        until: z.string(),
    })
    .refine((holiday) => holiday.until === '' || holiday.until > holiday.date, {
        path: ['until'],
        message: 'La fine deve essere dopo il primo giorno',
    });

export type HolidayFormData = z.infer<typeof holidaySchema>;

export const holidayKindLabels = { festivo: 'Festività', chiusura: 'Chiusura extra' } as const;
