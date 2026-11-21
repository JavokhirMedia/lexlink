# Mock-gov API (fictional government systems)

Everything under `/mock-gov/v1/*` simulates government agencies (OneID, advocate registry, courts,
tax/pension, cadastre, vehicle registry, legal-entity register, banks, border service). **All persons, numbers and organisations are invented.**

The app reaches these endpoints only through `App\Services\Gov\GovDataProvider`
(bound to `App\Services\Gov\MockGovProvider`, HTTP against `MOCK_GOV_BASE_URL`).

| Setting (`config/lexlink.php` → `mock_gov`) | Env | Default |
|---|---|---|
| `base_url` | `MOCK_GOV_BASE_URL` | `APP_URL/mock-gov/v1` |
| `api_key` | `MOCK_GOV_API_KEY` | `mock-gov-demo-key` |
| `oneid_client_id` / `oneid_client_secret` | `ONEID_CLIENT_ID` / `ONEID_CLIENT_SECRET` | `lexlink-demo` / `lexlink-demo-secret` |
| `delay_min` / `delay_max` | — | 2 / 8 seconds |
| `signing_key` (optional, read with fallback) | — | `mock-gov-agency-signing-key` |

> **Local dev:** `php artisan serve` is single-threaded, so the app calling itself over HTTP deadlocks.
> Run `PHP_CLI_SERVER_WORKERS=4 php artisan serve` (or `composer dev` with that env), or point
> `MOCK_GOV_BASE_URL` at a second server. Production (nginx + php-fpm) is fine.

All timestamps are ISO-8601 in Tashkent time (`+05:00`).

---

## 1. OneID (OAuth2 authorization code)

No API key — OAuth2 credentials instead.

### `GET /oneid/authorize`
Query: `client_id`, `redirect_uri`, `state`, optional `login_hint=<PINFL>`.

- With a known `login_hint` the request is auto-approved ("just one button") and the browser is
  redirected to `redirect_uri?code=...&state=...`. The code is valid 5 minutes, single use.
- Without `login_hint` (or unknown PINFL) a neutral "OneID" page lists demo persons to pick.
- Wrong `client_id` / invalid `redirect_uri` → 400.

### `POST /oneid/token`
Form or JSON: `grant_type=authorization_code`, `code`, `redirect_uri`, `client_id`, `client_secret`
(HTTP Basic client auth also accepted).
```json
{"access_token":"...","token_type":"Bearer","expires_in":3600,"scope":"myportal"}
```
Errors: 401 `invalid_client`, 400 `invalid_grant` (unknown / used / expired code, redirect mismatch).

### `GET /oneid/userinfo`  (`Authorization: Bearer <token>`)
```json
{"sub":"oneid-…","pinfl":"41505880261034","first_name":"Dilnoza","last_name":"Rustamova",
 "middle_name":"Akmalovna","full_name":"Rustamova Dilnoza Akmalovna","birth_date":"1988-05-15","phone":"+998901234501"}
```
`sub` is stable per PINFL (`App\Models\Mock\MockPerson::oneIdSubFor($pinfl)`); demo users are pre-created with it.

---

## 2. Agency APIs — header `X-Api-Key` required

Missing/wrong key → `401 {"error":"unauthorized","message":"..."}`. JSON only, no session/CSRF.

### `GET /health` (no key)
`{"status":"ok","service":"mock-gov","time":"..."}`

### `GET /advocates/{license}` — advocate licence registry
```json
{"license_number":"AL-00123","full_name":"Rustamova Dilnoza Akmalovna","pinfl":"41505880261034",
 "status":"active","bureau_name":"\"Rustamova va hamkorlar\" advokatlik byurosi",
 "issued_at":"2014-06-10","revoked_at":null,"checked_at":"..."}
```
`status`: `active | revoked | suspended`. Unknown → 404.

### `GET /court/decisions/{number}` — court decision registry
Number may contain `/` (e.g. `/court/decisions/2-1001-2611/0457`).
```json
{"number":"2-1001-2611/0457","case_number":"2-1001-2610/0398","court":"Toshkent shahar Yunusobod tumanlararo fuqarolik ishlari sudi",
 "judge":"Ergasheva M. T.","decision_type":"ajrim","subject":{"pinfl":"31207870261012","full_name":"Karimov Aziz Botirovich"},
 "allowed_types":["salary","court_case"],"requester_license":null,"issued_at":"2026-11-10","expires_at":"2027-05-10",
 "is_valid":true,"summary":"...","checked_at":"..."}
```
`requester_license` (if set) means the decision authorises only that advocate. Unknown → 404.

### `GET /court/cases/{number}` — court case card
`{case_number, court, judge, category, status, parties:[{role,name,pinfl(masked)}], hearings:[{date,time,room,note}], decisions:[{number,date,type,summary}]}`

### `POST /requests` — submit data request
```json
{"type":"salary","subject_pinfl":"31207870261012",
 "legal_basis":{"type":"court_decision","number":"2-1001-2611/0457"},
 "period":{"from":"2026-05","to":"2026-10"},
 "requester_license":"AL-00123","params":{}}
```
`type`: `court_case | tax_info | salary | property | vehicle | business_shares | bank_accounts | border_crossing`
(same values as `App\Enums\DataRequestType`, grouped by domain in `App\Support\LegalMatrix`); `legal_basis.type`: `court_decision | advocate_order`.
`period` (`YYYY-MM`) filters `salary` and `border_crossing`; `bank_accounts` turnover always covers the last 6 months.
For `court_case`, `params.court_case_number` selects the case (otherwise the latest case where the subject is a party).
Response `201 {"request_id":"GOV-2026-000123","status":"accepted","accepted_at":"..."}`. Malformed → 422.

The agency **validates independently** and, after the delay, sets `rejected` with an Uzbek `rejection_reason` when:
- licence unknown or not active (revoked);
- a **court-only** type with an advocate order: `salary` (soliq siri), `bank_accounts` (bank siri — Bank siri to'g'risidagi Qonun 10-modda),
  `border_crossing` (shaxsiy hayot — ShMQ 25-modda). Reason always ends with "Advokat orderi yetarli emas.";
- decision unknown, expired, issued for another subject, doesn't list the type, or bound to another advocate;
- subject PINFL / court case not found.

### `GET /requests/{id}` — poll
- before `ready_at` (now + 2..8 s): `{"request_id","status":"processing","type","expected_at"}`
- rejected: `{"request_id","status":"rejected","type","rejection_reason","rejected_at"}`
- completed:
```json
{"request_id":"GOV-2026-000123","status":"completed","type":"salary",
 "subject":{"pinfl":"3***********12","full_name":"Karimov A. B."},
 "data":{...},"issued_at":"2026-11-20T12:00:08+05:00","signature":"<hex>","signature_alg":"HMAC-SHA256"}
```
`signature` = `hash_hmac('sha256', json_encode(data, UNESCAPED_UNICODE|UNESCAPED_SLASHES), signing_key)`.

`data` shapes:
- **salary** `{employers:[{name,tin}], months:[{period,employer,amount,pension_contribution}], total, average, period:{from,to}}`
  — `months` has one row per employer per month; `average` = total / number of distinct months.
- **property** `{objects:[{cadastral_number,type,address,area,share,registered_at,encumbrances:[{type,holder,since}]}]}`
- **vehicle** `{vehicles:[{model,year,plate,color,vin(masked),registered_at,pledge:{holder,since}|null}]}`
- **court_case** `{case_number,court,judge,category,status,parties,hearings,decisions}`
- **tax_info** `{tin, tax_regime, vat_payer, individual_entrepreneur:{status,activity}, tax_debt, taxes_paid_last_year, registered_at, participations:[{name,tin,share_percent,status}]}`
  — only the part that is **not** a tax secret (Soliq kodeksi 29-modda, 1–8-bandlar).
- **business_shares** `{companies:[{name,tin,share_percent,charter_capital,share_value,since,status}]}` — `share_value` = charter × share %.
- **bank_accounts** `{accounts:[{bank,account(masked 20206000****4417),currency,balance,turnover_in,turnover_out,opened_at,restrictions:[{type,by,since}]}],
  totals_by_currency:{UZS:{balance,turnover_in,turnover_out,accounts},USD:{…}}, turnover_period:{from,to,months:6}, as_of}` — banks are fictional.
- **border_crossing** `{crossings:[{direction:exit|entry,date,country,checkpoint}], currently_abroad, last_exit:{direction,date,country,checkpoint}|null,
  days_abroad_last_12m, period:{from,to}}` — `currently_abroad` / `last_exit` / days are computed from the full history (not the period window).

---

## 3. Demo data (seeded by `php artisan db:seed`)

### Demo users (log in via OneID with `login_hint`, or e-mail + password `password`)
| Role | Name | PINFL | Licence | E-mail |
|---|---|---|---|---|
| Advokat (faol) | Rustamova Dilnoza Akmalovna | `41505880261034` | `AL-00123` active | dilnoza.rustamova@demo.lexlink.uz |
| Yurist | Toshpulatov Jasur Baxtiyorovich | `32208950261047` | — | jasur.toshpulatov@demo.lexlink.uz |
| Advokat (to'xtatilgan) | Xolmatov Sherzod Ravshanovich | `30311790261058` | `AL-00087` revoked 2026-10-01 | sherzod.xolmatov@demo.lexlink.uz |
| Admin | Abdullayeva Malika Farhodovna | `42709920261069` | — | admin@demo.lexlink.uz |

Pre-created: advocate profile (verified), wallet 300 000 so'm (advocate + revoked advocate), active
"Advokat" subscription for Rustamova, accepted advocate↔lawyer link, case **"Aliment undirish — Karimova N."**
(court case `2-1001-2610/0398`) owned by Rustamova, lawyer member with `can_view_responses = true` and
secrecy **not yet declared**. **No legal basis** — entered live in the demo.

### Demo scenario
| Who | PINFL | Notes |
|---|---|---|
| Karimov Aziz Botirovich (alimony debtor) | `31207870261012` | Salary 2025-11..2026-10 at "Namuna" MChJ (6.5 mln, 7.2 mln in 2026-03) + 2026-05..2026-10 at "Toshkent Qurilish Servis" MChJ (2.8 mln); flat `10:01:05:01:02:0012`; Chevrolet Cobalt `01 A 123 BC`; **not a YaTT**, STIR `487120345`, tax debt **1 200 000**; **40 %** in "Karimov Savdo Servis" MChJ (charter 50 mln → share 20 mln); accounts "Namuna Bank" ATB `20206000****4417` **18 436 500 UZS** + "Sharq Kapital Bank" ATB `20206840****0932` **2 350 USD**; border: Turkiya 2026-03-02 → 2026-03-16, then **left for Rossiya (Moskva) 2026-08-14 via Toshkent xalqaro aeroporti — not returned (currently abroad)** |
| Karimova Nilufar Shavkatovna (client) | `42303910261025` | claimant |
| Raximov Bekzod Ulug'bekovich | `31804850261076` | 2 properties (1 mortgaged) + Chevrolet Malibu 2 (pledged) |

Steps: advocate adds basis `court_decision` **`2-1001-2611/0457`** → verifies (subject Karimov, allowed
`salary, court_case`, valid until 2027-05-10) → salary request for 2026-05..2026-10 completes
(total 55 800 000, average 9 300 000) → **vehicle request is blocked** (decision doesn't cover it; agency also rejects).

Domain scenario (mentor feedback "sohalar bo'yicha"): second ajrim **`2-1001-2611/0458`** (same case, valid until 2027-05-10) covers
`bank_accounts, border_crossing` → bank request shows 18.4 mln UZS + 2 350 USD; border request shows "hozir chet elda (Rossiya) 2026-08-14 dan beri".
With an **advocate order** `tax_info` and `business_shares` go through (soliq siri emas), while `bank_accounts` / `border_crossing` / `salary`
are blocked in LexLink and rejected by the agency.

Other seeded registries: tax profile for all 30 persons (~18 % YaTT); business shares for ~25 % (fixed: Raximov 2, Sobirov, Jo'rayev, Mirzayev);
1–2 bank accounts per person (Sobirov's account has an ijro xatlovi); border crossings for ~40 % (all returned; Sobirov via Oybek CHP).

### Court decisions (11)
| Number | Subject (PINFL) | Allowed | Valid until | Note |
|---|---|---|---|---|
| `2-1001-2611/0457` | Karimov Aziz `31207870261012` | salary, court_case | 2027-05-10 | **demo** |
| `2-1001-2611/0458` | Karimov Aziz `31207870261012` | bank_accounts, border_crossing | 2027-05-10 | **demo (domains)** |
| `2-1004-2609/0812` | Raximov Bekzod `31804850261076` | property, vehicle | 2027-03-15 | |
| `2-1001-2502/0133` | Hasanov Bobur `31105700262066` | salary | 2025-08-14 | **expired** |
| `2-1103-2504/0271` | Ismoilov Rustam `32805940262088` | property, vehicle | 2026-01-20 | **expired** |
| `2-1007-2608/0302` | Sobirov Jamshid `30806900262099` | salary, property, vehicle, court_case, bank_accounts | 2027-02-28 | |
| `2-1801-2610/0544` | Mirzayev Ulug'bek `32807670262044` | property | 2027-04-01 | |
| `2-1004-2610/0911` | Umarov Elyor `31701760262111` | vehicle, court_case | 2027-01-31 | hal qiluv qarori |
| `2-1001-2609/0377` | Qodirov Doniyor `31312990262055` | salary | 2027-03-20 | only for advocate `AL-00045` |
| `2-1103-2610/0418` | Jo'rayev Islom `30910980262122` | salary, property | 2027-06-30 | |
| `2-1007-2611/0350` | Abdurahmonov Timur `31806820262133` | salary, court_case | 2027-05-31 | |

### Advocate licences (5)
`AL-00123` Rustamova (active) · `AL-00087` Xolmatov (**revoked**) · `AL-00045` Yusupov Otabek `30504760262000` (active) ·
`AL-00156` Nazarov Javohir `30202780262011` (active) · `AL-00201` Saidov Farrux `30203910262022` (active)

### Court cases (15)
`2-1001-2610/0398` (demo alimony), `2-1004-2608/0655`, `2-1001-2501/0047`, `2-1103-2503/0190`, `2-1007-2607/0231`,
`2-1801-2609/0488`, `2-1004-2606/0560`, `2-1001-2608/0320`, `2-1103-2609/0366`, `2-1007-2610/0297`,
`2-1001-2605/0215`, `2-1004-2611/0973`, `2-1801-2604/0302`, `2-1007-2609/0255`, `2-1103-2611/0471`.

### Quick curl
```bash
K='X-Api-Key: mock-gov-demo-key'; B=http://localhost:8000/mock-gov/v1
curl -H "$K" $B/advocates/AL-00087
curl -H "$K" $B/court/decisions/2-1001-2611/0457
curl -H "$K" -H 'Content-Type: application/json' -d '{"type":"salary","subject_pinfl":"31207870261012","legal_basis":{"type":"court_decision","number":"2-1001-2611/0457"},"period":{"from":"2026-05","to":"2026-10"},"requester_license":"AL-00123"}' $B/requests
curl -H "$K" $B/requests/GOV-2026-000001
```

---

## Advokat orderi — elektron orderlar reyestri (fictional "Advokatlar palatasi")

Legal ground: Advokatura to'g'risidagi Qonun **6-modda** (advokat hujjatlarni so'rash va olishga haqli) and
**7¹-modda "Advokat so'rovi"** — javob **15 kun** ichida; ma'lumot bo'lmasa **5 kun** ichida xabar; surishtiruv /
tergov / sud ishi boshlanganligi hujjati ilova qilinsa **3 ish kuni** (`App\Support\LegalMatrix::DEADLINES`).
Order-eligible types = `LegalMatrix::orderTypes()` (court_case, tax_info, property, vehicle, business_shares).
Salary, bank accounts, border crossing → court decision only.

### `GET /advocate-orders/{number}` (X-Api-Key)
```json
{"order_number":"ORD-2026-000457","license_number":"AL-00123","advocate_name":"Rustamova Dilnoza Akmalovna",
 "client":{"pinfl":"42303910261025","full_name":"Karimova Nilufar Shavkatovna","pinfl_masked":"4***********25"},
 "matter":"Aliment undirish","issued_at":"2026-11-05","status":"active",
 "registry":"Advokatlar palatasi — elektron orderlar reyestri (demo)","checked_at":"..."}
```
`status`: `active | cancelled`. Unknown → 404.

### `POST /advocate-orders` (X-Api-Key) — issue an e-order (demo of electronic issuance)
Body `{license_number, client_pinfl, client_name, matter}` → `201` with the order above (new number `ORD-YYYY-000901…`).
Inactive licence → `422 {"error":"license_inactive"}`; unknown licence → 404.

### Seeded orders (`AdvocateOrdersSeeder`)
| Number | Advocate | Client | Matter | Status |
|---|---|---|---|---|
| `ORD-2026-000457` | AL-00123 Rustamova | Karimova Nilufar Shavkatovna `42303910261025` | Aliment undirish | **active (demo)** |
| `ORD-2026-000312` | AL-00123 Rustamova | Hasanov Bobur `31105700262066` | Mehnat nizosi | cancelled |
| `ORD-2026-000398` | AL-00045 Yusupov | Qodirov Doniyor `31312990262055` | Mulk nizosi | active (other advocate → `mismatch` for Rustamova) |

App side: `GovDataProvider::advocateOrder()` / `issueAdvocateOrder()`; `LegalBasisVerifier` verifies an
`advocate_order` basis = exists + active + issuing licence == current advocate's licence (else `not_found` /
`mismatch` with an Uzbek `verification_error`). The subject (person whose data is requested) is entered by the
advocate together with a short "so'rov maqsadi" linked to the order matter.

Deadline (`data_requests.due_at`, `deadline_basis`) is set on submit: order → 15 days, or 3 working days
(`proceedings`) if the case's court case number exists in `/court/cases/{number}`; court decision → 15 days.

### Demo order path (pitch)
1. Login as **Rustamova Dilnoza** → case "Aliment undirish — Karimova N." → *Huquqiy asoslar* → *Asos qo'shish*.
2. (Optional) court decision `2-1001-2611/0457` → covers salary + court case only — vehicle is NOT covered.
3. Tab **Advokat orderi**: number `ORD-2026-000457` (or click *Elektron order berish (demo)* with client PINFL
   `42303910261025` to issue a new one), subject `31207870261012` *Karimov Aziz Botirovich*, purpose
   "Aliment undirish ishi bo'yicha javobgarning mol-mulkini aniqlash", tick *Ko'chmas mulk* + *Avtotransport*,
   validity +3 months → verified.
4. New request → *Avtotransport* for Karimov → sign → "Javob muddati: … gacha" (3 ish kuni — demo case
   `2-1001-2610/0398` is in the court registry) → completed → "Muddatida javob berildi".

## Client (ishonch bildiruvchi) access via the order

- Advocate: case page → *Huquqiy asoslar* → **Mijoz uchun kirish** → *Havola yaratish* (needs a verified order
  basis or this advocate's active registry order whose client PINFL is known) → copy the link.
- Client opens **`/client/access/{token}`** → OneID login (intended URL preserved) → access only if the OneID
  PINFL == order client PINFL (else 403 page). A brand-new OneID user gets role `client` only through this link;
  onboarding never offers it. Every view is audited (`client_access_granted`, `client_access_denied`, `client_viewed`).
- Client pages `/client`, `/client/cases/{id}`: case, advocate + licence, request timelines, deadline, and for
  completed requests ONLY the non-PII summary + masked AI summary — no payload, no PDF, no PINFLs.

**Seeded demo link:** `/client/access/demo-karimova-aliment-0457` (case "Aliment undirish — Karimova N.",
order `ORD-2026-000457`). Demo persona on the login page: **Karimova Nilufar Shavkatovna — Mijoz**
(`42303910261025`); she is pre-seeded as role `client` with the link already granted, so logging in as her opens `/client`.
