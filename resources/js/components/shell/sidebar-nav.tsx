/**
 * <SidebarNav /> — the app navigation list (desktop sidebar and mobile sheet).
 * Rows 40px, icon 18 + label 16; active = brand-soft surface, 3px brand bar on
 * the left, brand text (docs/DESIGN.md "Shell" 3).
 */
import { Link, usePage } from '@inertiajs/react';
import { navItemsFor } from '@/components/app-nav-items';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';

export function SidebarNav({
    onNavigate,
    className,
}: {
    onNavigate?: () => void;
    className?: string;
}) {
    const { auth } = usePage().props;
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const items = navItemsFor(auth.user?.role);

    return (
        <nav aria-label="Asosiy menyu" className={className}>
            <ul className="flex flex-col gap-1">
                {items.map((item) => {
                    const active = isCurrentOrParentUrl(item.href);
                    const Icon = item.icon;

                    return (
                        <li key={item.title}>
                            <Link
                                href={item.href}
                                prefetch
                                onClick={onNavigate}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'focus-ring relative flex h-10 items-center gap-3 rounded-md px-3 text-base transition-colors',
                                    active
                                        ? 'bg-brand-soft font-medium text-brand before:absolute before:inset-y-2 before:left-0 before:w-[3px] before:rounded-r-sm before:bg-brand'
                                        : 'text-ink-secondary hover:bg-raised hover:text-ink',
                                )}
                            >
                                {Icon && (
                                    <Icon
                                        aria-hidden="true"
                                        strokeWidth={1.5}
                                        className="size-[18px] shrink-0"
                                    />
                                )}
                                <span className="truncate">{item.title}</span>
                            </Link>
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}
