import { z } from 'zod';

const day = z.string().refine((value) => /^\d+$/.test(value) && Number(value) >= 1 && Number(value) <= 3650, 'Un giorno da 1 a 3650');

export const templateActivitySchema = z
    .object({
        name: z.string().trim().min(1, 'Il nome è obbligatorio').max(150, 'Massimo 150 caratteri'),
        description: z.string().max(2000, 'Massimo 2000 caratteri'),
        planned_hours: z.string().refine((value) => value !== '' && Number(value) >= 0, 'Inserisci le ore previste'),
        start_day: day,
        end_day: day,
        suggested_area_id: z.string(),
        suggested_role: z.string(),
    })
    .refine(
        (activity) => !/^\d+$/.test(activity.start_day) || !/^\d+$/.test(activity.end_day) || Number(activity.end_day) >= Number(activity.start_day),
        {
            path: ['end_day'],
            message: "L'ultimo giorno non può precedere il primo",
        },
    );

/**
 * Immediate client-side checks. The server remains the source of truth (SaveProjectTemplateRequest).
 */
export const templateSchema = z.object({
    name: z.string().trim().min(1, 'Il nome è obbligatorio').max(150, 'Massimo 150 caratteri'),
    activities: z.array(templateActivitySchema).min(1, 'Aggiungi almeno un’attività'),
});

export type TemplateActivityFormData = z.infer<typeof templateActivitySchema>;
export type TemplateFormData = z.infer<typeof templateSchema>;
