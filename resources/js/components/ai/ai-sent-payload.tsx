import { ChevronDown, ShieldAlert, ShieldCheck } from "lucide-react";
import { useState } from "react";
import { formatNumber } from "@/components/brand/money";
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from "@/components/ui/collapsible";
import { cn } from "@/lib/utils";
import type { AnonymizationReport } from "@/types/lexlink";

type Props = {
    report?: AnonymizationReport | null;
    sentPayload?: string | null;
    className?: string;
};

/** "3 ta shaxs, 2 ta tashkilot, 1 ta sud ishi raqami" */
export function replacedSummary(report?: AnonymizationReport | null): string {
    return (report?.replaced ?? [])
        .filter((r) => r.count > 0)
        .map((r) => `${r.count} ta ${r.label}`)
        .join(", ");
}

function StateBadge({ blocked, notCalled = false }: { blocked: boolean; notCalled?: boolean }) {
    if (notCalled) {
        return (
            <span className="inline-flex h-6 shrink-0 items-center gap-1.5 rounded-sm border border-hairline bg-raised px-2 text-meta font-medium text-ink-secondary">
                <ShieldCheck className="size-3.5" strokeWidth={1.75} aria-hidden />
                Tashqariga hech narsa yuborilmadi
            </span>
        );
    }

    return blocked ? (
        <span className="inline-flex h-6 shrink-0 items-center gap-1.5 rounded-sm border border-danger-border bg-danger-soft px-2 text-meta font-medium text-danger">
            <ShieldAlert className="size-3.5" strokeWidth={1.75} aria-hidden />
            Bloklandi — shaxsiy ma'lumot aniqlandi
        </span>
    ) : (
        <span className="inline-flex h-6 shrink-0 items-center gap-1.5 rounded-sm border border-success-border bg-success-soft px-2 text-meta font-medium text-success">
            <ShieldCheck className="size-3.5" strokeWidth={1.75} aria-hidden />
            Egasizlantirildi — ShMQ 16-modda
        </span>
    );
}

/**
 * "AI'ga nima yuborildi?" — transparency block: anonymisation badge, counts per category and the exact
 * egasizlantirilgan text that left the server. The placeholder -> value map is never sent to the browser here.
 */
export function AiSentPayload({ report, sentPayload, className }: Props) {
    const [open, setOpen] = useState(false);

    if (!report) {
        return null;
    }

    const blocked = report.leak_check === "blocked";
    const notCalled = !blocked && !sentPayload;
    const replaced = (report.replaced ?? []).filter((r) => r.count > 0);

    return (
        <Collapsible
            open={open}
            onOpenChange={setOpen}
            className={cn("rounded-lg border bg-card", className)}
        >
            <CollapsibleTrigger className="focus-ring flex min-h-11 w-full items-center justify-between gap-3 rounded-lg px-4 py-2 text-left text-sm font-medium text-ink transition-colors hover:bg-page">
                <span className="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                    AI'ga nima yuborildi?
                    <span className="hidden sm:inline-flex">
                        <StateBadge blocked={blocked} notCalled={notCalled} />
                    </span>
                </span>
                <ChevronDown
                    className={cn(
                        "size-4 shrink-0 text-ink-muted transition-transform duration-200",
                        open && "rotate-180",
                    )}
                    strokeWidth={1.5}
                    aria-hidden
                />
            </CollapsibleTrigger>
            <CollapsibleContent className="flex flex-col gap-4 border-t px-4 py-4 text-sm">
                <div className="flex flex-wrap gap-2 sm:hidden">
                    <StateBadge blocked={blocked} notCalled={notCalled} />
                </div>
                {!report.ai_called && (
                    <p className="text-meta text-ink-secondary">
                        AI chaqirilmadi
                    </p>
                )}

                {replaced.length > 0 && (
                    <div className="flex flex-col gap-2">
                        <p className="text-meta text-ink-secondary">
                            Maskalandi (o'rniga [SHAXS_1] kabi belgilar
                            yuborildi)
                        </p>
                        <dl className="grid max-w-md grid-cols-[minmax(0,1fr)_auto] gap-x-6 gap-y-1">
                            {replaced.map((r) => (
                                <div key={r.label} className="contents">
                                    <dt className="text-ink-secondary">
                                        {r.label}
                                    </dt>
                                    <dd className="text-right font-mono text-ink tabular-nums">
                                        {formatNumber(r.count)}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </div>
                )}
                {(report.dropped?.length ?? 0) > 0 && (
                    <p className="text-ink-secondary">
                        Yuborilmadi: {report.dropped?.join(", ")}
                    </p>
                )}
                {(report.generalized?.length ?? 0) > 0 && (
                    <p className="text-ink-secondary">
                        {report.generalized?.join("; ")}
                    </p>
                )}
                {blocked && (report.blocked?.length ?? 0) > 0 && (
                    <p className="text-danger">
                        Aniqlangan:{" "}
                        {report.blocked
                            ?.map((b) => `${b.label} (${b.count})`)
                            .join(", ")}
                        . So'rov Gemini'ga yuborilmadi.
                    </p>
                )}
                {report.note && (
                    <p className="text-meta text-ink-secondary">
                        {report.note}
                    </p>
                )}

                {sentPayload ? (
                    <div className="flex flex-col gap-2">
                        <p className="text-meta text-ink-secondary">
                            Yuborilgan matn
                        </p>
                        <pre className="max-h-72 overflow-auto rounded-md border bg-raised p-4 font-mono text-meta whitespace-pre-wrap text-ink">
                            {sentPayload}
                        </pre>
                    </div>
                ) : (
                    <p className="text-meta text-ink-secondary">
                        Tashqariga hech qanday matn yuborilmadi
                    </p>
                )}

                <p className="text-meta text-ink-muted">
                    Faqat egasizlantirilgan ma'lumot yuboriladi: u endi shaxsga
                    doir ma'lumot hisoblanmaydi, shu sababli transchegaraviy
                    uzatish talablari (ShMQ 15-modda) qo'llanilmaydi. Summalar
                    va hodisa sanalari tahlil uchun saqlanadi.
                </p>
            </CollapsibleContent>
        </Collapsible>
    );
}

export default AiSentPayload;
