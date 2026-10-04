/* eslint-env serviceworker */
/**
 * Service worker The Cosmic.
 *
 * Sengaja minimal dan memakai ALLOWLIST: hanya tiga hal yang disentuh —
 *   1. navigasi halaman penuh  → network-first, jatuh ke /offline.html bila gagal
 *   2. /build/assets/*         → cache-first (nama file ber-hash = tidak pernah berubah isinya)
 *   3. /fonts/* dan /icons/*   → stale-while-revalidate
 * Selain itu (API, Inertia XHR, /broadcasting, Sanctum/CSRF, POST, media, request
 * cross-origin) tidak dicegat sama sekali, supaya sesi login, token CSRF, dan chat
 * realtime tetap berperilaku persis seperti tanpa service worker.
 *
 * HTML TIDAK PERNAH di-cache: halaman berisi props user dan token CSRF.
 *
 * Penting: cache milik kode lain (mis. 'discuss-media-v1' di lib/mediaCache.ts)
 * tidak boleh dihapus di sini — pembersihan hanya menyentuh cache berawalan 'cosmic-'.
 */

const VERSION = 'v3'
const STATIC_CACHE = `cosmic-static-${VERSION}`
const ASSET_CACHE = 'cosmic-assets'
const OFFLINE_URL = '/offline.html'
const MAX_ASSET_ENTRIES = 200

// Wajib ada; install gagal kalau salah satu tidak bisa diambil.
const PRECACHE_REQUIRED = [OFFLINE_URL, '/icons/icon-192.png']
// Font inti (self-hosted). Opsional: file yang hilang tidak boleh menggagalkan install.
// Subset latin-ext dan Bitcount Variable tidak di-precache; masuk cache saat pertama dipakai
// lewat staleWhileRevalidate di bawah.
const PRECACHE_OPTIONAL = [
  '/fonts/BitcountGridDouble-Regular.woff2',
  '/fonts/BitcountGridDouble-Bold.woff2',
  '/fonts/inter/Inter-latin.woff2',
  '/fonts/manrope/Manrope-latin.woff2',
]

// Hanya simpan respons sukses yang BUKAN halaman HTML. Kalau file font hilang dan server
// membalas halaman fallback (HTML), jangan sampai itu tersimpan lalu disajikan sebagai font.
function isCacheable(response) {
  return response.ok && !/text\/html/i.test(response.headers.get('content-type') || '')
}

self.addEventListener('install', (event) => {
  event.waitUntil((async () => {
    const cache = await caches.open(STATIC_CACHE)
    await cache.addAll(PRECACHE_REQUIRED)
    await Promise.all(PRECACHE_OPTIONAL.map(async (url) => {
      try {
        const res = await fetch(url)
        if (isCacheable(res)) await cache.put(url, res)
      } catch { /* opsional */ }
    }))
    await self.skipWaiting()
  })())
})

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const names = await caches.keys()
    await Promise.all(
      names
        .filter((name) => name.startsWith('cosmic-static-') && name !== STATIC_CACHE)
        .map((name) => caches.delete(name)),
    )
    if (self.registration.navigationPreload) {
      await self.registration.navigationPreload.enable().catch(() => {})
    }
    await self.clients.claim()
  })())
})

self.addEventListener('fetch', (event) => {
  const { request } = event
  if (request.method !== 'GET' || request.headers.has('range')) return

  const url = new URL(request.url)
  if (url.origin !== self.location.origin) return

  if (request.mode === 'navigate') {
    event.respondWith(handleNavigation(event))
    return
  }

  if (url.pathname.startsWith('/build/assets/')) {
    event.respondWith(cacheFirst(request, event))
    return
  }

  if (url.pathname.startsWith('/fonts/') || url.pathname.startsWith('/icons/')) {
    event.respondWith(staleWhileRevalidate(request, event))
  }
})

async function handleNavigation(event) {
  try {
    const preloaded = await event.preloadResponse
    if (preloaded) return preloaded
    return await fetch(event.request)
  } catch {
    // Jaringan putus (fetch melempar error). Respons 4xx/5xx dari server TIDAK
    // sampai ke sini — itu tetap diteruskan apa adanya.
    const cached = await caches.match(OFFLINE_URL)
    return cached || new Response('Offline', { status: 503, headers: { 'Content-Type': 'text/plain' } })
  }
}

async function cacheFirst(request, event) {
  const cache = await caches.open(ASSET_CACHE)
  const hit = await cache.match(request)
  if (hit) return hit

  const response = await fetch(request)
  if (response.ok) {
    cache.put(request, response.clone())
    event.waitUntil(trim(cache, MAX_ASSET_ENTRIES))
  }
  return response
}

async function staleWhileRevalidate(request, event) {
  const cache = await caches.open(STATIC_CACHE)
  const hit = await cache.match(request)

  const refresh = fetch(request)
    .then((response) => {
      if (isCacheable(response)) cache.put(request, response.clone())
      return response
    })
    .catch(() => null)

  if (hit) {
    event.waitUntil(refresh)
    return hit
  }
  return (await refresh) || Response.error()
}

// cache.keys() berurutan sesuai waktu masuk → yang paling awal dibuang dulu.
async function trim(cache, max) {
  const keys = await cache.keys()
  for (let i = 0; i < keys.length - max; i++) await cache.delete(keys[i])
}
