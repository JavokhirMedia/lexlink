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
    @page { margin: 22mm 16mm 22mm 16mm; }
    * { font-family: 'DejaVu Sans', sans-serif; }
    body { font-size: 10pt; color: #0f1b33; line-height: 1.4; }
    .watermark { position: fixed; top: 38%; left: -10%; width: 120%; text-align: center;
        transform: rotate(-32deg); color: #1e3a6e; opacity: 0.09; font-size: 17pt; font-weight: bold; line-height: 1.5; z-index: -1; }
    header { position: fixed; top: -16mm; left: 0; right: 0; height: 12mm; border-bottom: 1.5pt solid #12264d; }
    header .brand { font-size: 12pt; font-weight: bold; color: #12264d; }
    header .meta { position: absolute; right: 0; top: 2mm; font-size: 8pt; color: #4b5873; text-align: right; }
    footer { position: fixed; bottom: -16mm; left: 0; right: 0; height: 10mm; border-top: 0.6pt solid #b8c1d3;
        font-size: 7.5pt; color: #4b5873; padding-top: 2mm; }
    h1 { font-size: 14pt; margin: 4mm 0 1mm; color: #12264d; }
    h2 { font-size: 11pt; margin: 6mm 0 2mm; color: #12264d; border-bottom: 0.6pt solid #b8c1d3; padding-bottom: 1mm; }
    .muted { color: #4b5873; }
    table { width: 100%; border-collapse: collapse; margin-top: 2mm; }
    th, td { border: 0.6pt solid #b8c1d3; padding: 1.6mm 2mm; text-align: left; vertical-align: top; font-size: 9pt; }
    th { background: #eef2f8; color: #12264d; font-weight: bold; }
    td.num, th.num { text-align: right; }
    table.kv td:first-child { width: 38%; background: #f6f8fb; color: #4b5873; }
    .total td { font-weight: bold; background: #f6f8fb; }
    .stamp { margin-top: 10mm; }
    .stamp td { border: none; padding: 0; font-size: 9pt; }
    .sigline { border-bottom: 0.6pt solid #0f1b33; width: 60mm; height: 8mm; }
    .demo { display: inline-block; border: 0.6pt solid #b08d2e; color: #7a5f12; padding: 0.5mm 2mm; font-size: 8pt; }
</style>
</head>
<body>
<div class="watermark">
    {{ $wm['advocate'] }} · Litsenziya {{ $wm['license'] ?? '—' }}<br>
    Yuklab oldi: {{ $wm['downloaded_by'] }} · {{ $wm['at'] }}<br>
    So'rov #{{ $wm['request_id'] }} · {{ $wm['gov_request_id'] }}
</div>

<header>
    <div class="brand">LexLink — Idora javobi (DEMO)</div>
    <div class="meta">So'rov #{{ $r->id }} · Idora raqami {{ $r->gov_request_id ?? '—' }}<br>Yaratildi: {{ $generatedAt->format('d.m.Y H:i') }}</div>
</header>

<footer>
    Ushbu hujjat demo maqsadida yaratilgan, barcha ma'lumotlar uydirma. Hujjat advokat siri hisoblanadi —
    uchinchi shaxslarga berish taqiqlanadi. Suv belgisi: {{ $wm['downloaded_by'] }}, {{ $wm['at'] }}.
</footer>

<h1>{{ $r->type->agency() }}: {{ $r->type->label() }} bo'yicha ma'lumotnoma</h1>
<div class="muted">Idoralararo so'rovga javob <span class="demo">DEMO</span></div>

<h2>So'rov rekvizitlari</h2>
<table class="kv">
    <tr><td>Ish</td><td>{{ $r->legalCase->title }}{{ $r->legalCase->court_case_number ? ' ('.$r->legalCase->court_case_number.')' : '' }}</td></tr>
    <tr><td>Shaxs</td><td>{{ $payload['subject']['full_name'] ?? $r->subject_name ?? '—' }}, JShShIR {{ $payload['subject']['pinfl'] ?? $r->subject_pinfl_masked }}</td></tr>
    <tr><td>Huquqiy asos</td><td>{{ $r->legalBasis?->type->label() }} № {{ $r->legalBasis?->number }}{{ $r->legalBasis?->court_name ? ', '.$r->legalBasis->court_name : '' }}</td></tr>
    <tr><td>So'rovchi advokat</td><td>{{ $r->signer?->name }}, litsenziya № {{ $r->signer?->advocateProfile?->license_number }}</td></tr>
    <tr><td>Imzolangan</td><td>{{ $r->signed_at?->setTimezone('Asia/Tashkent')->format('d.m.Y H:i') }} · ERI (demo) {{ substr((string) $r->signature, 0, 20) }}…</td></tr>
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
            <span class="muted" style="font-size: 7.5pt">{{ $response->gov_signature ?? '—' }}</span>
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
