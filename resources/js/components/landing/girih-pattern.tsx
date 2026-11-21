/**
 * <GirihPattern className="absolute inset-0" />
 *
 * Original 8-point star lattice (girih geometry, drawn for LexLink — not a
 * copy of any monument or state symbol). Stroke only, rendered at <= 4%
 * opacity. Used on the landing hero and login only (docs/DESIGN.md).
 *
 * Props:
 *   className?: string — position/size and a mask if the pattern should fade
 *   tile?:      number — tile size in px (default 96)
 */
import { useId } from 'react';
import { cn } from '@/lib/utils';

function starPath(cx: number, cy: number, outer: number, inner: number) {
    const pts: string[] = [];

    for (let i = 0; i < 16; i++) {
        const r = i % 2 === 0 ? outer : inner;
        const a = (Math.PI / 8) * i;
        pts.push(
            `${(cx + r * Math.cos(a)).toFixed(2)} ${(cy + r * Math.sin(a)).toFixed(2)}`,
        );
    }

    return `M${pts.join('L')}Z`;
}

function octagonPath(cx: number, cy: number, r: number) {
    const pts: string[] = [];

    for (let i = 0; i < 8; i++) {
        const a = (Math.PI / 4) * i + Math.PI / 8;
        pts.push(
            `${(cx + r * Math.cos(a)).toFixed(2)} ${(cy + r * Math.sin(a)).toFixed(2)}`,
        );
    }

    return `M${pts.join('L')}Z`;
}

export function GirihPattern({
    className,
    tile = 96,
}: {
    className?: string;
    tile?: number;
}) {
    const id = useId().replace(/:/g, '');
    const c = tile / 2;
    const outer = tile * 0.3125; // 30 on a 96 tile
    const inner = tile * 0.16;
    const d = outer * Math.SQRT1_2;
    const corner = tile * 0.125; // diamond "radius" at tile corners

    // Rays from the star's outer points to the tile edges and corners, so
    // neighbouring tiles join into one continuous lattice.
    const rays = [
        `M${c + outer} ${c}H${tile}`,
        `M${c - outer} ${c}H0`,
        `M${c} ${c + outer}V${tile}`,
        `M${c} ${c - outer}V0`,
        `M${c + d} ${c + d}L${tile - corner / 2} ${tile - corner / 2}`,
        `M${c - d} ${c - d}L${corner / 2} ${corner / 2}`,
        `M${c + d} ${c - d}L${tile - corner / 2} ${corner / 2}`,
        `M${c - d} ${c + d}L${corner / 2} ${tile - corner / 2}`,
    ].join('');

    const corners = [
        `M${corner} 0L0 ${corner}`,
        `M${tile - corner} 0L${tile} ${corner}`,
        `M0 ${tile - corner}L${corner} ${tile}`,
        `M${tile} ${tile - corner}L${tile - corner} ${tile}`,
    ].join('');

    return (
        <svg
            aria-hidden="true"
            focusable="false"
            className={cn(
                'pointer-events-none text-ink opacity-[0.04]',
                className,
            )}
        >
            <defs>
                <pattern
                    id={`girih-${id}`}
                    width={tile}
                    height={tile}
                    patternUnits="userSpaceOnUse"
                >
                    <g
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="1.25"
                        strokeLinejoin="round"
                    >
                        <path d={starPath(c, c, outer, inner)} />
                        <path d={octagonPath(c, c, inner * 0.62)} />
                        <path d={rays} />
                        <path d={corners} />
                    </g>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill={`url(#girih-${id})`} />
        </svg>
    );
}
