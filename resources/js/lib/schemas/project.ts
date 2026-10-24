import { z } from 'zod';

const date = z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Data non valida');

/**
 * Immediate client-side checks. The server remains the source of truth (SaveProjectRequest).
 */
export const projectSchema = z
    .object({
        client_id: z.string().min(1, 'Scegli un cliente'),
        name: z.string().trim().min(1, 'Il nome è obbligatorio').max(150, 'Massimo 150 caratteri'),
        description: z.string().max(2000, 'Massimo 2000 caratteri'),
        starts_on: date,
        due_on: date,
        planned_hours: z.string().refine((value) => value !== '' && Number(value) >= 0, 'Inserisci le ore previste'),
        status: z.string().min(1),
        member_ids: z.array(z.number()),
        contact_ids: z.array(z.number()),
        template_id: z.string(),
        template_assignees: z.record(z.string(), z.array(z.number())),
    })
    .refine((project) => project.due_on >= project.starts_on, {
        path: ['due_on'],
        message: "La scadenza non può precedere l'inizio",
    });

export type ProjectFormData = z.infer<typeof projectSchema>;
