import { useForm } from "@inertiajs/react";
import { KeyRound, ShieldCheck } from "lucide-react";
import { useState } from "react";
import type { FormEvent } from "react";
import { formatMoney } from "@/components/brand/money";
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
    const [selected, setSelected] = useState<string>("");
    const errors = form.errors as Record<string, string | undefined>;

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.submit(sign(requestId), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            Elektron raqamli imzo bilan imzolash
                        </DialogTitle>
                        <DialogDescription>
                            <span className="mr-2 inline-flex items-center rounded-sm border border-gold-border bg-gold-soft px-1.5 py-0.5 font-mono text-xs text-gold">
                                Demo: ERI imitatsiyasi
                            </span>
                            Haqiqiy E-IMZO ulanmagan — kalit tanlash jarayoni
                            ko'rsatiladi.
                        </DialogDescription>
                    </DialogHeader>

                    <fieldset className="space-y-2">
                        <legend className="mb-1 text-sm font-medium">
                            ERI kalitini tanlang
                        </legend>
                        <button
                            type="button"
                            onClick={() => {
                                setSelected(signerKey.id);
                                form.setData("key_id", signerKey.id);
                            }}
                            aria-pressed={selected === signerKey.id}
                            className={cn(
                                "flex w-full items-start gap-3 rounded-md border p-3 text-left transition-colors hover:bg-muted/60",
                                selected === signerKey.id &&
                                    "border-primary bg-primary/5 ring-2 ring-primary/20",
                            )}
                        >
                            <KeyRound
                                className="mt-0.5 size-5 shrink-0 text-primary"
                                aria-hidden
                            />
                            <span className="min-w-0 text-sm">
                                <span className="block font-medium">
                                    ERI kaliti: {signerKey.owner}, STIR{" "}
                                    {signerKey.stir}
                                </span>
                                <span className="block text-xs text-muted-foreground">
                                    {signerKey.issuer} · amal qiladi{" "}
                                    {formatDate(signerKey.valid_to)} gacha
                                    {signerKey.license_number
                                        ? ` · Litsenziya ${signerKey.license_number}`
                                        : ""}
                                </span>
                            </span>
                        </button>
                        <InputError message={errors.key_id} />
                    </fieldset>

                    <div className="rounded-md border bg-muted/40 p-3 text-sm">
                        <p className="flex items-center justify-between">
                            <span className="text-muted-foreground">
                                To'lov
                            </span>
                            <span className="font-medium tabular-nums">
                                {quote
                                    ? quote.from_subscription
                                        ? "Obuna doirasida"
                                        : formatMoney(quote.cost)
                                    : "—"}
                            </span>
                        </p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Rad etilsa yoki texnik xato bo'lsa, mablag'
                            avtomatik qaytariladi.
                        </p>
                    </div>

                    <div className="flex items-start gap-2">
                        <Checkbox
                            id="confirm"
                            checked={form.data.confirm}
                            onCheckedChange={(v) =>
                                form.setData("confirm", v === true)
                            }
                        />
                        <Label
                            htmlFor="confirm"
                            className="text-sm leading-snug font-normal"
                        >
                            So'rov huquqiy asos doirasida ekanini va olingan
                            ma'lumot advokat siri sifatida saqlanishini
                            tasdiqlayman.
                        </Label>
                    </div>
                    <InputError message={errors.confirm} />

                    {(errors.basis ||
                        errors.billing ||
                        errors.rate ||
                        errors.status ||
                        errors.request_text ||
                        errors.subject_pinfl) && (
                        <div
                            role="alert"
                            className="rounded-md border border-danger-border bg-danger-soft p-3 text-sm text-danger"
                        >
                            {errors.basis ??
                                errors.billing ??
                                errors.rate ??
                                errors.status ??
                                errors.request_text ??
                                errors.subject_pinfl}
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

                    <DialogFooter>
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
                            {form.processing ? <Spinner /> : <ShieldCheck />}
                            Imzolash va yuborish
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
