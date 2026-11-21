/**
 * Lucide icon per data request type (LegalMatrix types). Use everywhere a type is iconised.
 *   <RequestTypeIcon type="bank_accounts" className="size-4" />
 */
import {
    Banknote,
    Building2,
    Car,
    FileQuestion,
    Gavel,
    Home,
    Plane,
    Receipt,
    Wallet,
} from "lucide-react";
import type { LucideIcon } from "lucide-react";
import type { DataRequestType } from "@/types/lexlink";

export const REQUEST_TYPE_ICONS: Record<DataRequestType, LucideIcon> = {
    court_case: Gavel,
    tax_info: Receipt,
    salary: Wallet,
    property: Home,
    vehicle: Car,
    business_shares: Building2,
    bank_accounts: Banknote,
    border_crossing: Plane,
};

export function requestTypeIcon(type: string): LucideIcon {
    return REQUEST_TYPE_ICONS[type as DataRequestType] ?? FileQuestion;
}

export function RequestTypeIcon({
    type,
    className,
}: {
    type: string;
    className?: string;
}) {
    const Icon = requestTypeIcon(type);

    return <Icon className={className} aria-hidden />;
}
