import { applyPalette } from '@/lib/palette';
import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';
import { useEffect } from 'react';

export default function AuthLayout({ children, title, description, ...props }: { children: React.ReactNode; title: string; description: string }) {
    // Le pagine pubbliche (login, invito) usano sempre la palette predefinita.
    useEffect(() => applyPalette('scuro'), []);

    return (
        <AuthLayoutTemplate title={title} description={description} {...props}>
            {children}
        </AuthLayoutTemplate>
    );
}
