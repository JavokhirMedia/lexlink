@php
    $type = $r->type->value;
    $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $wm = $watermark;
@endphp
<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="utf-8">
<title>LexLink — Idora javobi #{{ $r->id }}</title>
<style>
    /* docs/DESIGN.md: ink neutrals, one state-blue accent, registry numbers in mono chips. DejaVu stays (dompdf + Uzbek glyphs). */
    @page { margin: 24mm 16mm 22mm 16mm; }
    * { font-family: 'DejaVu Sans', sans-serif; }
    body { font-size: 9.5pt; color: #111827; line-height: 1.45; }
    .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 8.5pt; }
    .reg { font-family: 'DejaVu Sans Mono', monospace; font-size: 8pt; border: 0.6pt solid #E3E7EE; border-radius: 1.5mm; padding: 0.3mm 1.4mm; color: #111827; white-space: nowrap; }
    .watermark { position: fixed; top: 38%; left: -10%; width: 120%; text-align: center;
        transform: rotate(-32deg); color: #0A5BD8; opacity: 0.07; font-size: 16pt; font-weight: bold; line-height: 1.5; z-index: -1; }
    header { position: fixed; top: -18mm; left: 0; right: 0; height: 13mm; border-bottom: 0.6pt solid #E3E7EE; }
    header .inst { font-size: 6.8pt; color: #4B5565; text-transform: uppercase; }
    header .brand { font-size: 12pt; font-weight: bold; color: #111827; }
    header .meta { position: absolute; right: 0; top: 1mm; font-size: 7.5pt; color: #4B5565; text-align: right; line-height: 1.5; }
    footer { position: fixed; bottom: -16mm; left: 0; right: 0; height: 10mm; border-top: 0.6pt solid #E3E7EE;
        font-size: 7pt; color: #6B7487; padding-top: 2mm; }
    .rule { height: 0.9mm; background: #0A5BD8; width: 18mm; margin: 2mm 0 3mm; }
    h1 { font-size: 13pt; margin: 3mm 0 1mm; color: #111827; }
    h2 { font-size: 10.5pt; margin: 7mm 0 2mm; color: #111827; }
    .muted { color: #4B5565; }
    table { width: 100%; border-collapse: collapse; margin-top: 1.5mm; }
    th, td { border-bottom: 0.6pt solid #E3E7EE; padding: 1.8mm 2mm; text-align: left; vertical-align: top; font-size: 8.5pt; }
    th { background: #EEF1F6; color: #4B5565; font-weight: normal; border-bottom: 0.6pt solid #D5DBE4; }
    td.num, th.num { text-align: right; }
    table.kv td { border-bottom: 0.6pt solid #E3E7EE; }
    table.kv td:first-child { width: 36%; color: #4B5565; }
    .total td { font-weight: bold; background: #F5F7FA; }
    .stamp { margin-top: 10mm; }
    .stamp td { border: none; padding: 0; font-size: 8.5pt; }
    .sigline { border-bottom: 0.6pt solid #111827; width: 60mm; height: 8mm; }
    .demo { display: inline-block; border: 0.6pt solid #F2D9A6; background: #FFF6E5; color: #9A5B00; padding: 0.3mm 1.6mm; font-size: 7.5pt; border-radius: 1.5mm; }
</style>
</head>
<body>
<div class="watermark">
    {{ $wm['advocate'] }} · Litsenziya {{ $wm['license'] ?? '—' }}<br>
    Yuklab oldi: {{ $wm['downloaded_by'] }} · {{ $wm['at'] }}<br>
    So'rov #{{ $wm['request_id'] }} · {{ $wm['gov_request_id'] }}
</div>

<header>
    <div class="inst">Advokat so'rovlari yagona axborot tizimi</div>
    <div class="brand">LexLink <span class="muted" style="font-weight: normal; font-size: 9pt">· Idora javobi (demo)</span></div>
    <div class="meta">So'rov #{{ $r->id }} · <span class="mono">{{ $r->gov_request_id ?? '—' }}</span><br>Yaratildi: {{ $generatedAt->format('d.m.Y H:i') }}</div>
</header>

<footer>
    Ushbu hujjat demo maqsadida yaratilgan, barcha ma'lumotlar uydirma. Hujjat advokat siri hisoblanadi —
    uchinchi shaxslarga berish taqiqlanadi. Suv belgisi: {{ $wm['downloaded_by'] }}, {{ $wm['at'] }}.
</footer>

<h1>{{ $r->type->agency() }}: {{ $r->type->label() }} bo'yicha ma'lumotnoma</h1>
<div class="muted">Idoralararo so'rovga javob <span class="demo">demo</span></div>
<div class="rule"></div>

<h2>So'rov rekvizitlari</h2>
<table class="kv">
    <tr><td>Ish</td><td>{{ $r->legalCase->title }}@if ($r->legalCase->court_case_number) <span class="reg">{{ $r->legalCase->court_case_number }}</span>@endif</td></tr>
    <tr><td>Shaxs</td><td>{{ $payload['subject']['full_name'] ?? $r->subject_name ?? '—' }} · JShShIR <span class="reg">{{ $payload['subject']['pinfl'] ?? $r->subject_pinfl_masked }}</span></td></tr>
    <tr><td>Huquqiy asos</td><td>{{ $r->legalBasis?->type->label() }} <span class="reg">{{ $r->legalBasis?->number }}</span>{{ $r->legalBasis?->court_name ? ', '.$r->legalBasis->court_name : '' }}</td></tr>
    <tr><td>So'rovchi advokat</td><td>{{ $r->signer?->name }}, litsenziya <span class="reg">{{ $r->signer?->advocateProfile?->license_number }}</span></td></tr>
    <tr><td>Imzolangan</td><td>{{ $r->signed_at?->setTimezone('Asia/Tashkent')->format('d.m.Y H:i') }} · ERI (demo) <span class="mono muted">{{ substr((string) $r->signature, 0, 20) }}…</span></td></tr>
    <tr><td>Javob olingan</td><td>{{ $response->received_at?->setTimezone('Asia/Tashkent')->format('d.m.Y H:i') }}</td></tr>
</table>

@if ($type === 'salary')
    <h2>Ish haqi va daromadlar ({{ $summary['period_from'] ?? '' }} — {{ $summary['period_to'] ?? '' }})</h2>
    <table>
        <tr><th>Davr</th><th>Ish beruvchi</th><th class="num">Hisoblangan ish haqi, so'm</th><th class="num">Pensiya badali, so'm</th></tr>
        @foreach ($data['months'] ?? [] as $m)
            <tr><td>{{ $m['period'] ?? '' }}</td><td>{{ $m['employer'] ?? '' }}</td><td class="num">{{ $fmt($m['amount'] ?? 0) }}</td><td class="num">{{ $fmt($m['pension_contribution'] ?? 0) }}</td></tr>
        @endforeach
        <tr class="total"><td colspan="2">Jami ({{ $summary['months_count'] ?? 0 }} oy, {{ $summary['employers_count'] ?? 0 }} ish beruvchi)</td><td class="num">{{ $fmt($summary['total'] ?? 0) }}</td><td class="num">{{ $fmt($summary['pension_total'] ?? 0) }}</td></tr>
        <tr class="total"><td colspan="2">O'rtacha oylik daromad</td><td class="num">{{ $fmt($summary['average_monthly'] ?? 0) }}</td><td></td></tr>
    </table>
    @if (! empty($data['employers']))
        <h2>Ish beruvchilar</h2>
        <table><tr><th>Nomi</th><th>STIR</th></tr>
            @foreach ($data['employers'] as $e)<tr><td>{{ $e['name'] ?? '' }}</td><td>{{ $e['tin'] ?? '' }}</td></tr>@endforeach
        </table>
    @endif
@elseif ($type === 'property')
    <h2>Ko'chmas mulk obyektlari</h2>
    <table>
        <tr><th>Kadastr raqami</th><th>Turi</th><th>Manzil</th><th class="num">Maydon, m²</th><th>Ulush</th><th>Ro'yxatga olingan</th><th>Cheklovlar</th></tr>
        @forelse ($data['objects'] ?? [] as $o)
            <tr><td>{{ $o['cadastral_number'] ?? '' }}</td><td>{{ $o['type'] ?? '' }}</td><td>{{ $o['address'] ?? '' }}</td><td class="num">{{ $o['area'] ?? '' }}</td><td>{{ $o['share'] ?? '' }}</td><td>{{ $o['registered_at'] ?? '' }}</td>
                <td>@forelse ($o['encumbrances'] ?? [] as $enc){{ $enc['type'] ?? '' }} ({{ $enc['holder'] ?? '' }}, {{ $enc['since'] ?? '' }})<br>@empty Yo'q @endforelse</td></tr>
        @empty
            <tr><td colspan="7">Shaxs nomida ko'chmas mulk qayd etilmagan</td></tr>
        @endforelse
    </table>
@elseif ($type === 'vehicle')
    <h2>Avtotransport vositalari</h2>
    <table>
        <tr><th>Rusumi</th><th>Yili</th><th>Davlat raqami</th><th>Rangi</th><th>VIN</th><th>Ro'yxatga olingan</th><th>Garov</th></tr>
        @forelse ($data['vehicles'] ?? [] as $v)
            <tr><td>{{ $v['model'] ?? '' }}</td><td>{{ $v['year'] ?? '' }}</td><td>{{ $v['plate'] ?? '' }}</td><td>{{ $v['color'] ?? '' }}</td><td>{{ $v['vin'] ?? '' }}</td><td>{{ $v['registered_at'] ?? '' }}</td>
                <td>{{ ! empty($v['pledge']) ? (($v['pledge']['holder'] ?? '').', '.($v['pledge']['since'] ?? '')) : "Yo'q" }}</td></tr>
        @empty
            <tr><td colspan="7">Shaxs nomida avtotransport qayd etilmagan</td></tr>
        @endforelse
    </table>
@elseif ($type === 'tax_info')
    <h2>Soliq ma'lumotlari (Soliq kodeksi 29-modda, soliq siri hisoblanmaydigan qism)</h2>
    <table class="kv">
        <tr><td>STIR</td><td>{{ $data['tin'] ?? '—' }}</td></tr>
        <tr><td>Soliq rejimi</td><td>{{ $data['tax_regime'] ?? '—' }}</td></tr>
        <tr><td>QQS to'lovchi</td><td>{{ ! empty($data['vat_payer']) ? 'Ha' : "Yo'q" }}</td></tr>
        <tr><td>YaTT holati</td><td>{{ $data['individual_entrepreneur']['status'] ?? '—' }}{{ ! empty($data['individual_entrepreneur']['activity']) ? ' — '.$data['individual_entrepreneur']['activity'] : '' }}</td></tr>
        <tr><td>Soliq qarzi, so'm</td><td>{{ $fmt($data['tax_debt'] ?? 0) }}</td></tr>
        <tr><td>O'tgan yili to'langan soliqlar, so'm</td><td>{{ $fmt($data['taxes_paid_last_year'] ?? 0) }}</td></tr>
    </table>
    @if (! empty($data['participations']))
        <h2>Yuridik shaxslardagi ishtirok</h2>
        <table><tr><th>Nomi</th><th>STIR</th><th class="num">Ulush, %</th><th>Holati</th></tr>
            @foreach ($data['participations'] as $p)<tr><td>{{ $p['name'] ?? '' }}</td><td>{{ $p['tin'] ?? '' }}</td><td class="num">{{ $p['share_percent'] ?? '' }}</td><td>{{ $p['status'] ?? '' }}</td></tr>@endforeach
        </table>
    @endif
@elseif ($type === 'business_shares')
    <h2>Yuridik shaxslardagi ulushlar</h2>
    <table>
        <tr><th>Yuridik shaxs</th><th>STIR</th><th class="num">Ulush, %</th><th class="num">Ustav fondi, so'm</th><th class="num">Ulush qiymati, so'm</th><th>Ta'sischi bo'lgan sana</th><th>Holati</th></tr>
        @forelse ($data['companies'] ?? [] as $c)
            <tr><td>{{ $c['name'] ?? '' }}</td><td>{{ $c['tin'] ?? '' }}</td><td class="num">{{ $c['share_percent'] ?? '' }}</td><td class="num">{{ $fmt($c['charter_capital'] ?? 0) }}</td><td class="num">{{ $fmt($c['share_value'] ?? 0) }}</td><td>{{ $c['since'] ?? '' }}</td><td>{{ $c['status'] ?? '' }}</td></tr>
        @empty
            <tr><td colspan="7">Shaxs yuridik shaxslarda ishtirokchi sifatida qayd etilmagan</td></tr>
        @endforelse
        @if (! empty($data['companies']))
            <tr class="total"><td colspan="4">Ulushlarning nominal qiymati jami</td><td class="num">{{ $fmt($summary['total_share_value'] ?? 0) }}</td><td colspan="2"></td></tr>
        @endif
    </table>
@elseif ($type === 'bank_accounts')
    <h2>Bank hisobvaraqlari (bank siri)</h2>
    <table>
        <tr><th>Bank</th><th>Hisobvaraq</th><th>Valyuta</th><th class="num">Qoldiq</th><th class="num">Kirim ({{ $summary['turnover_months'] ?? 6 }} oy)</th><th class="num">Chiqim ({{ $summary['turnover_months'] ?? 6 }} oy)</th><th>Ochilgan</th><th>Cheklovlar</th></tr>
        @forelse ($data['accounts'] ?? [] as $a)
            <tr><td>{{ $a['bank'] ?? '' }}</td><td>{{ $a['account'] ?? '' }}</td><td>{{ $a['currency'] ?? '' }}</td><td class="num">{{ $fmt($a['balance'] ?? 0) }}</td><td class="num">{{ $fmt($a['turnover_in'] ?? 0) }}</td><td class="num">{{ $fmt($a['turnover_out'] ?? 0) }}</td><td>{{ $a['opened_at'] ?? '' }}</td>
                <td>@forelse ($a['restrictions'] ?? [] as $x){{ $x['type'] ?? '' }} ({{ $x['by'] ?? '' }}, {{ $x['since'] ?? '' }})<br>@empty Yo'q @endforelse</td></tr>
        @empty
            <tr><td colspan="8">Shaxs nomida bank hisobvarag'i topilmadi</td></tr>
        @endforelse
        @foreach ($summary['totals_by_currency'] ?? [] as $cur => $t)
            <tr class="total"><td colspan="3">Jami {{ $cur }} ({{ $t['accounts'] ?? 0 }} hisobvaraq)</td><td class="num">{{ $fmt($t['balance'] ?? 0) }}</td><td class="num">{{ $fmt($t['turnover_in'] ?? 0) }}</td><td class="num">{{ $fmt($t['turnover_out'] ?? 0) }}</td><td colspan="2"></td></tr>
        @endforeach
    </table>
@elseif ($type === 'border_crossing')
    <h2>Chegaradan o'tish ma'lumotlari</h2>
    <table class="kv">
        <tr><td>Hozir chet eldami</td><td>{{ ! empty($summary['currently_abroad']) ? 'Ha — '.($summary['last_exit_date'] ?? '').' dan beri ('.($summary['last_exit_country'] ?? '').'), '.($summary['days_abroad_current'] ?? 0).' kun' : "Yo'q" }}</td></tr>
        <tr><td>So'nggi 12 oyda chet elda</td><td>{{ $summary['days_abroad_last_12m'] ?? 0 }} kun</td></tr>
    </table>
    <table>
        <tr><th>Yo'nalish</th><th>Sana va vaqt</th><th>Davlat</th><th>O'tish punkti</th></tr>
        @forelse ($data['crossings'] ?? [] as $c)
            <tr><td>{{ ($c['direction'] ?? '') === 'exit' ? 'Chiqish' : 'Kirish' }}</td><td>{{ $c['date'] ?? '' }}</td><td>{{ $c['country'] ?? '' }}</td><td>{{ $c['checkpoint'] ?? '' }}</td></tr>
        @empty
            <tr><td colspan="4">Ko'rsatilgan davrda chegaradan o'tish qayd etilmagan</td></tr>
        @endforelse
    </table>
@else
    <h2>Sud ishi</h2>
    <table class="kv">
        <tr><td>Ish raqami</td><td>{{ $data['case_number'] ?? '—' }}</td></tr>
        <tr><td>Sud</td><td>{{ $data['court'] ?? '—' }}</td></tr>
        <tr><td>Sudya</td><td>{{ $data['judge'] ?? '—' }}</td></tr>
        <tr><td>Toifa</td><td>{{ $data['category'] ?? '—' }}</td></tr>
        <tr><td>Holati</td><td>{{ $data['status'] ?? '—' }}</td></tr>
    </table>
    @if (! empty($data['parties']))
        <h2>Taraflar</h2>
        <table><tr><th>Roli</th><th>F.I.Sh.</th><th>JShShIR</th></tr>
            @foreach ($data['parties'] as $p)<tr><td>{{ $p['role'] ?? '' }}</td><td>{{ $p['name'] ?? '' }}</td><td>{{ $p['pinfl'] ?? '' }}</td></tr>@endforeach
        </table>
    @endif
    @if (! empty($data['hearings']))
        <h2>Sud majlislari</h2>
        <table><tr><th>Sana</th><th>Vaqt</th><th>Xona</th><th>Izoh</th></tr>
            @foreach ($data['hearings'] as $h)<tr><td>{{ $h['date'] ?? '' }}</td><td>{{ $h['time'] ?? '' }}</td><td>{{ $h['room'] ?? '' }}</td><td>{{ $h['note'] ?? '' }}</td></tr>@endforeach
        </table>
    @endif
    @if (! empty($data['decisions']))
        <h2>Qarorlar</h2>
        <table><tr><th>Raqam</th><th>Sana</th><th>Turi</th><th>Mazmuni</th></tr>
            @foreach ($data['decisions'] as $d)<tr><td>{{ $d['number'] ?? '' }}</td><td>{{ $d['date'] ?? '' }}</td><td>{{ $d['type'] ?? '' }}</td><td>{{ $d['summary'] ?? '' }}</td></tr>@endforeach
        </table>
    @endif
@endif

<table class="stamp">
    <tr>
        <td style="width: 55%">
            Idora elektron imzosi (demo):<br>
            <span class="mono muted" style="font-size: 7pt">{{ $response->gov_signature ?? '—' }}</span>
        </td>
        <td>
            Advokat: {{ $r->signer?->name }}<br><br>
            <div class="sigline"></div>
            <span class="muted" style="font-size: 8pt">(imzo)</span>
        </td>
    </tr>
</table>
</body>
</html>
