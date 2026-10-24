import { Alert, AlertDescription } from '@/components/ui/alert';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/** Shows the confirmation message of the last action (session flash). */
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
