/**
 * <WalletChip /> — header wallet balance for advocates, links to /billing.
 */
import { Link, usePage } from '@inertiajs/react';
import { Money } from '@/components/brand/money';
import { cn } from '@/lib/utils';

export function WalletChip({ className }: { className?: string }) {
    const { auth } = usePage().props;

    if (auth.user?.role !== 'advocate') {
        return null;
    }

    return (
        <Link
            href="/billing"
            className={cn(
                'focus-ring flex h-11 flex-col justify-center rounded-md px-3 text-right transition-colors hover:bg-raised',
                className,
            )}
        >
            <span className="text-meta text-ink-secondary">Hisob balansi</span>
            <Money
                amount={auth.wallet_balance ?? 0}
                className="text-sm font-semibold text-ink"
            />
        </Link>
    );
}
