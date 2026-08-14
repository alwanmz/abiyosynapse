@php
    $company = rescue(fn () => \App\Models\CompanySetting::find(1), null, false);
    $brand = trim($company->nama_perusahaan ?? '') ?: 'Teamboard SKI';
    $logo = $company?->logo_path ? '/storage/' . $company->logo_path : null;

    $code = $code ?? null;
    $badge = $badge ?? null;
    $title = $title ?? '';
    $accent = $accent ?? null;
    $message = $message ?? '';
    $showWalker = $showWalker ?? true;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @yield('head')
    @php
        $headline = trim(preg_replace('/\s+/', ' ', $title . ' ' . ($accent ?? '') . ' ' . trim($__env->yieldContent('title_suffix'))));
    @endphp
    <title>{{ trim(($code ? $code . ' — ' : '') . $headline) }} — {{ $brand }}</title>
    <link rel="icon" href="{{ $logo ?? '/favicon.ico' }}">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        :root {
            --blue-600: #2563eb;
            --blue-700: #1d4ed8;
            --sky: #38bdf8;
            --ink: #0a1326;
        }
        html, body { height: 100%; margin: 0; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: #e6ecff;
            background:
                radial-gradient(circle at 85% 12%, rgba(56,189,248,0.18), transparent 45%),
                radial-gradient(circle at 12% 88%, rgba(59,130,246,0.20), transparent 45%),
                linear-gradient(160deg, #0b1730 0%, #0a1326 55%, #070e1f 100%);
            min-height: 100svh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            padding: 24px;
            text-align: center;
        }

        /* Soft blurred glow blobs (matches login) */
        .glow { position: fixed; border-radius: 9999px; filter: blur(110px); z-index: 0; pointer-events: none; }
        .glow.one { width: 480px; height: 480px; left: -140px; bottom: -160px; background: rgba(56,189,248,0.22); }
        .glow.two { width: 420px; height: 420px; right: -120px; top: -140px; background: rgba(96,165,250,0.18); }

        /* Drifting clouds for that platformer sky */
        .cloud {
            position: fixed; z-index: 1; opacity: .10; pointer-events: none;
            background: #cfe4ff; border-radius: 9999px; filter: blur(6px);
        }
        .cloud::before, .cloud::after {
            content: ""; position: absolute; background: inherit; border-radius: 9999px;
        }
        .cloud { width: 120px; height: 34px; }
        .cloud::before { width: 60px; height: 60px; left: 16px; top: -22px; }
        .cloud::after  { width: 46px; height: 46px; right: 18px; top: -14px; }
        .cloud.a { top: 18%; animation: drift 46s linear infinite; }
        .cloud.b { top: 34%; transform: scale(.7); animation: drift 64s linear infinite; animation-delay: -20s; }
        .cloud.c { top: 12%; transform: scale(1.15); opacity: .07; animation: drift 82s linear infinite; animation-delay: -50s; }
        @keyframes drift { from { left: -160px; } to { left: 108vw; } }

        main { position: relative; z-index: 5; max-width: 560px; }

        .brand { display: inline-flex; flex-direction: column; align-items: center; gap: 10px; margin-bottom: 30px; }
        .logo-box {
            width: 62px; height: 62px; border-radius: 16px; overflow: hidden;
            display: flex; align-items: center; justify-content: center;
            background: var(--blue-600); color: #fff;
            box-shadow: 0 12px 30px rgba(37,99,235,0.45), inset 0 0 0 1px rgba(255,255,255,0.08);
        }
        .logo-box img { width: 100%; height: 100%; object-fit: contain; background: #fff; }
        .brand-name { font-size: 20px; font-weight: 800; letter-spacing: -0.02em; color: #fff; }
        .brand-tag { margin-top: -4px; font-size: 11px; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: rgba(147,197,253,0.85); }

        /* Big ghosted status code behind the heading */
        .code {
            font-size: clamp(88px, 20vw, 150px); font-weight: 800; line-height: .9;
            letter-spacing: -0.06em; margin: 0 0 6px;
            background: linear-gradient(180deg, rgba(255,255,255,0.22), rgba(96,165,250,0.06));
            -webkit-background-clip: text; background-clip: text; color: transparent;
            user-select: none;
        }

        .pill {
            display: inline-flex; align-items: center; gap: 8px; margin-bottom: 22px;
            padding: 6px 14px; border-radius: 9999px; font-size: 11px; font-weight: 700;
            letter-spacing: .12em; text-transform: uppercase;
            background: rgba(255,255,255,0.08); color: #dbeafe;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.14); backdrop-filter: blur(4px);
        }
        .pill .spark { width: 8px; height: 8px; border-radius: 9999px; background: #fbbf24; box-shadow: 0 0 10px 2px rgba(251,191,36,.7); animation: pulse 1.6s ease-in-out infinite; }

        h1 { margin: 0 0 14px; font-size: clamp(26px, 5vw, 40px); font-weight: 800; letter-spacing: -0.03em; color: #fff; line-height: 1.1; }
        h1 .accent { background: linear-gradient(90deg, #60a5fa, #38bdf8); -webkit-background-clip: text; background-clip: text; color: transparent; }
        p.lead { margin: 0 auto; max-width: 460px; font-size: 15px; line-height: 1.6; color: rgba(191,205,232,0.85); }

        .actions { margin-top: 28px; display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px; cursor: pointer;
            padding: 11px 20px; border-radius: 12px; border: 0;
            font-family: inherit; font-size: 14px; font-weight: 600; text-decoration: none;
            transition: transform .15s ease, box-shadow .15s ease, background-color .15s ease;
        }
        .btn:active { transform: translateY(1px); }
        .btn-primary {
            background: var(--blue-600); color: #fff;
            box-shadow: 0 10px 24px rgba(37,99,235,0.40), inset 0 0 0 1px rgba(255,255,255,0.10);
        }
        .btn-primary:hover { background: var(--blue-700); }
        .btn-ghost {
            background: rgba(255,255,255,0.08); color: #dbeafe;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,0.14); backdrop-filter: blur(4px);
        }
        .btn-ghost:hover { background: rgba(255,255,255,0.14); }

        .dots { margin-top: 26px; display: inline-flex; gap: 9px; }
        .dots span { width: 10px; height: 10px; border-radius: 9999px; background: var(--sky); opacity: .5; animation: blink 1.4s ease-in-out infinite; }
        .dots span:nth-child(2) { animation-delay: .2s; }
        .dots span:nth-child(3) { animation-delay: .4s; }

        /* ---- Super-Mario-style walking logo ---- */
        .scene { position: fixed; left: 0; right: 0; bottom: 0; height: 150px; z-index: 4; pointer-events: none; }
        .ground {
            position: absolute; left: 0; right: 0; bottom: 0; height: 46px;
            background:
                repeating-linear-gradient(90deg, rgba(255,255,255,0.05) 0 2px, transparent 2px 46px),
                linear-gradient(180deg, #14315f 0%, #0d2144 100%);
            border-top: 3px solid rgba(96,165,250,0.55);
            box-shadow: 0 -8px 24px rgba(37,99,235,0.25);
        }
        .walker { position: absolute; bottom: 40px; left: 0; animation: walk 9s linear infinite; }
        .hop { animation: hop .5s ease-in-out infinite; transform-origin: bottom center; }
        .char {
            width: 56px; height: 56px; border-radius: 9999px; display: block;
            object-fit: contain; background: #fff;
            box-shadow: 0 6px 16px rgba(0,0,0,0.35), 0 0 0 3px rgba(96,165,250,0.5);
        }
        .char.fallback { display: flex; align-items: center; justify-content: center; background: var(--blue-600); color: #fff; }
        .shadow {
            width: 46px; height: 11px; margin: 4px auto 0; border-radius: 9999px;
            background: rgba(0,0,0,0.45); filter: blur(3px);
            animation: shadowScale .5s ease-in-out infinite;
        }

        @keyframes walk { from { transform: translateX(-14vw); } to { transform: translateX(114vw); } }
        /* hop = the little up/down + waddle that reads as "walking / marching" */
        @keyframes hop {
            0%   { transform: translateY(0)     rotate(-5deg); }
            25%  { transform: translateY(-20px) rotate(0deg); }
            50%  { transform: translateY(0)     rotate(5deg); }
            75%  { transform: translateY(-20px) rotate(0deg); }
            100% { transform: translateY(0)     rotate(-5deg); }
        }
        @keyframes shadowScale {
            0%, 50%, 100% { transform: scaleX(1);   opacity: .45; }
            25%, 75%      { transform: scaleX(.62); opacity: .22; }
        }
        @keyframes blink { 0%, 100% { opacity: .35; transform: translateY(0); } 50% { opacity: 1; transform: translateY(-3px); } }
        @keyframes pulse { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(.7); opacity: .6; } }

        @media (prefers-reduced-motion: reduce) {
            .walker, .hop, .shadow, .cloud, .dots span, .pill .spark { animation: none !important; }
            .walker { transform: translateX(46vw); }
        }
    </style>
</head>
<body>
    <div class="glow one"></div>
    <div class="glow two"></div>
    <div class="cloud a"></div>
    <div class="cloud b"></div>
    <div class="cloud c"></div>

    <main>
        @if ($code)
            <div class="code">{{ $code }}</div>
        @endif

        @if ($badge)
            <div class="pill"><span class="spark"></span> {{ $badge }}</div>
        @endif

        <h1>{{ $title }}@if ($accent) <span class="accent">{{ $accent }}</span>@endif @yield('title_suffix')</h1>

        <p class="lead">{{ $message }}</p>

        @hasSection('actions')
            <div class="actions">@yield('actions')</div>
        @endif

        @yield('extra')
    </main>

    @if ($showWalker)
        <div class="scene">
            <div class="walker">
                <div class="hop">
                    @if ($logo)
                        <img class="char" src="{{ $logo }}" alt="{{ $brand }}">
                    @else
                        <div class="char fallback">
                            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
                        </div>
                    @endif
                </div>
                <div class="shadow"></div>
            </div>
            <div class="ground"></div>
        </div>
    @endif
</body>
</html>
