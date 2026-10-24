import { z } from 'zod';

/**
 * Immediate client-side checks. The server remains the source of truth (SaveClientRequest).
 */
export const clientContactSchema = z.object({
    id: z.number().nullable(),
    name: z.string().trim().min(1, 'Il nome è obbligatorio').max(120, 'Massimo 120 caratteri'),
    email: z.string().trim().max(255, 'Massimo 255 caratteri').email('Email non valida').or(z.literal('')),
    phone: z.string().trim().max(40, 'Massimo 40 caratteri'),
    is_primary: z.boolean(),
});

export const clientSchema = z.object({
    company_name: z.string().trim().min(1, 'La ragione sociale è obbligatoria').max(150, 'Massimo 150 caratteri'),
    contacts: z.array(clientContactSchema),
});

export type ClientContactFormData = z.infer<typeof clientContactSchema>;
export type ClientFormData = z.infer<typeof clientSchema>;
