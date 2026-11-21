import { router } from "@inertiajs/react";
import { FileSearch, RefreshCw } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { analyze } from "@/routes/ai/requests";
import type { AiAnalysis, AlimonyCalculation } from "@/types/lexlink";
import { AiDisclaimer } from "./ai-disclaimer";
import { AiSentPayload } from "./ai-sent-payload";
import { AlimonyTable } from "./alimony-table";
import { CitationChips } from "./citation-chips";
import { modelLabel, restorePii } from "./format";
import { AiMarkdown } from "./markdown";
import { formatDateTime } from "@/components/brand/money";

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
        <Card className="gap-4">
            <CardHeader className="flex flex-row items-start justify-between gap-3">
                <div className="space-y-1">
                    <CardTitle className="flex items-center gap-2 text-base">
                        <FileSearch
                            className="size-4 text-primary"
                            aria-hidden
                        />
                        AI tahlil
                    </CardTitle>
                    {analysis && (
                        <p className="text-xs text-muted-foreground">
                            {formatDateTime(analysis.created_at)}
                            {analysis.model &&
                                ` · ${modelLabel(analysis.model)}`}
                        </p>
                    )}
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={run}
                    disabled={processing}
                >
                    <RefreshCw
                        className={
                            processing ? "size-4 animate-spin" : "size-4"
                        }
                        aria-hidden
                    />
                    {analysis ? "Qayta tahlil" : "Tahlil qilish"}
                </Button>
            </CardHeader>

            <CardContent className="space-y-4">
                {!analysis && (
                    <p className="text-sm text-muted-foreground">
                        {processing
                            ? "Tahlil tayyorlanmoqda…"
                            : "Javob hali tahlil qilinmagan. AI'ga faqat egasizlantirilgan ma'lumot yuboriladi (ShMQ 16-modda)."}
                    </p>
                )}

                {analysis?.status === "failed" && (
                    <p className="text-sm text-destructive">
                        Tahlil bajarilmadi. Qayta urinib ko'ring.
                    </p>
                )}

                {alimony && <AlimonyTable calc={alimony} />}

                {facts &&
                    typeof facts.count === "number" &&
                    structured?.type !== "court_case" && (
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="secondary">
                                Topildi: {facts.count}
                            </Badge>
                            {typeof facts.encumbered === "number" && (
                                <Badge variant="outline">
                                    Garov / taqiq: {facts.encumbered}
                                </Badge>
                            )}
                        </div>
                    )}

                {output && (
                    <AiMarkdown citations={analysis?.citations}>
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
            </CardContent>
        </Card>
    );
}

export default AiAnalysisPanel;
