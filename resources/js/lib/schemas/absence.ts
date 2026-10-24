import { z } from 'zod';

const date = z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Data non valida');
const time = z.string().regex(/^([01]\d|2[0-3]):[0-5]\d$/, 'Orario non valido');

/**
 * Immediate client-side checks. The server remains the source of truth (SaveAbsenceRequest).
 * Vacations cover one or more days; a leave is within a single day, with a start and end time.
 */
export const absenceSchema = z
    .object({
        type: z.enum(['ferie', 'permesso'], { message: 'Scegli il tipo' }),
        starts_on: date,
        ends_on: date,
        starts_time: z.string(),
        ends_time: z.string(),
        note: z.string().max(1000, 'Massimo 1000 caratteri'),
    })
    .superRefine((absence, context) => {
        if (absence.type === 'ferie') {
            if (absence.ends_on < absence.starts_on) {
                context.addIssue({ code: 'custom', path: ['ends_on'], message: "La fine non può essere prima dell'inizio" });
            }

            return;
        }

        const start = time.safeParse(absence.starts_time);
        const end = time.safeParse(absence.ends_time);

        if (!start.success) {
            context.addIssue({ code: 'custom', path: ['starts_time'], message: "Indica l'ora di inizio" });
        }

        if (!end.success) {
            context.addIssue({ code: 'custom', path: ['ends_time'], message: "Indica l'ora di fine" });
        }

        if (start.success && end.success && absence.ends_time <= absence.starts_time) {
            context.addIssue({ code: 'custom', path: ['ends_time'], message: "L'ora di fine deve essere successiva all'inizio" });
        }
    });

export type AbsenceFormData = z.infer<typeof absenceSchema>;

export const absenceTypeOptions: { value: string; label: string }[] = [
    { value: 'ferie', label: 'Ferie' },
    { value: 'permesso', label: 'Permesso' },
];

export const absenceTypeLabel = (type: string): string => absenceTypeOptions.find((option) => option.value === type)?.label ?? type;
