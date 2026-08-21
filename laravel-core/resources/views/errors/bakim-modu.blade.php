<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Planlı bakım çalışması</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f1f5f9; color: #0f172a; }
        main { width: min(92vw, 620px); padding: 2.5rem; text-align: center; background: #fff; border: 1px solid #e2e8f0; border-radius: 1rem; box-shadow: 0 20px 60px rgba(15, 23, 42, .08); }
        h1 { margin: 0 0 1rem; font-size: clamp(1.5rem, 4vw, 2.2rem); }
        p { margin: 0; color: #475569; line-height: 1.7; }
        .badge { display: inline-block; margin-bottom: 1.25rem; padding: .4rem .75rem; border-radius: 999px; background: #fff7ed; color: #c2410c; font-size: .8rem; font-weight: 700; }
    </style>
</head>
<body>
    <main>
        <span class="badge">PLANLI BAKIM</span>
        <h1>Birazdan tekrar hizmetinizdeyiz</h1>
        <p>{{ $mesaj }}</p>
    </main>
</body>
</html>
