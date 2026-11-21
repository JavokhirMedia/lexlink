import type { ReactNode } from "react";
import { AuthCard } from "@/layouts/auth/auth-card";
import { GirihPattern } from "@/layouts/auth/girih-pattern";

/**
 * Auth pages body (rendered inside PublicLayout): a faint original girih
 * lattice (4%) behind a centered 440px column. With `title`/`description`
 * the children go into one AuthCard; without them the page lays out its own
 * cards (login: OneID card + the secondary demo list below it).
 */
export default function AuthLayout({
    title = "",
    description = "",
    children,
}: {
    title?: string;
    description?: string;
    children: ReactNode;
}) {
    return (
        <div className="relative flex flex-1 flex-col items-center gap-8 sm:py-4">
            <GirihPattern className="-top-8 -right-4 -left-4 h-[720px] lg:-top-12 lg:-right-8 lg:-left-8" />
            {title || description ? (
                <AuthCard title={title} description={description}>
                    {children}
                </AuthCard>
            ) : (
                children
            )}
        </div>
    );
}
