/**
 * <RoleBadge role="advocate" />  -> "Advokat"
 * <LicenseStatus license={auth.user.license} /> -> "AL-0123 · tasdiqlangan"
 *
 * RoleBadge props:     role: UserRole | null, label?: string (backend role_label),
 *                      variant?: 'light' | 'dark', className?
 * LicenseStatus props: license: SharedAuthUser['license'], variant?: 'light' | 'dark',
 *                      className?
 */
import { BadgeCheck, CircleSlash, Clock } from 'lucide-react';
import { cn } from '@/lib/utils';
import { RegistryChip } from './registry-chip';
import type { SharedAuthUser, UserRole } from '@/types/lexlink';

const ROLE_LABELS: Record<UserRole, string> = {
    advocate: 'Advokat',
    lawyer: 'Yurist',
    admin: 'Administrator',
    client: 'Mijoz',
};

export function RoleBadge({
    role,
    label,
    variant = 'light',
    className,
}: {
    role: UserRole | null;
    label?: string | null;
    variant?: 'light' | 'dark';
    className?: string;
}) {
    if (!role) {
        return null;
    }

    return (
        <span
            className={cn(
                'inline-flex h-6 items-center rounded-sm px-2 text-xs font-medium',
                variant === 'light'
                    ? 'bg-raised text-ink-secondary'
                    : 'bg-white/10 text-white/80',
                className,
            )}
        >
            {label || ROLE_LABELS[role]}
        </span>
    );
}

const LICENSE = {
    active: { text: 'tasdiqlangan', icon: BadgeCheck, tone: 'ok' },
    pending: { text: 'tekshirilmoqda', icon: Clock, tone: 'warn' },
    suspended: { text: "to'xtatilgan", icon: CircleSlash, tone: 'bad' },
    revoked: { text: 'bekor qilingan', icon: CircleSlash, tone: 'bad' },
} as const;

export function LicenseStatus({
    license,
    variant = 'light',
    className,
}: {
    license: SharedAuthUser['license'];
    variant?: 'light' | 'dark';
    className?: string;
}) {
    if (!license) {
        return null;
    }

    const cfg = LICENSE[license.status] ?? LICENSE.pending;
    const Icon = cfg.icon;
    const toneClass =
        variant === 'light'
            ? { ok: 'text-success', warn: 'text-warning', bad: 'text-danger' }[
                  cfg.tone
              ]
            : {
                  ok: 'text-white',
                  warn: 'text-white',
                  bad: 'text-white',
              }[cfg.tone];

    return (
        <span
            className={cn(
                'inline-flex min-w-0 items-center gap-2 text-meta',
                className,
            )}
        >
            <RegistryChip value={license.number} kind="license" size="sm" />
            <span className={cn('inline-flex items-center gap-1', toneClass)}>
                <Icon aria-hidden="true" className="size-3.5" />
                {cfg.text}
            </span>
        </span>
    );
}
