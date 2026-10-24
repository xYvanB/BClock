import { z } from 'zod';

const date = z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Data non valida');

/**
 * Controlli immediati lato client. Il server resta la fonte di verità (SaveActivityRequest),
 * compreso il limite della scadenza del progetto.
 */
export const activitySchema = z
    .object({
        project_id: z.string().min(1, 'Scegli un progetto'),
        name: z.string().trim().min(1, 'Il nome è obbligatorio').max(150, 'Massimo 150 caratteri'),
        description: z.string().max(2000, 'Massimo 2000 caratteri'),
        starts_on: date,
        due_on: date,
        planned_hours: z.string().refine((value) => value !== '' && Number(value) >= 0, 'Inserisci le ore previste'),
        status: z.string().min(1),
        assignee_ids: z.array(z.number()).min(1, 'Scegli almeno una persona'),
    })
    .refine((activity) => activity.due_on >= activity.starts_on, {
        path: ['due_on'],
        message: "La scadenza non può precedere l'inizio",
    });

export type ActivityFormData = z.infer<typeof activitySchema>;
