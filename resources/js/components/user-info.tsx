import { RoleBadge } from '@/components/brand/role-badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import type { User } from '@/types';
import type { UserRole } from '@/types/lexlink';

export function UserInfo({
    user,
    showEmail = false,
}: {
    user: User;
    showEmail?: boolean;
}) {
    const getInitials = useInitials();
    const role = (user.role ?? null) as UserRole | null;
    const roleLabel = (user.role_label ?? null) as string | null;
    const contact =
        user.email || (typeof user.phone === 'string' ? user.phone : '');

    return (
        <>
            <Avatar className="h-8 w-8 overflow-hidden rounded-md">
                <AvatarImage src={user.avatar} alt={user.name} />
                <AvatarFallback className="rounded-md bg-navy-soft text-xs font-semibold text-navy">
                    {getInitials(user.name)}
                </AvatarFallback>
            </Avatar>
            <div className="grid flex-1 gap-0.5 text-left text-sm leading-tight">
                <span className="flex items-center gap-1.5 truncate font-medium">
                    {user.name}
                    {showEmail && <RoleBadge role={role} label={roleLabel} />}
                </span>
                {showEmail && contact && (
                    <span className="truncate text-xs text-muted-foreground">
                        {contact}
                    </span>
                )}
            </div>
        </>
    );
}
