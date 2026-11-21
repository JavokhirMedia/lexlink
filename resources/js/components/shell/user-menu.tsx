/**
 * <UserMenu /> — header account menu: name, role, licence RegistryChip,
 * Sozlamalar, Chiqish. Renders nothing for guests.
 */
import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, LogOut, Settings } from 'lucide-react';
import { LicenseStatus, RoleBadge } from '@/components/brand/role-badge';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useInitials } from '@/hooks/use-initials';
import { logout } from '@/routes';
import { edit as profileEdit } from '@/routes/profile';

export function UserMenu() {
    const { auth } = usePage().props;
    const getInitials = useInitials();
    const user = auth.user;

    if (!user) {
        return null;
    }

    return (
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger
                data-test="sidebar-menu-button"
                className="focus-ring flex h-11 items-center gap-2 rounded-md px-1.5 text-left transition-colors hover:bg-raised data-[state=open]:bg-raised sm:px-2"
            >
                <Avatar className="size-8 rounded-md">
                    <AvatarFallback className="rounded-md bg-raised text-sm font-medium text-ink-secondary">
                        {getInitials(user.name)}
                    </AvatarFallback>
                </Avatar>
                <span className="hidden max-w-48 min-w-0 flex-col md:flex">
                    <span className="truncate text-sm font-medium text-ink">
                        {user.name}
                    </span>
                    {user.role_label && (
                        <span className="truncate text-meta text-ink-secondary">
                            {user.role_label}
                        </span>
                    )}
                </span>
                <ChevronDown
                    aria-hidden="true"
                    strokeWidth={1.5}
                    className="hidden size-4 text-ink-muted md:block"
                />
                <span className="sr-only md:hidden">Hisob menyusi</span>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-72">
                <div className="flex flex-col gap-2 px-2 py-2">
                    <div className="flex flex-col gap-0.5">
                        <span className="text-base font-medium text-ink">
                            {user.name}
                        </span>
                        {(user.email || user.phone) && (
                            <span className="truncate text-meta text-ink-secondary">
                                {user.email || String(user.phone ?? '')}
                            </span>
                        )}
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <RoleBadge role={user.role} label={user.role_label} />
                    </div>
                    {user.license && (
                        <div className="flex flex-col gap-1 pt-1">
                            <span className="text-meta text-ink-secondary">
                                Advokatlik litsenziyasi
                            </span>
                            <LicenseStatus license={user.license} />
                        </div>
                    )}
                </div>
                <DropdownMenuSeparator />
                <DropdownMenuItem asChild>
                    <Link href={profileEdit()} prefetch className="w-full">
                        <Settings strokeWidth={1.5} />
                        Sozlamalar
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link
                        href={logout()}
                        as="button"
                        className="w-full"
                        onClick={() => router.flushAll()}
                        data-test="logout-button"
                    >
                        <LogOut strokeWidth={1.5} />
                        Chiqish
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
