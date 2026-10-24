import { z } from 'zod';

/**
 * Controlli immediati lato client. Il server resta la fonte di verità (SaveUserRequest).
 */
export const userSchema = z.object({
    name: z.string().trim().min(1, 'Il nome è obbligatorio').max(120, 'Massimo 120 caratteri'),
    surname: z.string().trim().min(1, 'Il cognome è obbligatorio').max(120, 'Massimo 120 caratteri'),
    email: z.string().trim().min(1, "L'email è obbligatoria").pipe(z.email('Email non valida')),
    role: z.string().min(1, 'Scegli un ruolo'),
    area_id: z.string(),
    daily_capacity: z
        .string()
        .min(1, 'La capacità è obbligatoria')
        .refine((value) => Number(value) >= 0.5 && Number(value) <= 24, 'Tra 0,5 e 24 ore'),
});

export type UserFormData = z.infer<typeof userSchema>;
