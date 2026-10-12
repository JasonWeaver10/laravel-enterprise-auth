<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Enterprise Auth')</title>
    {{-- Styles are inline on purpose: this repo should run with nothing but
         `composer install` and `php artisan serve`, no npm build step. --}}
    <style>
        :root {
            --bg: #f6f7f9; --surface: #fff; --fg: #15191e; --muted: #5d6771;
            --line: #e1e5ea; --accent: #1662d4; --bad: #a6341f;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #11151a; --surface: #181d23; --fg: #e8ecf0; --muted: #94a0ab;
                --line: #272e36; --accent: #5e9cf5; --bad: #e08270;
                color-scheme: dark;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; background: var(--bg); color: var(--fg); line-height: 1.55;
            font: 16px/1.55 -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .wrap { max-width: 760px; margin: 0 auto; padding: 48px 20px 80px; }
        h1 { font-size: 1.6rem; margin: 0 0 6px; letter-spacing: -0.015em; }
        h2 { font-size: 1.05rem; margin: 28px 0 10px; }
        p { margin: 0 0 12px; }
        .lede { color: var(--muted); margin-bottom: 28px; }
        .card {
            background: var(--surface); border: 1px solid var(--line);
            border-radius: 12px; padding: 20px; margin-bottom: 16px;
        }
        dl { display: grid; grid-template-columns: max-content 1fr; gap: 8px 18px; margin: 0; }
        dt { color: var(--muted); font-size: 0.86rem; }
        dd { margin: 0; font-variant-numeric: tabular-nums; word-break: break-all; }
        pre {
            background: var(--bg); border: 1px solid var(--line); border-radius: 8px;
            padding: 14px; overflow-x: auto; font-size: 0.82rem; margin: 0;
            font-family: ui-monospace, "SF Mono", Menlo, monospace;
        }
        .btn {
            display: inline-block; background: var(--accent); color: #fff;
            border: 0; border-radius: 8px; padding: 10px 18px; font: inherit;
            font-weight: 600; cursor: pointer; text-decoration: none;
        }
        .btn.ghost { background: transparent; color: var(--fg); border: 1px solid var(--line); font-weight: 400; }
        .status {
            border-left: 3px solid var(--accent); background: var(--surface);
            padding: 12px 14px; border-radius: 0 8px 8px 0; margin-bottom: 20px;
        }
        .warn { border-left-color: var(--bad); }
        code { font-family: ui-monospace, Menlo, monospace; font-size: 0.88em; }
    </style>
</head>
<body>
<div class="wrap">
    @if (session('status'))
        <div class="status">{{ session('status') }}</div>
    @endif
    @yield('body')
</div>
</body>
</html>
