<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">

        <title inertia>{{ config('app.name', 'The Cosmic') }}</title>

        {{-- @font-face SENGAJA inline di sini, bukan di resources/css/app.css:
             - Di dev (npm run dev) CSS app baru ada setelah JS dimuat dan URL font-nya
               ditulis ulang ke origin Vite (:5173), jadi teks splash tampil dengan font
               cadangan dan preload di bawah tidak terpakai.
             - Di sini rule sudah ada di first paint, URL-nya same-origin dan persis sama
               dengan preload serta daftar precache di public/sw.js (ubah salah satu → ubah semua).
             Bitcount memakai font-display: block (teks brand pendek); Inter/Manrope swap. --}}
        <style>
            @font-face {
                font-family: 'BitcountGridDouble';
                font-style: normal;
                font-weight: 100 900;
                font-display: block;
                src: url('/fonts/BitcountGridDouble-Variable.woff2') format('woff2-variations');
            }
            @font-face {
                font-family: 'BitcountGridDouble';
                font-style: normal;
                font-weight: 400;
                font-display: block;
                src: url('/fonts/BitcountGridDouble-Regular.woff2') format('woff2');
            }
            @font-face {
                font-family: 'BitcountGridDouble';
                font-style: normal;
                font-weight: 700;
                font-display: block;
                src: url('/fonts/BitcountGridDouble-Bold.woff2') format('woff2');
            }
            @font-face {
                font-family: 'Inter';
                font-style: normal;
                font-weight: 100 900;
                font-display: swap;
                src: url('/fonts/inter/Inter-latin.woff2') format('woff2');
                unicode-range: U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD;
            }
            @font-face {
                font-family: 'Inter';
                font-style: normal;
                font-weight: 100 900;
                font-display: swap;
                src: url('/fonts/inter/Inter-latin-ext.woff2') format('woff2');
                unicode-range: U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF;
            }
            @font-face {
                font-family: 'Manrope';
                font-style: normal;
                font-weight: 200 800;
                font-display: swap;
                src: url('/fonts/manrope/Manrope-latin.woff2') format('woff2');
                unicode-range: U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD;
            }
            @font-face {
                font-family: 'Manrope';
                font-style: normal;
                font-weight: 200 800;
                font-display: swap;
                src: url('/fonts/manrope/Manrope-latin-ext.woff2') format('woff2');
                unicode-range: U+0100-02BA,U+02BD-02C5,U+02C7-02CC,U+02CE-02D7,U+02DD-02FF,U+0304,U+0308,U+0329,U+1D00-1DBF,U+1E00-1E9F,U+1EF2-1EFF,U+2020,U+20A0-20AB,U+20AD-20C0,U+2113,U+2C60-2C7F,U+A720-A7FF;
            }
        </style>
        {{-- Font self-hosted (public/fonts). Preload hanya yang dipakai di first paint:
             teks splash (Bitcount 400), body (Inter), UI (Manrope). 'crossorigin' wajib
             untuk preload font walau same-origin. --}}
        <link rel="preload" href="/fonts/BitcountGridDouble-Regular.woff2" as="font" type="font/woff2" crossorigin />
        <link rel="preload" href="/fonts/inter/Inter-latin.woff2" as="font" type="font/woff2" crossorigin />
        <link rel="preload" href="/fonts/manrope/Manrope-latin.woff2" as="font" type="font/woff2" crossorigin />
        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0, viewport-fit=cover, interactive-widget=resizes-content"
        />

        {{-- Splash web (#splash di bawah) hanya untuk tab browser biasa. Saat dibuka dari PWA
             yang sudah di-install, OS sudah menampilkan splash sendiri (ikon + background_color
             manifest), jadi splash web dimatikan agar tidak muncul dua kali.
             - Media query: mencakup display-mode manifest (fullscreen/standalone/minimal-ui/WCO)
               dan langsung berlaku di first paint tanpa menunggu JS.
             - Script: iOS Safari tidak mendukung media query itu (pakai navigator.standalone),
               dan TWA Android ditandai lewat document.referrer 'android-app://'. --}}
        <style>
            @media (display-mode: fullscreen), (display-mode: standalone), (display-mode: minimal-ui), (display-mode: window-controls-overlay) {
                #splash { display: none !important; }
            }
            html.is-pwa #splash { display: none !important; }
        </style>
        <script>
            (function () {
                var pwa = false;
                try {
                    pwa = window.navigator.standalone === true
                        || (window.matchMedia && (
                            window.matchMedia('(display-mode: fullscreen)').matches
                            || window.matchMedia('(display-mode: standalone)').matches
                            || window.matchMedia('(display-mode: minimal-ui)').matches
                            || window.matchMedia('(display-mode: window-controls-overlay)').matches))
                        || (document.referrer || '').indexOf('android-app://') === 0;
                } catch (e) {}
                if (pwa) document.documentElement.classList.add('is-pwa');
            })();
        </script>

        {{-- PWA --}}
        <link rel="manifest" href="/manifest.json" />
        <meta name="theme-color" content="#000000" />



        <link rel="icon" href="/favicon.ico" sizes="48x48">
        <link rel="icon" href="/icons/icon-192x192.png" type="image/png" sizes="192x192">
        <link rel="icon" href="/icons/icon-512x512.png" type="image/png" sizes="512x512"/>

        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png" />
        <meta name="mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-capable" content="yes" />
        <meta name="apple-mobile-web-app-title" content="The Cosmic" />
        <meta name="apple-mobile-web-app-status-bar-style" content="black" />

        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="bg-black text-[#999999] antialiased">
        <div id="splash" style="position:fixed;inset:0;z-index:99999;background:#000;display:flex;flex-direction:column;align-items:center;justify-content:center">
            <div style="position:relative;width:200px;max-width:80vw;margin-bottom:16px">
                {{-- poster = frame logo yang sama dengan ikon app (dibuat scripts/make-pwa-icons.py), supaya
                     splash bawaan OS -> splash ini terlihat bersambung, bukan dua logo berbeda. --}}
                <video autoplay loop muted playsinline poster="/icons/splash-poster.jpg" style="width:100%;display:block">
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

                // PWA: splash dibuang dari DOM (bukan sekadar disembunyikan) supaya <video>-nya
                // berhenti memuat/memutar, dan tidak ada jeda 3 detik buatan (MIN_SPLASH_MS).
                if (document.documentElement.classList.contains('is-pwa')) {
                    if (splash && splash.parentNode) splash.parentNode.removeChild(splash);
                    return;
                }

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
