# LexLink

**Advokat so'rovi — huquqiy asos bilan, bitta oynada.**

LexLink — advokatlar va yuristlar uchun platforma: sud qarori yoki advokat orderi asosida davlat tizimlariga
ma'lumot so'rovi yuboriladi, tizim huquqiy asos shaxs, ma'lumot turi va muddatni qamrashini tekshiradi,
javob esa shifrlangan holda bitta oynada ko'rsatiladi. Har bir ko'rish audit jurnaliga yoziladi.

Lex Hackathon 2026 (1-trek: huquqiy xizmatlar) uchun MVP.
Demo: **https://hackathon.john.uz** — barcha shaxslar, raqamlar va idoralar uydirma.

> Davlat API'lari hozircha mock (`/mock-gov/v1`). Haqiqiy integratsiya uchun faqat
> `App\Services\Gov\GovDataProvider` implementatsiyasi almashtiriladi.

---

## Asosiy imkoniyatlar

- **OneID orqali kirish** (mock), advokatlik litsenziyasini reyestrdan tekshirish; litsenziya to'xtatilsa imzo huquqi darhol o'chadi
- **Ishlar va jamoa** — advokat yuristni taklif qiladi, har bir ish bo'yicha javobni ko'rish ruxsatini beradi
- **Huquqiy asos tekshiruvi** — sud qarori/ajrimi yoki elektron advokat orderi reyestrdan tekshiriladi; asos qamramagan so'rov yuborilmaydi
- **8 tur so'rov, 5 soha** — sud ishi, soliq (ochiq qism), ish haqi, ko'chmas mulk, avtotransport, yuridik shaxslardagi ulushlar, bank hisobvaraqlari, chetga chiqish
- **ERI imzo (demo), navbat va real vaqt holati** — Redis queue, Reverb orqali "Jarayonda → Tayyor"
- **Javob** — qisqa xulosa, to'liq jadval, suv belgili PDF; to'liq javob 30 kundan keyin avtomatik o'chiriladi
- **Javob muddati nazorati** — Advokatura to'g'risidagi Qonun 7¹-modda: 15 kun, sud jarayoni boshlangan bo'lsa 3 ish kuni
- **AI yordamchi** (Gemini + lex.uz bilimlar bazasi) — so'rov matni, javob tahlili, aliment hisobi (Oila kodeksi 99-modda, hisob PHP'da), da'vo arizasi loyihasi
- **AI egasizlantirish** (ShMQ 16-modda) — tashqi AI'ga faqat egasizlantirilgan matn ketadi; shaxsiy ma'lumot aniqlansa so'rov bloklanadi; "AI'ga nima yuborildi?" ko'rinishi
- **Mijoz portali** — ishonch bildiruvchi order orqali o'z ishining holati va qisqa xulosasini ko'radi
- **Tariflar va balans** — obuna, so'rov uchun to'lov, rad etilsa pul qaytariladi; javobni ko'rish bepul
- **Maxsus imkoniyatlar** — shrift kattaligi, yuqori kontrast, kulrang rejim

## Huquqiy xarita

| Soha | Ma'lumot | Asos |
|---|---|---|
| Sud ishlari | Ish holati | Advokat orderi yetarli |
| Soliq va daromad | Soliq ma'lumotlari (ochiq qism) | Advokat orderi yetarli (SK 29-m., 1–8-bandlar) |
| | Ish haqi / daromad | Faqat sud qarori (soliq siri) |
| Mulkiy huquqlar | Ko'chmas mulk, avtotransport, ulushlar | Advokat orderi yetarli |
| Bank siri | Hisobvaraqlar, qoldiq, aylanma | Faqat sud qarori (Bank siri to'g'risidagi Qonun 10-m.) |
| Chetga chiqish | Chegaradan o'tish | Faqat sud qarori (ShMQ 25-m.) |

Yagona manba: `app/Support/LegalMatrix.php`. Ilovada: `/legal-map`.
Xarita lex.uz matnlari asosida tuzilgan; aniq tartib Adliya vazirligi va Advokatlar palatasi bilan tasdiqlanishi kerak.

## Texnologiyalar

Laravel 13 · Inertia v3 + React 19 + TypeScript · Tailwind v4 · MySQL 8 · Redis + Horizon · Laravel Reverb ·
dompdf · Gemini (o'z RAG'imiz, lex.uz'dan 10 ta qonun) · Docker Compose · GitHub Actions.

## Lokal ishga tushirish

Talablar: PHP 8.4, Composer, Node 22+, SQLite (yoki MySQL), ixtiyoriy Redis.

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
touch database/database.sqlite        # .env.example SQLite ishlatadi
php artisan migrate --seed            # demo foydalanuvchilar, mock reyestrlar, tariflar
php artisan lex:import --from-snapshot  # lex.uz bilimlar bazasi (oflayn snapshot)
npm run build

# ilova o'zini HTTP orqali chaqiradi (mock API), shuning uchun bir nechta worker kerak
PHP_CLI_SERVER_WORKERS=4 php artisan serve
```

AI uchun `.env` ga `GEMINI_API_KEY=` qo'shing; kalit bo'lmasa deterministik shablonlar ishlaydi.

### Demo shaxslar

Kirish sahifasidagi "Demo rejimi" ro'yxatidan tanlanadi:

| Rol | Ism | Izoh |
|---|---|---|
| Advokat | Rustamova Dilnoza | litsenziya AL-00123, faol |
| Yurist | Toshpulatov Jasur | advokat jamoasida |
| Advokat | Xolmatov Sherzod | litsenziya AL-00087, to'xtatilgan |
| Mijoz | Karimova Nilufar | order ORD-2026-000457 |

Demo ish: "Aliment undirish — Karimova N.", sud ajrimlari `2-1001-2611/0457` (ish haqi, sud ishi) va
`2-1001-2611/0458` (bank, chetga chiqish). Batafsil: [`docs/mock-gov-api.md`](docs/mock-gov-api.md).

## Testlar

```bash
php artisan test        # 230+ test
vendor/bin/pint --test  # kod stili
npx tsc --noEmit        # TypeScript
```

## Ish tartibi va deploy

`main` himoyalangan. O'zgarishlar faqat pull request orqali kiradi:

1. `feature/<nom>` branch → `main` ga pull request
2. **Tests** (Pint, tsc, build, PHPUnit) yashil bo'lishi shart
3. Merge → GitHub Actions testlarni qayta o'tkazadi va aynan shu commit'ni production'ga avtomatik deploy qiladi

Batafsil: [`docs/DEPLOY.md`](docs/DEPLOY.md).

## Hujjatlar

- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — arxitektura, modullar, majburiy yuridik qoidalar
- [`docs/diagrams/`](docs/diagrams/) — texnik flowchartlar (arxitektura, so'rov oqimi, asos tekshiruvi, AI egasizlantirish, ER diagramma va boshqalar)
- [`docs/DESIGN.md`](docs/DESIGN.md) — dizayn tizimi
- [`docs/mock-gov-api.md`](docs/mock-gov-api.md) — mock davlat API'lari va demo ma'lumotlar
- [`docs/DEPLOY.md`](docs/DEPLOY.md) — CI/CD va server
- [`docs/presentation/`](docs/presentation/) — loyiha taqdimoti (PDF)

## Cheklovlar (MVP)

Davlat API'lari, OneID, ERI imzo va Click/Payme to'lovlari — demo imitatsiya. Keyingi bosqich: E-IMZO,
haqiqiy idora API'lari ("Yuridik yordam" / Raqamli ma'lumotlar platformasi), shaxsiy ma'lumotlar bazasini
ro'yxatdan o'tkazish, to'lov merchant shartnomasi va fiskal chek.
