import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { PageHeader } from '@/components/brand/page-header';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit } from '@/routes/profile';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profil',
        href: edit(),
        icon: null,
    },
];

/** Nested inside the app shell: page header + a narrow settings sub-nav. */
export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="flex flex-col gap-8">
            <PageHeader
                title="Sozlamalar"
                description="Profil va hisob sozlamalari"
            />

            <div className="flex flex-col gap-8 lg:flex-row lg:gap-12">
                <aside className="w-full lg:w-48 lg:shrink-0">
                    <nav className="flex flex-col gap-1" aria-label="Sozlamalar">
                        {sidebarNavItems.map((item, index) => {
                            const active = isCurrentOrParentUrl(item.href);

                            return (
                                <Link
                                    key={`${toUrl(item.href)}-${index}`}
                                    href={item.href}
                                    aria-current={active ? 'page' : undefined}
                                    className={cn(
                                        'focus-ring flex h-10 items-center rounded-md px-3 text-base transition-colors',
                                        active
                                            ? 'bg-raised font-medium text-ink'
                                            : 'text-ink-secondary hover:bg-raised hover:text-ink',
                                    )}
                                >
                                    {item.title}
                                </Link>
                            );
                        })}
                    </nav>
                </aside>

                <div className="min-w-0 flex-1 md:max-w-2xl">
                    <section className="flex max-w-xl flex-col gap-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
