<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#f6f1e7">
    <title>{{ __('You are offline - Sofor') }}</title>
    {{-- Self-contained on purpose: this page is shown when nothing else can load. --}}
    <style>
        :root { --ink: #17211f; --muted: #5b6660; --line: #e3dccb; --brand: #0f6f6a; --paper: #f6f1e7; --surface: #fffdf8; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100dvh; display: grid; place-items: center; padding: 24px 16px; background: var(--paper);
               color: var(--ink); font-family: Inter, ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
        main { width: 100%; max-width: 420px; }
        .card { background: var(--surface); border: 2px solid var(--ink); border-radius: 28px; padding: 28px 24px; box-shadow: 5px 5px 0 0 var(--ink); }
        .mark { width: 48px; height: 48px; border-radius: 16px; background: var(--brand); color: #fff; display: grid; place-items: center;
                font: 700 26px/1 Poppins, ui-sans-serif, system-ui, 'Segoe UI', sans-serif; }
        h1 { font: 600 26px/1.2 Poppins, ui-sans-serif, system-ui, 'Segoe UI', sans-serif; margin: 18px 0 6px; letter-spacing: -0.02em; }
        p { margin: 0; color: var(--muted); font-size: 14px; line-height: 1.55; }
        button { margin-top: 20px; width: 100%; height: 46px; border: 0; border-radius: 999px; background: var(--brand); color: #fff;
                 font: 600 14px/1 inherit; cursor: pointer; }
        h2 { margin: 24px 0 8px; font-size: 12px; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); }
        ul { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
        a { display: block; padding: 12px 14px; border: 1px solid var(--line); border-radius: 16px; background: var(--surface);
            color: var(--ink); text-decoration: none; font-size: 14px; font-weight: 600; }
        .tip { margin-top: 18px; font-size: 12.5px; }
    </style>
</head>
<body>
    <main>
        <div class="card">
            <div class="mark">S</div>
            <h1>{{ __('No connection right now') }}</h1>
            <p>{{ __('Hill tracts and highways do that. Trips you opened recently still work, and costs you add are saved on this phone and sent the moment you are back online.') }}</p>
            <button type="button" onclick="location.reload()">{{ __('Try again') }}</button>

            <div id="saved" hidden>
                <h2>{{ __('Saved on this phone') }}</h2>
                <ul id="saved-list"></ul>
            </div>

            <p class="tip">{{ __('Sofor never loses a cost you logged offline - it waits for a signal.') }}</p>
        </div>
    </main>

    <script>
        // List the trip pages the service worker kept, with their titles.
        (async () => {
            if (!('caches' in window)) return;
            const cache = await caches.open('sofor-pages');
            const requests = await cache.keys();
            const list = document.getElementById('saved-list');

            for (const request of requests.slice(-8).reverse()) {
                const url = new URL(request.url);
                if (!/^\/(trips\/\d+|dashboard)$/.test(url.pathname)) continue;
                const response = await cache.match(request);
                const html = response ? await response.text() : '';
                const title = (html.match(/<title>([^<]*)<\/title>/i)?.[1] || url.pathname).replace(/\s*[-|·]\s*Sofor.*$/i, '');
                const item = document.createElement('li');
                const link = document.createElement('a');
                link.href = url.pathname;
                link.textContent = title.replace(/&amp;/g, '&').replace(/&#039;/g, "'").replace(/&quot;/g, '"');
                item.appendChild(link);
                list.appendChild(item);
            }

            document.getElementById('saved').hidden = list.children.length === 0;
        })();
    </script>
</body>
</html>
