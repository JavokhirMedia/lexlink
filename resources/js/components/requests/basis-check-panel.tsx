import { ShieldAlert, ShieldCheck, ShieldEllipsis } from "lucide-react";
import { cn } from "@/lib/utils";
import type { BasisCheck } from "./types";
import { formatDate } from "./types";

/**
 * Rule #1 "Asos bo'lmasa — so'rov yo'q": live result of DataRequestService::checkBasis.
 * Props: check (null = not checked yet), loading?
 */
export function BasisCheckPanel({
    check,
    loading = false,
    className,
}: {
    check: BasisCheck | null;
    loading?: boolean;
    className?: string;
}) {
    if (!check) {
        return (
            <div
                className={cn(
                    "flex items-start gap-3 rounded-md border border-dashed p-4 text-sm text-muted-foreground",
                    className,
                )}
            >
                <ShieldEllipsis
                    className="mt-0.5 size-5 shrink-0"
                    aria-hidden
                />
                <div>
                    <p className="font-medium text-foreground">
                        Huquqiy asos tekshiruvi
                    </p>
                    <p>
                        Shaxs va so'rov turini tanlang — tizim asos qamrovini
                        tekshiradi.
                    </p>
                </div>
            </div>
        );
    }

    if (check.ok && check.basis) {
        const b = check.basis;

        return (
            <div
                role="status"
                className={cn(
                    "flex items-start gap-3 rounded-md border border-success-border bg-success-soft p-4 text-sm",
                    loading && "opacity-60",
                    className,
                )}
            >
                <ShieldCheck
                    className="mt-0.5 size-5 shrink-0 text-success"
                    aria-hidden
                />
                <div className="space-y-0.5">
                    <p className="font-medium text-success">
                        Asos qamraydi:{" "}
                        {b.type === "court_decision" ? "ajrim" : "order"} №{" "}
                        {b.number}
                        {b.expires_at
                            ? `, amal qiladi ${formatDate(b.expires_at)} gacha`
                            : ""}
                    </p>
                    <p className="text-foreground/80">
                        {b.type_label}
                        {b.court_name ? ` · ${b.court_name}` : ""}
                        {b.subject_name ? ` · ${b.subject_name}` : ""}
                        {b.subject_pinfl_masked
                            ? ` (${b.subject_pinfl_masked})`
                            : ""}
                    </p>
                </div>
            </div>
        );
    }

    return (
        <div
            role="alert"
            className={cn(
                "flex items-start gap-3 rounded-md border border-danger-border bg-danger-soft p-4 text-sm",
                loading && "opacity-60",
                className,
            )}
        >
            <ShieldAlert
                className="mt-0.5 size-5 shrink-0 text-danger"
                aria-hidden
            />
            <div className="space-y-0.5">
                <p className="font-medium text-danger">So'rov yuborilmaydi</p>
                <p className="text-foreground/90">{check.reason}</p>
            </div>
        </div>
    );
}
