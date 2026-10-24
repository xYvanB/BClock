import { type ZodError } from 'zod';

/**
 * Trasforma un errore Zod in un oggetto campo → messaggio (il primo messaggio per campo).
 */
export function flattenZodErrors<T extends string>(error: ZodError): Partial<Record<T, string>> {
    const errors: Partial<Record<T, string>> = {};

    for (const issue of error.issues) {
        const field = String(issue.path[0]) as T;
        if (!(field in errors)) {
            errors[field] = issue.message;
        }
    }

    return errors;
}
