/**
 * Auth pages body (rendered inside PublicLayout): a centered 440px card with
 * the page title and description. Login page content goes inside.
 */
export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex flex-1 items-start justify-center py-4 sm:py-8">
            <div className="w-full max-w-[440px] rounded-xl border bg-card p-6 shadow-xs sm:p-8">
                {(title || description) && (
                    <div className="mb-6 flex flex-col gap-2">
                        {title && (
                            <h1 className="text-page-title text-ink">{title}</h1>
                        )}
                        {description && (
                            <p className="text-base text-ink-secondary">
                                {description}
                            </p>
                        )}
                    </div>
                )}
                {children}
            </div>
        </div>
    );
}
