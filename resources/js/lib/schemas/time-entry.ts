import { z } from 'zod';

const time = z.string().regex(/^([01]\d|2[0-3]):[0-5]\d$/, 'Orario non valido');

/**
 * Controlli immediati lato client. Il server resta la fonte di verità (SaveTimeEntryRequest).
 */
export const timeEntrySchema = z
    .object({
        activity_id: z.string().min(1, "Scegli un'attività"),
        date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Data non valida'),
        started_at: time,
        ended_at: time,
        description: z.string().max(1000, 'Massimo 1000 caratteri'),
    })
    .refine((entry) => entry.ended_at > entry.started_at, {
        path: ['ended_at'],
        message: "L'ora di fine deve essere successiva all'inizio",
    });

export type TimeEntryFormData = z.infer<typeof timeEntrySchema>;
