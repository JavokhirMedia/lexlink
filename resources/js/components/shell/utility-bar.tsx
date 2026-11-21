/**
 * <UtilityBar /> — 36px strip above the header (docs/DESIGN.md "Shell" 1).
 * Left: the ONLY demo notice in the shell. Right: "Maxsus imkoniyatlar"
 * (text size, high contrast, grayscale) and "Yordam" (/help).
 */
import { Link } from '@inertiajs/react';
import { Accessibility, CircleHelp } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    isA11yDefault,
    resetA11y,
    updateA11y,
    useAccessibility,
} from '@/hooks/use-accessibility';
import type { TextSize } from '@/hooks/use-accessibility';
import { cn } from '@/lib/utils';
import { help } from '@/routes';

const SIZES: { value: TextSize; label: string }[] = [
    { value: 100, label: 'Odatiy · 100%' },
    { value: 112, label: 'Kattaroq · 112%' },
    { value: 125, label: 'Katta · 125%' },
];

const linkClass =
    'focus-ring inline-flex h-7 items-center gap-1.5 rounded-sm px-2 text-meta text-ink-secondary transition-colors hover:bg-surface hover:text-ink data-[state=open]:bg-surface data-[state=open]:text-ink';

export function AccessibilityMenu() {
    const a11y = useAccessibility();
    const keepOpen = (e: Event) => e.preventDefault();

    return (
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger
                className={linkClass}
                aria-label="Maxsus imkoniyatlar"
            >
                <Accessibility
                    aria-hidden="true"
                    className="size-4"
                    strokeWidth={1.5}
                />
                <span className="hidden sm:inline">Maxsus imkoniyatlar</span>
                {!isA11yDefault(a11y) && (
                    <span className="sr-only">(yoqilgan)</span>
                )}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-64">
                <DropdownMenuLabel>Matn o'lchami</DropdownMenuLabel>
                <DropdownMenuRadioGroup
                    value={String(a11y.textSize)}
                    onValueChange={(v) =>
                        updateA11y({ textSize: Number(v) as TextSize })
                    }
                >
                    {SIZES.map((s) => (
                        <DropdownMenuRadioItem
                            key={s.value}
                            value={String(s.value)}
                            onSelect={keepOpen}
                        >
                            {s.label}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
                <DropdownMenuSeparator />
                <DropdownMenuLabel>Ko'rinish</DropdownMenuLabel>
                <DropdownMenuCheckboxItem
                    checked={a11y.contrast}
                    onCheckedChange={(v) => updateA11y({ contrast: v === true })}
                    onSelect={keepOpen}
                >
                    Yuqori kontrast
                </DropdownMenuCheckboxItem>
                <DropdownMenuCheckboxItem
                    checked={a11y.grayscale}
                    onCheckedChange={(v) =>
                        updateA11y({ grayscale: v === true })
                    }
                    onSelect={keepOpen}
                >
                    Oq-qora rejim
                </DropdownMenuCheckboxItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    disabled={isA11yDefault(a11y)}
                    onSelect={(e) => {
                        e.preventDefault();
                        resetA11y();
                    }}
                >
                    Odatiy holatga qaytarish
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

export function UtilityBar({
    contained = false,
    className,
}: {
    /** Public pages: align with the 1280 content column. */
    contained?: boolean;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'h-utility border-b border-hairline bg-raised print:hidden',
                className,
            )}
        >
            <div
                className={cn(
                    'flex h-full items-center gap-4 px-4 lg:px-8',
                    contained && 'mx-auto w-full max-w-content',
                )}
            >
                <p className="min-w-0 flex-1 truncate text-meta text-ink-secondary">
                    <span className="hidden md:inline">
                        Advokat so'rovlari yagona axborot tizimi ·{' '}
                    </span>
                    demo, barcha ma'lumotlar uydirma
                </p>
                <div className="flex shrink-0 items-center gap-1">
                    <AccessibilityMenu />
                    <Link href={help()} className={linkClass}>
                        <CircleHelp
                            aria-hidden="true"
                            className="size-4"
                            strokeWidth={1.5}
                        />
                        Yordam
                    </Link>
                </div>
            </div>
        </div>
    );
}
