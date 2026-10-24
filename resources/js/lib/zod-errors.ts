import { type ZodError } from 'zod';

/**
 * Trasforma un errore Zod in un oggetto percorso → messaggio (il primo messaggio per percorso).
 * I percorsi annidati usano la stessa notazione degli errori del server, es. `contacts.0.email`.
 */
export function flattenZodErrors<T extends string>(error: ZodError): Partial<Record<T, string>> {
    const errors: Partial<Record<T, string>> = {};

    for (const issue of error.issues) {
        const field = issue.path.map(String).join('.') as T;
        if (!(field in errors)) {
            errors[field] = issue.message;
        }
    }

    return errors;
}
