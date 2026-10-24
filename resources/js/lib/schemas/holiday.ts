import { z } from 'zod';

/**
 * Controlli immediati lato client. Il server resta la fonte di verità (SaveHolidayRequest).
 */
export const holidaySchema = z.object({
    date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Data non valida'),
    name: z.string().trim().min(1, 'Il nome è obbligatorio').max(120, 'Massimo 120 caratteri'),
});

export type HolidayFormData = z.infer<typeof holidaySchema>;
