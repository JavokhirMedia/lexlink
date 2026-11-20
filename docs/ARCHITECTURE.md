# LexLink — architecture & build contract

Hackathon MVP. Spec: `../LexLink — Texnik topshiriq (Lex Hackathon MVP).pdf`. UI language: **Uzbek (Latin)** only.
Design direction: official, "state-grade" — white / deep navy blue, calm, trustworthy, precise typography. No purple gradients, no emoji.

## Stack
Laravel 13 · Inertia v3 + React 19 + TS · Tailwind v4 + shadcn/ui (`resources/js/components/ui`) · Wayfinder (typed routes, `@/routes`, `@/actions`) · MySQL 8 · Redis · Horizon · Reverb (`@laravel/echo-react` already configured in `app.tsx`) · dompdf · Gemini (AI) · Docker Compose on VPS.

## Already in place (foundation — do not rewrite, extend)
- Migrations: `database/migrations/2026_11_20_000100_create_lexlink_core_tables.php`, `..._000200_create_mock_gov_tables.php`, users table has `oneid_sub, pinfl(encrypted), pinfl_hash, phone, role`. Need a column? Add a NEW migration in your module, don't edit these.
- Models (`app/Models`): User, AdvocateProfile, Organization, AdvocateLawyerLink, LegalCase (table `cases`; `case` is a PHP keyword), CaseMember, LegalBasis, DataRequest, RequestResponse, AiAnalysis, Plan, Subscription, Wallet, Transaction, AuditLog (append-only), LawDocument, LawChunk.
- Enums (`app/Enums`): UserRole, DataRequestType (labels, agency, acceptedBases, secrecyRegime), DataRequestStatus (state machine `canTransitionTo`), LegalBasisType, AuditAction (labels).
- `App\Support\Pinfl` — hash / mask / isValid. PII columns auto-hash + mask on save.
- `App\Services\AuditLogger::log(AuditAction, ?Model, meta, ?caseId, ?user)`.
- Interfaces: `App\Services\Gov\GovDataProvider` (+ `Dto\GovSubmission`, `GovApiException`), `App\Services\Ai\AiService`.
- Event `App\Events\RequestStatusUpdated` (ShouldBroadcastNow) on `private-case.{id}` and `private-user.{id}`, `broadcastAs` = `RequestStatusUpdated`. Channel auth in `routes/channels.php`.
- Middleware aliases: `onboarded` (role must be set), `role:advocate,lawyer`.
- Shared Inertia props: `auth.user` (id, name, email, phone, role, role_label, license{number,status}, can_sign), `auth.wallet_balance`, `ai_disclaimer`. TS: `resources/js/types/lexlink.ts`.
- `config/lexlink.php` — prices, retention, mock-gov settings, AI settings.
- Routes split per module (`routes/web.php` requires `auth.php, cases.php, requests.php, billing.php, ai.php, app.php`; `routes/mock-gov.php` mounted at `/mock-gov/v1`, name prefix `mock-gov.`, no middleware applied — add your own).

## Legal rules the code MUST enforce (this is the pitch)
1. **No basis, no request.** Submit button disabled + server-side 422 unless the case has a *verified, unexpired* legal basis whose subject PINFL == request subject PINFL and whose `allowed_types` includes the request type. Salary (tax secret) accepts **court_decision only**. Blocked attempts are audited (`RequestBlocked`).
2. **Only an advocate with an active licence signs & submits.** Lawyers prepare drafts. Revoked licence => signing rights removed immediately (check licence on sign).
3. **Lawyer sees responses only if** advocate granted `case_members.can_view_responses` for that case **and** the lawyer accepted the per-case secrecy declaration ("advokat sirini saqlash majburiyati", `secrecy_declared_at`). Admin sees metadata only, never payload.
4. **Every view / PDF download / sign / submit is audited.** Audit is append-only.
5. **PII**: payload + PINFL encrypted at rest; lists show masked PINFL; full payload purged after `response_retention_days` (summary + audit remain) — scheduled command.
6. **AI**: mask F.I.Sh. + PINFL before sending to Gemini; label every output with `ai_disclaimer`; numbers (average income, alimony) computed in PHP — Oila kodeksi 99-modda: 1 child 1/4, 2 children 1/3, 3+ children 1/2 of earnings.
7. **Money**: response is never paywalled; charge on submit, refund on rejection/technical failure.
8. All seed persons/numbers are fictional.

## Module ownership (parallel agents — touch only your files; shared files listed are append-only for you)
| Module | Owns |
|---|---|
| mock-gov | `routes/mock-gov.php`, `app/Http/Controllers/MockGov/*`, `app/Services/Gov/MockGovProvider.php`, `app/Models/Mock/*`, `database/seeders/*`, `database/factories/*` (except UserFactory edits are OK), `docs/mock-gov-api.md`, binding GovDataProvider in `AppServiceProvider` |
| auth-team | `routes/auth.php`, OneID client flow, onboarding, licence check, invites/team, audit journal page, `app/Http/Controllers/Auth/*`, `Team/*`, `Audit/*`, `pages/auth/*`, `pages/onboarding/*`, `pages/team/*`, `pages/audit/*`, Fortify cleanup, `tests/Feature/Auth/*` |
| cases | `routes/cases.php`, `app/Http/Controllers/Cases/*`, `app/Policies/LegalCasePolicy.php`, `app/Services/LegalBasisVerifier.php`, `pages/cases/*` (index, create, show — show page hosts a requests list section rendered by `components/requests/case-requests-panel.tsx` from requests agent) |
| requests | `routes/requests.php`, `app/Services/DataRequestService.php`, `app/Jobs/*Gov*`, `app/Jobs/AnalyzeResponseJob.php` (calls AiService), `app/Policies/DataRequestPolicy.php`, PDF (`resources/views/pdf/*`), purge command, `pages/requests/*`, `components/requests/*` |
| billing | `routes/billing.php`, `routes/app.php` (dashboard), `app/Services/BillingService.php`, `app/Http/Controllers/Billing/*`, `DashboardController`, `pages/billing/*`, `pages/dashboard.tsx` |
| ai | `routes/ai.php`, `app/Services/Ai/*` (GeminiAiService, PiiMasker, LawKnowledgeBase, AlimonyCalculator), `app/Console/Commands/LexImport*`, `storage/app/lex/*`, `pages/ai/*`, `components/ai/*` |
| design | `resources/css/app.css`, `components/app-*`, `components/nav-*`, `layouts/*`, `pages/welcome.tsx`, `components/brand/*`, sidebar navigation |
| devops | `docker/*`, `Dockerfile`, `docker-compose*.yml`, `deploy.sh`, `.env.production.example`, server setup |

Cross-module calls go through the service classes named above. If you need something from another module that doesn't exist yet, code against the documented signature below and leave a `// TODO(module)` — don't create the other module's file.

### Service signatures (contracts between modules)
```php
// requests
App\Services\DataRequestService
  create(LegalCase $case, User $by, array $data): DataRequest               // status=draft
  checkBasis(DataRequest $r): array{ok: bool, reason: ?string, basis: ?LegalBasis}
  requestSignature(DataRequest $r, User $by): DataRequest                     // draft -> awaiting_signature
  signAndSubmit(DataRequest $r, User $advocate): DataRequest                 // checks licence+basis, BillingService::chargeForRequest, -> submitted, dispatch SubmitGovRequestJob
  transition(DataRequest $r, DataRequestStatus $to, array $attrs = []): DataRequest  // emits RequestStatusUpdated + audit
// cases
App\Services\LegalBasisVerifier
  verify(LegalBasis $b): LegalBasis   // fills subject, allowed_types, expires_at, verification_status from GovDataProvider::courtDecision / advocate order rules
// billing
App\Services\BillingService
  walletFor(User $u): Wallet
  quoteForRequest(User $u): array{cost:int, from_subscription:bool}
  chargeForRequest(DataRequest $r, User $u): void   // throws App\Exceptions\InsufficientBalance
  refundForRequest(DataRequest $r): void
  chargeAi(User $u, string $kind): int              // returns cost charged (0 if within plan)
  topup(User $u, int $amount, string $provider): Transaction
// ai — App\Services\Ai\AiService (interface), bound to GeminiAiService
// gov — App\Services\Gov\GovDataProvider (interface), bound to MockGovProvider
```

### Pages (Inertia component names)
`welcome`, `auth/login`, `onboarding/role`, `onboarding/license`, `dashboard`, `cases/index`, `cases/create`, `cases/show`, `requests/create`, `requests/show` (status timeline + summary card + full table/JSON + PDF + AI panel), `team/index`, `invites/accept`, `audit/index`, `billing/index` (tariffs, balance, history), `billing/checkout` (Click/Payme mock), `ai/claim` (da'vo arizasi draft), `ai/assistant` (optional law Q&A).

### Sidebar navigation (design agent builds it)
Bosh sahifa `/dashboard` · Ishlar `/cases` · So'rovlar `/requests` · Jamoa `/team` (advocate) · Audit jurnali `/audit` · Tarif va balans `/billing` · Huquqiy yordamchi `/ai/assistant`.

## Conventions
- Wayfinder: after adding routes run `php artisan wayfinder:generate` and import from `@/routes/...` / `@/actions/...`.
- Tests: Pest/PHPUnit feature tests in `tests/Feature/<Module>/`, sqlite in-memory (phpunit.xml). Do NOT run `migrate:fresh` against the shared local DB while other agents work; use tests.
- Don't `git commit` — the lead integrates and commits.
- Money integers in so'm. Dates ISO. Format in UI with `Intl.NumberFormat('uz-UZ')`.
