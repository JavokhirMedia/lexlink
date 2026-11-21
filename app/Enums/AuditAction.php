<?php

namespace App\Enums;

enum AuditAction: string
{
    case Login = 'login';
    case Logout = 'logout';
    case LicenseVerified = 'license_verified';
    case LicenseRevoked = 'license_revoked';
    case CaseCreated = 'case_created';
    case LawyerInvited = 'lawyer_invited';
    case LawyerJoined = 'lawyer_joined';
    case PermissionChanged = 'permission_changed';
    case SecrecyDeclared = 'secrecy_declared';
    case LegalBasisAdded = 'legal_basis_added';
    case LegalBasisVerified = 'legal_basis_verified';
    case RequestCreated = 'request_created';
    case RequestSigned = 'request_signed';
    case RequestSubmitted = 'request_submitted';
    case RequestBlocked = 'request_blocked'; // legal basis did not cover it
    case ResponseReceived = 'response_received';
    case ViewResponse = 'view_response';
    case ViewPayload = 'view_payload';
    case DownloadPdf = 'download_pdf';
    case AiAnalysis = 'ai_analysis';
    case AiDraftClaim = 'ai_draft_claim';
    case AiPiiBlocked = 'ai_pii_blocked'; // anonymisation leak guard stopped an outgoing AI request
    case WalletTopup = 'wallet_topup';
    case Charge = 'charge';
    case Refund = 'refund';
    case ResponsePurged = 'response_purged';
    case AdvocateOrderIssued = 'advocate_order_issued';
    case ClientLinkCreated = 'client_link_created';
    case ClientLinkRevoked = 'client_link_revoked';
    case ClientAccessGranted = 'client_access_granted';
    case ClientAccessDenied = 'client_access_denied';
    case ClientViewed = 'client_viewed';

    public function label(): string
    {
        return match ($this) {
            self::Login => 'Tizimga kirdi',
            self::Logout => 'Tizimdan chiqdi',
            self::LicenseVerified => 'Litsenziya tasdiqlandi',
            self::LicenseRevoked => "Litsenziya to'xtatilgan",
            self::CaseCreated => 'Ish yaratildi',
            self::LawyerInvited => 'Yurist taklif qilindi',
            self::LawyerJoined => "Yurist jamoaga qo'shildi",
            self::PermissionChanged => "Ruxsat o'zgartirildi",
            self::SecrecyDeclared => 'Advokat siri deklaratsiyasi imzolandi',
            self::LegalBasisAdded => "Huquqiy asos qo'shildi",
            self::LegalBasisVerified => 'Huquqiy asos tekshirildi',
            self::RequestCreated => "So'rov loyihasi yaratildi",
            self::RequestSigned => "So'rov imzolandi",
            self::RequestSubmitted => "So'rov idoraga yuborildi",
            self::RequestBlocked => "So'rov bloklandi (asos qamramaydi)",
            self::ResponseReceived => 'Javob qabul qilindi',
            self::ViewResponse => "Javob ko'rildi",
            self::ViewPayload => "To'liq javob ochildi",
            self::DownloadPdf => 'PDF yuklab olindi',
            self::AiAnalysis => 'AI tahlil',
            self::AiDraftClaim => "AI da'vo arizasi loyihasi",
            self::AiPiiBlocked => "AI so'rovi bloklandi (shaxsiy ma'lumot aniqlandi)",
            self::WalletTopup => "Balans to'ldirildi",
            self::Charge => 'Balansdan yechildi',
            self::Refund => 'Mablag\' qaytarildi',
            self::ResponsePurged => "Javob muddati tugab o'chirildi",
            self::AdvocateOrderIssued => 'Elektron advokat orderi berildi',
            self::ClientLinkCreated => 'Mijoz uchun kirish havolasi yaratildi',
            self::ClientLinkRevoked => 'Mijoz havolasi bekor qilindi',
            self::ClientAccessGranted => 'Mijozga ish holatini ko\'rish ruxsati berildi',
            self::ClientAccessDenied => 'Mijoz kirishi rad etildi',
            self::ClientViewed => "Mijoz ish holatini ko'rdi",
        };
    }
}
