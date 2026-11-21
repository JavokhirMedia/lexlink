import { ChevronDown, ShieldAlert, ShieldCheck } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
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
    const summary = replacedSummary(report);

    return (
        <Collapsible
            open={open}
            onOpenChange={setOpen}
            className={cn("rounded-md border bg-muted/30", className)}
        >
            <CollapsibleTrigger className="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm font-medium hover:bg-muted/50">
                <span className="flex items-center gap-2">
                    {blocked ? (
                        <ShieldAlert
                            className="size-4 text-destructive"
                            aria-hidden
                        />
                    ) : (
                        <ShieldCheck
                            className="size-4 text-primary"
                            aria-hidden
                        />
                    )}
                    AI'ga nima yuborildi?
                </span>
                <ChevronDown
                    className={cn(
                        "size-4 text-muted-foreground transition-transform",
                        open && "rotate-180",
                    )}
                    aria-hidden
                />
            </CollapsibleTrigger>
            <CollapsibleContent className="space-y-3 border-t px-3 py-3 text-sm">
                <div className="flex flex-wrap gap-2">
                    {blocked ? (
                        <Badge variant="destructive">
                            Bloklandi — shaxsiy ma'lumot aniqlandi
                        </Badge>
                    ) : (
                        <Badge variant="secondary">
                            Egasizlantirildi — ShMQ 16-modda
                        </Badge>
                    )}
                    {!report.ai_called && (
                        <Badge variant="outline">AI chaqirilmadi</Badge>
                    )}
                </div>

                {summary && (
                    <p className="text-muted-foreground">
                        {summary} maskalandi (o'rniga [SHAXS_1] kabi belgilar
                        yuborildi).
                    </p>
                )}
                {(report.dropped?.length ?? 0) > 0 && (
                    <p className="text-muted-foreground">
                        Yuborilmadi: {report.dropped?.join(", ")}.
                    </p>
                )}
                {(report.generalized?.length ?? 0) > 0 && (
                    <p className="text-muted-foreground">
                        {report.generalized?.join("; ")}.
                    </p>
                )}
                {blocked && (report.blocked?.length ?? 0) > 0 && (
                    <p className="text-destructive">
                        Aniqlangan:{" "}
                        {report.blocked
                            ?.map((b) => `${b.label} (${b.count})`)
                            .join(", ")}
                        . So'rov Gemini'ga yuborilmadi.
                    </p>
                )}
                {report.note && (
                    <p className="text-xs text-muted-foreground">
                        {report.note}
                    </p>
                )}
                <p className="text-xs text-muted-foreground">
                    Faqat egasizlantirilgan ma'lumot yuboriladi: u endi shaxsga
                    doir ma'lumot hisoblanmaydi, shu sababli transchegaraviy
                    uzatish talablari (ShMQ 15-modda) qo'llanilmaydi. Summalar
                    va hodisa sanalari tahlil uchun saqlanadi.
                </p>

                {sentPayload ? (
                    <pre className="max-h-80 overflow-auto rounded-md border bg-background p-3 font-mono text-xs leading-relaxed whitespace-pre-wrap">
                        {sentPayload}
                    </pre>
                ) : (
                    <p className="text-xs text-muted-foreground">
                        Tashqariga hech qanday matn yuborilmadi.
                    </p>
                )}
            </CollapsibleContent>
        </Collapsible>
    );
}

export default AiSentPayload;
