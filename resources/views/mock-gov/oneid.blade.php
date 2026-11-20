<!doctype html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OneID — Yagona identifikatsiya tizimi (demo)</title>
    <style>
        :root { --navy: #0b2a4a; --line: #d9dee5; --muted: #5b6573; --bg: #f4f6f9; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: #1a2330; }
        header { background: var(--navy); color: #fff; padding: 14px 24px; font-weight: 600; letter-spacing: .2px; }
        header small { font-weight: 400; opacity: .75; margin-left: 8px; }
        main { max-width: 560px; margin: 32px auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 6px; padding: 24px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        p.lead { color: var(--muted); margin: 0 0 20px; font-size: 14px; }
        h2 { font-size: 13px; color: var(--muted); font-weight: 600; margin: 20px 0 8px; }
        a.person { display: flex; justify-content: space-between; align-items: center; padding: 12px 14px; border: 1px solid var(--line); border-radius: 4px; margin-bottom: 8px; color: inherit; text-decoration: none; }
        a.person:hover { border-color: var(--navy); background: #f8fafc; }
        .name { font-weight: 600; font-size: 15px; }
        .meta { color: var(--muted); font-size: 12px; font-variant-numeric: tabular-nums; }
        .go { color: var(--navy); font-size: 13px; font-weight: 600; }
        .error { background: #fdecec; border: 1px solid #f3c2c2; color: #8a1c1c; padding: 10px 12px; border-radius: 4px; font-size: 14px; margin-bottom: 16px; }
        footer { text-align: center; color: var(--muted); font-size: 12px; margin: 24px 0; }
    </style>
</head>
<body>
<header>OneID <small>Yagona identifikatsiya tizimi — sinov muhiti</small></header>
<main>
    <div class="card">
        <h1>Tizimga kirish</h1>
        <p class="lead">Davom etish uchun foydalanuvchini tanlang. Bu soxta (demo) identifikatsiya sahifasi, barcha shaxslar o'ylab topilgan.</p>

        @if ($error)
            <div class="error">{{ $error }}</div>
        @endif

        <h2>Demo foydalanuvchilar</h2>
        @foreach ($demo as $p)
            <a class="person" href="{{ route('mock-gov.oneid.authorize', $query + ['login_hint' => $p->pinfl]) }}">
                <span>
                    <span class="name">{{ $p->fullName() }}</span><br>
                    <span class="meta">JShShIR {{ \App\Support\Pinfl::mask($p->pinfl) }} · {{ $p->birth_date->format('d.m.Y') }}</span>
                </span>
                <span class="go">Kirish</span>
            </a>
        @endforeach

        @if ($others->isNotEmpty())
            <h2>Boshqa fuqarolar</h2>
            @foreach ($others as $p)
                <a class="person" href="{{ route('mock-gov.oneid.authorize', $query + ['login_hint' => $p->pinfl]) }}">
                    <span>
                        <span class="name">{{ $p->fullName() }}</span><br>
                        <span class="meta">JShShIR {{ \App\Support\Pinfl::mask($p->pinfl) }}</span>
                    </span>
                    <span class="go">Kirish</span>
                </a>
            @endforeach
        @endif
    </div>
    <footer>Demo muhit · haqiqiy davlat tizimi emas</footer>
</main>
</body>
</html>
