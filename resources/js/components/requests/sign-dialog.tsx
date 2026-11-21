import { useForm } from "@inertiajs/react";
import { KeyRound, PenLine } from "lucide-react";
import type { FormEvent } from "react";
import { formatMoney } from "@/components/brand/money";
import { RegistryChip } from "@/components/brand/registry-chip";
import InputError from "@/components/input-error";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Spinner } from "@/components/ui/spinner";
import { cn } from "@/lib/utils";
import { sign } from "@/routes/requests";
import type { Quote, SignerKey } from "./types";
import { formatDate } from "./types";

/**
 * Mock E-IMZO: pick the ERI key and confirm. Server: DataRequestService::signAndSubmit.
 * Props: requestId, signerKey, quote, open, onOpenChange
 */
export function SignDialog({
    requestId,
    signerKey,
    quote,
    open,
    onOpenChange,
}: {
    requestId: number;
    signerKey: SignerKey;
    quote: Quote | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({ key_id: "", confirm: false });
    const errors = form.errors as Record<string, string | undefined>;
    const selected = form.data.key_id === signerKey.id;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.submit(sign(requestId), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    const serverError =
        errors.basis ??
        errors.billing ??
        errors.rate ??
        errors.status ??
        errors.request_text ??
        errors.subject_pinfl;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit} className="flex flex-col gap-6">
                    <DialogHeader>
                        <DialogTitle className="text-xl leading-7">
                            ERI bilan imzolash
                        </DialogTitle>
                        <DialogDescription className="text-sm text-ink-secondary">
                            E-IMZO imitatsiyasi (demo): haqiqiy kalit
                            ulanmagan, tanlash jarayoni ko'rsatiladi
                        </DialogDescription>
                    </DialogHeader>

                    <fieldset className="flex flex-col gap-2">
                        <legend className="mb-2 text-sm font-medium text-ink">
                            Kalitni tanlang
                        </legend>
                        <div role="radiogroup" aria-label="ERI kaliti">
                            <button
                                type="button"
                                role="radio"
                                aria-checked={selected}
                                onClick={() =>
                                    form.setData("key_id", signerKey.id)
                                }
                                className={cn(
                                    "focus-ring flex w-full items-start gap-3 rounded-lg border bg-surface p-4 text-left transition-colors hover:border-ink-muted/50",
                                    selected &&
                                        "border-brand bg-brand-soft hover:border-brand",
                                )}
                            >
                                <span
                                    className={cn(
                                        "mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border",
                                        selected
                                            ? "border-brand"
                                            : "border-input",
                                    )}
                                    aria-hidden
                                >
                                    {selected && (
                                        <span className="size-2 rounded-full bg-brand" />
                                    )}
                                </span>
                                <span className="flex min-w-0 flex-1 flex-col gap-1.5">
                                    <span className="flex items-center gap-2 text-base font-medium text-ink">
                                        <KeyRound
                                            className="size-[18px] shrink-0 text-ink-secondary"
                                            strokeWidth={1.5}
                                            aria-hidden
                                        />
                                        <span className="truncate">
                                            {signerKey.owner}
                                        </span>
                                    </span>
                                    <span className="text-sm text-ink-secondary">
                                        STIR{" "}
                                        <span className="font-mono text-ink tabular-nums">
                                            {signerKey.stir}
                                        </span>{" "}
                                        · {signerKey.issuer}
                                    </span>
                                    <span className="flex flex-wrap items-center gap-2 text-xs text-ink-secondary">
                                        {signerKey.license_number && (
                                            <RegistryChip
                                                value={
                                                    signerKey.license_number
                                                }
                                                kind="license"
                                                size="sm"
                                            />
                                        )}
                                        <span className="tabular-nums">
                                            Amal qiladi{" "}
                                            {formatDate(signerKey.valid_to)}{" "}
                                            gacha
                                        </span>
                                    </span>
                                </span>
                            </button>
                        </div>
                        <InputError message={errors.key_id} />
                    </fieldset>

                    <div className="flex flex-col gap-1 border-y py-4">
                        <div className="flex items-baseline justify-between gap-4">
                            <span className="text-sm text-ink-secondary">
                                To'lov
                            </span>
                            <span className="text-base font-semibold text-ink tabular-nums">
                                {quote
                                    ? quote.from_subscription
                                        ? "Obuna doirasida"
                                        : formatMoney(quote.cost)
                                    : "—"}
                            </span>
                        </div>
                        <p className="text-xs text-ink-muted">
                            Rad etilsa yoki texnik xato bo'lsa, mablag'
                            avtomatik qaytariladi
                        </p>
                    </div>

                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="confirm"
                            className="mt-0.5"
                            checked={form.data.confirm}
                            onCheckedChange={(v) =>
                                form.setData("confirm", v === true)
                            }
                        />
                        <Label
                            htmlFor="confirm"
                            className="text-sm leading-5 font-normal text-ink"
                        >
                            So'rov huquqiy asos doirasida ekanini va olingan
                            ma'lumot advokat siri sifatida saqlanishini
                            tasdiqlayman
                        </Label>
                    </div>
                    <InputError message={errors.confirm} />

                    {serverError && (
                        <div
                            role="alert"
                            className="rounded-lg border border-danger-border bg-danger-soft px-4 py-3 text-sm text-danger"
                        >
                            {serverError}
                            {errors.billing && (
                                <a
                                    href="/billing"
                                    className="ml-1 font-medium underline underline-offset-2"
                                >
                                    Balansni to'ldirish
                                </a>
                            )}
                        </div>
                    )}

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Bekor qilish
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                !form.data.key_id ||
                                !form.data.confirm ||
                                form.processing
                            }
                        >
                            {form.processing ? <Spinner /> : <PenLine />}
                            Imzolash va yuborish
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
