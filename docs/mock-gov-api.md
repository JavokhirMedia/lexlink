# Mock-gov API (fictional government systems)

Everything under `/mock-gov/v1/*` simulates government agencies (OneID, advocate registry, courts,
tax/pension, cadastre, vehicle registry). **All persons, numbers and organisations are invented.**

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
`type`: `salary | property | vehicle | court_case`; `legal_basis.type`: `court_decision | advocate_order`.
For `court_case`, `params.court_case_number` selects the case (otherwise the latest case where the subject is a party).
Response `201 {"request_id":"GOV-2026-000123","status":"accepted","accepted_at":"..."}`. Malformed → 422.

The agency **validates independently** and, after the delay, sets `rejected` with an Uzbek `rejection_reason` when:
- licence unknown or not active (revoked);
- `salary` with anything other than a court decision (soliq siri);
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
| Karimov Aziz Botirovich (alimony debtor) | `31207870261012` | Salary 2025-11..2026-10 at "Namuna" MChJ (6.5 mln, 7.2 mln in 2026-03) + 2026-05..2026-10 at "Toshkent Qurilish Servis" MChJ (2.8 mln); flat `10:01:05:01:02:0012`; Chevrolet Cobalt `01 A 123 BC` |
| Karimova Nilufar Shavkatovna (client) | `42303910261025` | claimant |
| Raximov Bekzod Ulug'bekovich | `31804850261076` | 2 properties (1 mortgaged) + Chevrolet Malibu 2 (pledged) |

Steps: advocate adds basis `court_decision` **`2-1001-2611/0457`** → verifies (subject Karimov, allowed
`salary, court_case`, valid until 2027-05-10) → salary request for 2026-05..2026-10 completes
(total 55 800 000, average 9 300 000) → **vehicle request is blocked** (decision doesn't cover it; agency also rejects).

### Court decisions (10)
| Number | Subject (PINFL) | Allowed | Valid until | Note |
|---|---|---|---|---|
| `2-1001-2611/0457` | Karimov Aziz `31207870261012` | salary, court_case | 2027-05-10 | **demo** |
| `2-1004-2609/0812` | Raximov Bekzod `31804850261076` | property, vehicle | 2027-03-15 | |
| `2-1001-2502/0133` | Hasanov Bobur `31105700262066` | salary | 2025-08-14 | **expired** |
| `2-1103-2504/0271` | Ismoilov Rustam `32805940262088` | property, vehicle | 2026-01-20 | **expired** |
| `2-1007-2608/0302` | Sobirov Jamshid `30806900262099` | all four | 2027-02-28 | |
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
