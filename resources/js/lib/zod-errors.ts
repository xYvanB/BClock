import { type ZodError } from 'zod';

/**
 * Turns a Zod error into a path → message object (the first message per path).
 * Nested paths use the same notation as the server's errors, e.g. `contacts.0.email`.
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
