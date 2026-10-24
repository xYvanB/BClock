import { z } from 'zod';

/**
 * Immediate client-side checks. The server remains the source of truth (SaveAreaRequest).
 */
export const areaSchema = z.object({
    name: z.string().trim().min(1, 'Il nome è obbligatorio').max(120, 'Massimo 120 caratteri'),
    description: z.string().max(1000, 'Massimo 1000 caratteri'),
});

export type AreaFormData = z.infer<typeof areaSchema>;
