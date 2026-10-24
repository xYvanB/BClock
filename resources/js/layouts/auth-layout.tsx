import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';
import { applyPalette } from '@/lib/palette';
import { useEffect } from 'react';

export default function AuthLayout({ children, title, description, ...props }: { children: React.ReactNode; title: string; description: string }) {
    // Public pages (login, invitation) always use the default palette.
    useEffect(() => applyPalette('scuro'), []);

    return (
        <AuthLayoutTemplate title={title} description={description} {...props}>
            {children}
        </AuthLayoutTemplate>
    );
}
