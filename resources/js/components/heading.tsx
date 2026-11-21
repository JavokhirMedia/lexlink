export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}) {
    return (
        <header
            className={
                variant === 'small' ? 'flex flex-col gap-1' : 'mb-6 flex flex-col gap-1'
            }
        >
            <h2
                className={
                    variant === 'small'
                        ? 'text-base font-semibold text-ink'
                        : 'text-section-title text-ink'
                }
            >
                {title}
            </h2>
            {description && (
                <p className="text-sm text-ink-secondary">{description}</p>
            )}
        </header>
    );
}
