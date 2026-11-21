import { router } from "@inertiajs/react";
import { RefreshCw } from "lucide-react";
import { useState } from "react";
import { formatDateTime, formatNumber } from "@/components/brand/money";
import { Section } from "@/components/brand/section";
import { Button } from "@/components/ui/button";
import { analyze } from "@/routes/ai/requests";
import type { AiAnalysis, AlimonyCalculation } from "@/types/lexlink";
import { AiDisclaimer } from "./ai-disclaimer";
import { AiSentPayload } from "./ai-sent-payload";
import { AlimonyTable } from "./alimony-table";
import { CitationChips } from "./citation-chips";
import { modelLabel, restorePii } from "./format";
import { AiMarkdown } from "./markdown";

type Props = { requestId: number; analysis: AiAnalysis | null };

type AssetFacts = { count: number; encumbered?: number };

export function AiAnalysisPanel({ requestId, analysis }: Props) {
    const [processing, setProcessing] = useState(false);

    const run = () => {
        router.post(
            analyze.url(requestId),
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const structured = analysis?.structured ?? null;
    const alimony = (structured?.alimony ?? null) as AlimonyCalculation | null;
    const facts = (structured?.facts ?? null) as AssetFacts | null;
    const output = restorePii(analysis?.output, structured);
    const disclaimer =
        analysis?.disclaimer ?? (structured?.disclaimer as string | undefined);

    return (
        <Section
            title="AI tahlil"
            description={
                analysis ? (
                    <span className="tabular-nums">
                        {formatDateTime(analysis.created_at)}
                        {analysis.model && ` · ${modelLabel(analysis.model)}`}
                    </span>
                ) : (
                    "AI'ga faqat egasizlantirilgan ma'lumot yuboriladi (ShMQ 16-modda)"
                )
            }
            actions={
                <Button
                    variant="outline"
                    size="sm"
                    onClick={run}
                    disabled={processing}
                >
                    <RefreshCw
                        className={processing ? "animate-spin" : undefined}
                        aria-hidden
                    />
                    {analysis ? "Qayta tahlil" : "Tahlil qilish"}
                </Button>
            }
        >
            <div className="flex flex-col gap-6 rounded-lg border bg-card p-4 shadow-xs sm:p-6">
                {!analysis && (
                    <p className="text-sm text-ink-secondary">
                        {processing
                            ? "Tahlil tayyorlanmoqda…"
                            : "Javob hali tahlil qilinmagan"}
                    </p>
                )}

                {analysis?.status === "failed" && (
                    <p className="text-sm text-danger" role="alert">
                        Tahlil bajarilmadi. Qayta urinib ko'ring.
                    </p>
                )}

                {alimony && <AlimonyTable calc={alimony} />}

                {facts &&
                    typeof facts.count === "number" &&
                    structured?.type !== "court_case" && (
                        <p className="text-sm text-ink-secondary">
                            Topildi:{" "}
                            <span className="font-medium text-ink tabular-nums">
                                {formatNumber(facts.count)}
                            </span>
                            {typeof facts.encumbered === "number" && (
                                <>
                                    {" · "}Garov / taqiq:{" "}
                                    <span className="font-medium text-ink tabular-nums">
                                        {formatNumber(facts.encumbered)}
                                    </span>
                                </>
                            )}
                        </p>
                    )}

                {output && (
                    <AiMarkdown
                        citations={analysis?.citations}
                        className="max-w-[72ch]"
                    >
                        {output}
                    </AiMarkdown>
                )}

                <CitationChips citations={analysis?.citations} />

                {analysis && (
                    <AiSentPayload
                        report={analysis.anonymization}
                        sentPayload={analysis.sent_payload}
                    />
                )}

                {analysis && <AiDisclaimer text={disclaimer} />}
            </div>
        </Section>
    );
}

export default AiAnalysisPanel;
