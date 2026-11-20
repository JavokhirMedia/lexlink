/**
 * <SecrecyDeclarationDialog caseId={case.id} open={open} onOpenChange={setOpen} onAccepted={() => ...} />
 *
 * Per-case "advokat sirini saqlash majburiyati". A lawyer must accept it once per case
 * before seeing any response of that case. POSTs to cases.secrecy-declaration.store
 * (sets case_members.secrecy_declared_at, audited as SecrecyDeclared), then calls onAccepted().
 *
 * Props:
 *   caseId:        number
 *   open:          boolean
 *   onAccepted:    () => void              — called after the server stored the declaration
 *   onOpenChange?: (open: boolean) => void — called when the user dismisses the dialog
 */
import { router } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/cases/secrecy-declaration';

type Props = {
    caseId: number;
    open: boolean;
    onAccepted: () => void;
    onOpenChange?: (open: boolean) => void;
};

export function SecrecyDeclarationDialog({
    caseId,
    open,
    onAccepted,
    onOpenChange,
}: Props) {
    const [checked, setChecked] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const submit = () => {
        setProcessing(true);
        setError(null);

        router.post(
            store.url(caseId),
            { accepted: true },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => onAccepted(),
                onError: (errors) =>
                    setError(
                        Object.values(errors)[0] ??
                            'Majburiyatni saqlab bo‘lmadi.',
                    ),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <ShieldCheck className="size-5 text-primary" />
                        Advokat sirini saqlash majburiyati
                    </DialogTitle>
                    <DialogDescription>
                        Ushbu ish bo‘yicha davlat idoralaridan olingan javoblarni
                        ko‘rishdan oldin quyidagi majburiyatni qabul qiling.
                    </DialogDescription>
                </DialogHeader>

                <div className="max-h-[50vh] space-y-3 overflow-y-auto rounded-md border bg-muted/40 p-4 text-sm leading-relaxed">
                    <p>Men, advokat yordamchisi (yurist) sifatida:</p>
                    <ol className="list-decimal space-y-2 pl-5">
                        <li>
                            ushbu ish doirasida menga ma’lum bo‘lgan barcha
                            ma’lumotlar — mijoz, boshqa shaxslar, ularning daromadi,
                            mol-mulki va sud ishlari haqidagi ma’lumotlar —
                            “Advokatura to‘g‘risida”gi Qonunga muvofiq advokat siri
                            hisoblanishini tan olaman;
                        </li>
                        <li>
                            ushbu ma’lumotlarni faqat advokat topshirig‘i va ish
                            maqsadlarida ishlataman, uchinchi shaxslarga oshkor
                            qilmayman, nusxalamayman va platformadan tashqariga
                            chiqarmayman;
                        </li>
                        <li>
                            shaxsga doir ma’lumotlar (JShShIR, F.I.Sh., daromad va
                            boshqalar) “Shaxsga doir ma’lumotlar to‘g‘risida”gi
                            Qonun talablariga, soliq siri esa soliq qonunchiligiga
                            muvofiq muhofaza qilinishini bilaman;
                        </li>
                        <li>
                            majburiyat jamoadan chiqqanimdan yoki ish
                            yakunlanganidan keyin ham amal qilishini, uni buzish
                            qonunchilikda belgilangan javobgarlikka sabab bo‘lishi
                            mumkinligini tushunaman;
                        </li>
                        <li>
                            javoblarni har bir ko‘rishim va yuklab olishim audit
                            jurnalida qayd etilishiga roziman.
                        </li>
                    </ol>
                </div>

                <div className="flex items-start gap-3">
                    <Checkbox
                        id={`secrecy-accept-${caseId}`}
                        checked={checked}
                        onCheckedChange={(v) => setChecked(v === true)}
                    />
                    <Label
                        htmlFor={`secrecy-accept-${caseId}`}
                        className="text-sm leading-snug font-normal"
                    >
                        Men yuqoridagi majburiyatni o‘qib chiqdim va uni qabul
                        qilaman.
                    </Label>
                </div>

                <InputError message={error ?? undefined} />

                <DialogFooter>
                    {onOpenChange && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Bekor qilish
                        </Button>
                    )}
                    <Button
                        type="button"
                        onClick={submit}
                        disabled={!checked || processing}
                        data-test="secrecy-accept-button"
                    >
                        {processing && <Spinner />}
                        Qabul qilaman
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default SecrecyDeclarationDialog;
