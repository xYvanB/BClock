import { Alert, AlertDescription } from '@/components/ui/alert';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/** Mostra il messaggio di conferma dell'ultima azione (flash di sessione). */
export function FlashMessage() {
    const { flash } = usePage<SharedData>().props;

    if (!flash?.success) {
        return null;
    }

    return (
        <Alert role="status">
            <AlertDescription>✅ {flash.success}</AlertDescription>
        </Alert>
    );
}
