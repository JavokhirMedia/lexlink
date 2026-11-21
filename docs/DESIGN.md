# LexLink — design contract (unslop redesign, Nov 2026)

Approved by the user. Every agent follows this. Method: the `unslop` skill (credit: unslop by Damir Sokolovsky, @heydamir, CC BY 4.0) — 9 passes in order: spacing, color, icons, copy, type, layout, details, visuals, motion. References: `/Users/john/.claude/skills/unslop/SKILL.md`, `references/signs.md`, `references/tokens.md`.

## Direction
- **Product.** Platform where Uzbek advocates and lawyers send legally-grounded data requests to state systems and receive answers securely, every view audited.
- **Character.** Rasmiy, aniq, xotirjam (official, exact, calm).
- **World.** Uzbekistan's e-government services (my.gov.uz, OneID, lex.uz): bright state blue, white official paper, registry numbers, stamps, girih geometry.
- **Moments** (the only places with rich color/detail): (1) the request status timeline + the "Asos qamraydi / bloklandi" legal-basis panel with a seal-like verified mark; (2) on landing/login: a code-built preview of a real agency response document on a very faint original girih pattern; (3) the legal map matrix.
- **Signature.** `RegistryChip` — every legal identifier (GOV-2026-000002, ORD-2026-000457, 2-1001-2611/0457, AL-00123, 3***********12) is rendered in IBM Plex Mono inside a thin bordered chip with a tiny seal glyph. Used consistently, nowhere else decorative.

## Tokens
- **Accent / primary:** `#0A5BD8` (white text passes AA). Hover `#084BB3`, pressed `#073E94`, soft surface `#EAF2FF`, focus ring `#0A5BD8` 2px + 2px offset. Accent covers a few % of a screen: primary action, current nav item, selection, one key figure.
- **Neutrals (ink, light):** page `#F5F7FA`, card `#FFFFFF`, raised/input `#EEF1F6`, hairline ≈ `#E3E7EE`, text primary `#111827`, secondary `#4B5565`, muted `#6B7487` (meta only).
- **Semantic (toned):** success `#16794A` on `#EAF7F0`; danger `#C42B2B` on `#FDEEEE`; warning `#9A5B00` on `#FFF6E5`. Only for meaning (verified / blocked / pending).
- **No dark mode.** Light only; force light (remove the system-theme switch and the appearance settings page/toggle).
- **Type:** IBM Plex Sans (UI), IBM Plex Mono (registry numbers, PINFL). No serif anywhere (remove Source Serif and `font-display`). Body **16/24**, secondary 14/20, meta 13/18 minimum (nothing below 13px except keycaps), page title 28/34 weight 600, section title 20/28 weight 600, hero 48–56/1.08 weight 600. Weights 400/500/600. Sentence case; the ONLY uppercase is the two-line institutional name in the header lockup (no wide tracking).
- **Numbers:** one formatter app-wide: `formatMoney()` in `components/brand/money.tsx` → `55 800 000 so'm` (space grouping, never commas). Tabular figures in tables/stats. Dates `20.11.2026` in UI.
- **Spacing:** 4px grid. Page padding 32 desktop / 16 mobile; card padding 24; section gap 32–48; list rows 44.
- **Radius:** controls 8, cards 12, panels 16, chips 6. Nested radius math.
- **Depth:** hairline borders; shadow only `0 1px 2px rgba(17,24,39,.05)`; floating menus `0 8px 24px rgba(17,24,39,.08)`.
- **Icons:** lucide only, 18px (16 in dense tables), stroke 1.5, text-role color, fixed icon box, 8–10 gap.
- **Motion:** one curve `cubic-bezier(0.22,1,0.36,1)`; 150ms hover, 200ms menus, 300ms entrances. Spinners only on things actually in progress. No pulse/ping, hover:scale, transition-all.

## Shell (layout)
1. **Utility bar** (36px, `#EEF1F6`, hairline bottom): left "Advokat so'rovlari yagona axborot tizimi · demo, barcha ma'lumotlar uydirma" (the ONLY demo notice in the shell — remove other DemoBadges from chrome); right: **Maxsus imkoniyatlar** (working panel: font size 100/112/125%, high contrast, grayscale; persisted in localStorage with try/catch; applied via classes on `<html>`), **Yordam** (`/help` simple guide page).
2. **Header** (72px, white, hairline): logo mark + two-line lockup — line 1 small uppercase `ADVOKAT SO'ROVLARI YAGONA AXBOROT TIZIMI` (secondary, 12–13px, normal tracking), line 2 `LexLink` (20px 600). Right: wallet balance (advocates), user menu (name, role, licence RegistryChip), logout. Mobile: menu button opens nav as a sheet.
3. **Sidebar** (264px, white, hairline right, full-height sticky below header): rows 40px, icon 18 + label 16, active = `#EAF2FF` surface + 3px `#0A5BD8` left bar + accent text. Licence/wallet card moves to header/user menu.
4. **Content**: max-width 1280, padding 32, page header (title 28/600 + description 16 secondary + actions right), breadcrumbs 14px above.
5. **Public pages** (landing, legal-map guest, login, client portal) use the same utility bar + header, no sidebar.
6. Never use the Uzbekistan state emblem, flag image, or real agency logos as branding (OneID/Click/Payme only as plain text). Girih pattern: original SVG, ≤ 4% opacity, landing hero + login only.

## Components (brand, shared)
`RegistryChip({ value, kind?: 'gov'|'order'|'decision'|'license'|'pinfl'|'case', copyable? })`, `StatusBadge` (restyle), `PageHeader`, `StatTile` (label + figure + comparison, no icons), `Money`, `AccessBadge` (order = neutral outline + check, court = danger-soft), `LegalBasisChip`, `EmptyState` (line icon + one sentence + one action), `Section` (title + description + content).

## Copy
Uzbek Latin, plain, no exclamation marks, no trailing periods on labels/buttons/titles, no hype. Titles name the context ("Bosh sahifa", "Ish: Aliment undirish — Karimova N.").
