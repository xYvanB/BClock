import { z } from 'zod';

const date = z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Data non valida');

/**
 * Controlli immediati lato client. Il server resta la fonte di verità (SaveAbsenceRequest).
 */
export const absenceSchema = z
    .object({
        type: z.enum(['ferie', 'permesso'], { message: 'Scegli il tipo' }),
        starts_on: date,
        ends_on: date,
        note: z.string().max(1000, 'Massimo 1000 caratteri'),
    })
    .refine((absence) => absence.ends_on >= absence.starts_on, {
        path: ['ends_on'],
        message: "La fine non può essere prima dell'inizio",
    });

export type AbsenceFormData = z.infer<typeof absenceSchema>;

export const absenceTypeOptions: { value: string; label: string }[] = [
    { value: 'ferie', label: 'Ferie' },
    { value: 'permesso', label: 'Permesso' },
];

export const absenceTypeLabel = (type: string): string => absenceTypeOptions.find((option) => option.value === type)?.label ?? type;
