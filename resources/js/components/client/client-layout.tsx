import type { ReactNode } from "react";
import PublicLayout from "@/layouts/public-layout";

/**
 * Client (ishonch bildiruvchi) portal: the public shell (no advocate sidebar)
 * with a narrower 896px reading column and a short privacy note.
 */
export default function ClientLayout({ children }: { children: ReactNode }) {
    return (
        <PublicLayout container={false}>
            <div className="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 px-4 py-8 lg:px-8 lg:py-12">
                {children}
                <p className="mt-4 border-t border-hairline pt-4 text-meta text-ink-secondary">
                    Ma'lumotlar advokatingiz tomonidan advokat orderi asosida
                    ko'rsatiladi. Barcha ko'rishlar audit jurnalida qayd etiladi.
                </p>
            </div>
        </PublicLayout>
    );
}
