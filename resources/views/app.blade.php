<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'The Cosmic') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700&family=Manrope:wght@400;500;600;700&family=Material+Symbols+Outlined:opsz,wght,FILL@20..48,100..700,0..1&display=swap" rel="stylesheet" />
<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0, viewport-fit=cover, interactive-widget=resizes-content"
/>
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="bg-black text-[#999999] antialiased">
        <div id="splash" style="position:fixed;inset:0;z-index:99999;background:#000;display:flex;flex-direction:column;align-items:center;justify-content:center">
            <div style="position:relative;width:200px;max-width:80vw;margin-bottom:16px">
                <video autoplay loop muted playsinline style="width:100%;display:block">
                    <source src="/lv_0_20260622135734.mp4" type="video/mp4" />
                </video>
                <div style="position:absolute;inset:0;pointer-events:none;background:linear-gradient(to right,#000 0%,transparent 25%,transparent 75%,#000 100%)"></div>
            </div>
            <span style="font-family:'BitcountGridDouble';font-size:24px;color:white;letter-spacing:0.05em">The Cosmic</span>
        </div>

        @inertia

        <script>
            (function() {
                var MIN_SPLASH_MS = 3000;
                var startTime = Date.now();
                var splash = document.getElementById('splash');

                function hideSplash() {
                    if (!splash) return;
                    var elapsed = Date.now() - startTime;
                    var remaining = MIN_SPLASH_MS - elapsed;
                    if (remaining > 0) {
                        setTimeout(function() { splash.style.display = 'none'; }, remaining);
                    } else {
                        splash.style.display = 'none';
                    }
                }

                if (document.readyState === 'complete') {
                    requestAnimationFrame(hideSplash);
                } else {
                    window.addEventListener('load', function() {
                        requestAnimationFrame(hideSplash);
                    });
                }
            })();
        </script>
    </body>
</html>
