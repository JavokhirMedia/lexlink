import type { ComponentType } from "react";
import type { AiAnalysis } from "@/types/lexlink";

type PanelProps = { requestId: number; analysis: AiAnalysis | null };

// The AI module owns @/components/ai/ai-analysis-panel; resolved via glob so this page
// still builds while that module is in progress.
const modules = import.meta.glob<{
    AiAnalysisPanel?: ComponentType<PanelProps>;
    default?: ComponentType<PanelProps>;
}>("/resources/js/components/ai/ai-analysis-panel.tsx", { eager: true });
const Panel =
    Object.values(modules)[0]?.AiAnalysisPanel ??
    Object.values(modules)[0]?.default ??
    null;

/** Renders <AiAnalysisPanel requestId analysis /> when the AI module is present. */
export function AiPanelSlot(props: PanelProps) {
    return Panel ? <Panel {...props} /> : null;
}
