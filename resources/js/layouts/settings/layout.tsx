import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        url: '/settings/profile',
        icon: null,
    },
    {
        title: 'Password',
        url: '/settings/password',
        icon: null,
    },
    {
        title: 'Preferenze',
        url: '/settings/preferences',
        icon: null,
    },
    {
        title: 'Notifiche',
        url: '/settings/notifications',
        icon: null,
    },
    {
        title: 'App collegate',
        url: '/settings/connected-apps',
        icon: null,
    },
];

/** Application settings: seen only by the Boss and HR. */
const adminNavItems: NavItem[] = [
    { title: 'Calendario aziendale', url: '/settings/company-calendar', icon: null },
    { title: 'Carico di lavoro', url: '/settings/workload', icon: null },
];

export default function SettingsLayout({ children }: { children: React.ReactNode }) {
    const currentPath = window.location.pathname;
    const { auth } = usePage<SharedData>().props;
    const navItems = ['capo', 'hr'].includes(auth.user.role) ? [...sidebarNavItems, ...adminNavItems] : sidebarNavItems;

    return (
        <div className="px-4 py-6">
            <Heading title={t('Settings')} description={t('Manage your profile and account settings')} />

            <div className="flex flex-col space-y-8 lg:flex-row lg:space-y-0 lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav className="flex flex-col space-y-1 space-x-0">
                        {navItems.map((item) => (
                            <Button
                                key={item.url}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': currentPath === item.url,
                                })}
                            >
                                <Link href={item.url} prefetch>
                                    {t(item.title)}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 md:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">{children}</section>
                </div>
            </div>
        </div>
    );
}
