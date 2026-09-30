// Local cache for image/video/file bytes, backed by the browser's Cache
// Storage API. Goal: stream/download something once, never fetch the same
// URL from the server twice from this device.
//
// - getCachedBlobUrl(url)  → cache hit: instant, no network. Miss: one fetch,
//   then cached for next time. Used for the image lightbox and file downloads.
// - warmCache(url)         → fire a caching fetch in the background without
//   blocking whoever's using the URL directly (native <video> streaming).
// - putBlob(url, blob)     → store a blob we already have locally (e.g. the
//   sender's own upload) without any network round-trip at all.
// - saveToDevice(url, name)→ "Save": deliberately cache-only, never touches
//   the network, per the product requirement that Save must come from what
//   was already streamed/downloaded, not trigger a fresh fetch.

const CACHE_NAME = 'discuss-media-v1'
const INDEX_KEY = 'discuss:media-cache-index'
const MAX_CACHE_BYTES = 150 * 1024 * 1024 // soft cap; oldest entries evicted first

function supported(): boolean {
  return typeof window !== 'undefined' && 'caches' in window
}

let cacheHandle: Promise<Cache> | null = null
function openCache(): Promise<Cache> {
  if (!cacheHandle) cacheHandle = caches.open(CACHE_NAME)
  return cacheHandle
}

interface IndexEntry { size: number; ts: number }

function readIndex(): Record<string, IndexEntry> {
  try {
    const raw = localStorage.getItem(INDEX_KEY)
    const parsed = raw ? JSON.parse(raw) : {}
    return parsed && typeof parsed === 'object' ? parsed : {}
  } catch {
    return {}
  }
}

function writeIndex(idx: Record<string, IndexEntry>): void {
  try {
    localStorage.setItem(INDEX_KEY, JSON.stringify(idx))
  } catch {
    // storage unavailable — eviction bookkeeping just won't happen this session
  }
}

async function evictIfNeeded(): Promise<void> {
  const idx = readIndex()
  let total = Object.values(idx).reduce((sum, e) => sum + e.size, 0)
  if (total <= MAX_CACHE_BYTES) return

  const cache = await openCache()
  const oldestFirst = Object.entries(idx).sort((a, b) => a[1].ts - b[1].ts)
  for (const [url, entry] of oldestFirst) {
    if (total <= MAX_CACHE_BYTES) break
    await cache.delete(url)
    delete idx[url]
    total -= entry.size
  }
  writeIndex(idx)
}

function recordEntry(url: string, size: number): void {
  const idx = readIndex()
  idx[url] = { size, ts: Date.now() }
  writeIndex(idx)
  evictIfNeeded().catch(() => {})
}

function forgetEntry(url: string): void {
  const idx = readIndex()
  if (url in idx) {
    delete idx[url]
    writeIndex(idx)
  }
}

export async function isCached(url: string): Promise<boolean> {
  if (!supported()) return false
  try {
    const cache = await openCache()
    return !!(await cache.match(url))
  } catch {
    return false
  }
}

export async function getCachedBlob(url: string): Promise<Blob | null> {
  if (!supported()) return null
  try {
    const cache = await openCache()
    const res = await cache.match(url)
    return res ? await res.blob() : null
  } catch {
    return null
  }
}

// Only one real fetch per URL, even if multiple components ask for it at once.
const inFlight = new Map<string, Promise<Blob>>()

async function fetchAndCache(url: string): Promise<Blob> {
  const existing = inFlight.get(url)
  if (existing) return existing

  const task = (async () => {
    const res = await fetch(url)
    if (!res.ok) throw new Error(`Fetch failed (${res.status})`)
    const blob = await res.blob()
    if (supported()) {
      try {
        const cache = await openCache()
        await cache.put(url, new Response(blob, { headers: { 'Content-Type': blob.type } }))
        recordEntry(url, blob.size)
      } catch {
        // Quota exceeded or Cache Storage unavailable mid-flight — the blob we
        // already have is still returned, it just won't be cached this time.
      }
    }
    return blob
  })()

  inFlight.set(url, task)
  try {
    return await task
  } finally {
    inFlight.delete(url)
  }
}

/** Cache hit → instant object URL, no network. Miss → one fetch, then cached. */
export async function getCachedBlobUrl(url: string): Promise<string> {
  const cached = await getCachedBlob(url)
  const blob = cached ?? await fetchAndCache(url)
  return URL.createObjectURL(blob)
}

/** Warms the cache in the background; safe to call repeatedly for the same URL. */
export function warmCache(url: string): Promise<void> {
  if (!supported()) return Promise.resolve()
  return isCached(url).then((yes) => {
    if (yes) return
    return fetchAndCache(url).then(() => undefined).catch(() => undefined)
  })
}

/** Store a blob we already have locally (e.g. our own just-sent upload) — no network at all. */
export async function putBlob(url: string, blob: Blob): Promise<void> {
  if (!supported()) return
  try {
    const cache = await openCache()
    await cache.put(url, new Response(blob, { headers: { 'Content-Type': blob.type || 'application/octet-stream' } }))
    recordEntry(url, blob.size)
  } catch {
    // best effort
  }
}

export async function evictFromCache(url: string): Promise<void> {
  if (!supported()) return
  try {
    const cache = await openCache()
    await cache.delete(url)
    forgetEntry(url)
  } catch {
    // best effort
  }
}

/**
 * Saves a URL to the user's device using ONLY what's already cached — this
 * never triggers a fresh server fetch. Returns false if nothing is cached yet
 * (caller should hide/disable the Save action in that case).
 */
export async function saveToDevice(url: string, filename: string): Promise<boolean> {
  const blob = await getCachedBlob(url)
  if (!blob) return false
  const objectUrl = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = objectUrl
  a.download = filename
  a.rel = 'noopener'
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(objectUrl), 4000)
  return true
}
