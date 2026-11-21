import { useId } from "react";
import { cn } from "@/lib/utils";

/**
 * Original girih-style lattice (eight-point stars joined by kite lines), drawn
 * as thin strokes at 4% opacity behind the login card (docs/DESIGN.md: login
 * and landing hero only). Fades out towards the edges with a mask.
 */
export function GirihPattern({ className }: { className?: string }) {
    const id = useId().replace(/:/g, "");

    return (
        <div
            aria-hidden="true"
            className={cn(
                "pointer-events-none absolute text-brand opacity-[0.04]",
                "[mask-image:radial-gradient(ellipse_at_center,black_35%,transparent_75%)]",
                className,
            )}
        >
            <svg className="block h-full w-full">
                <defs>
                    <pattern
                        id={`girih-${id}`}
                        width="96"
                        height="96"
                        patternUnits="userSpaceOnUse"
                    >
                        <g
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="1.5"
                            strokeLinejoin="round"
                        >
                            {/* eight-point star: two squares, one turned 45° */}
                            <rect x="30" y="30" width="36" height="36" />
                            <rect
                                x="30"
                                y="30"
                                width="36"
                                height="36"
                                transform="rotate(45 48 48)"
                            />
                            <circle cx="48" cy="48" r="9" />
                            {/* kites that join neighbouring stars */}
                            <path d="M48 22.5 L48 0 M48 73.5 L48 96 M22.5 48 L0 48 M73.5 48 L96 48" />
                            <path d="M30 30 L12 12 L0 0 M66 30 L84 12 L96 0 M30 66 L12 84 L0 96 M66 66 L84 84 L96 96" />
                            <path d="M12 12 L0 24 M12 12 L24 0 M84 12 L96 24 M84 12 L72 0 M12 84 L0 72 M12 84 L24 96 M84 84 L96 72 M84 84 L72 96" />
                        </g>
                    </pattern>
                </defs>
                <rect width="100%" height="100%" fill={`url(#girih-${id})`} />
            </svg>
        </div>
    );
}
