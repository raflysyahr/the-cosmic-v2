function getCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp('(^|; )' + name + '=([^;]*)'))
  return match ? decodeURIComponent(match[2]) : null
}

export function apiFetch(url: string, opts: RequestInit = {}): Promise<Response> {
  const headers = new Headers(opts.headers)
  if (!headers.has('Accept')) headers.set('Accept', 'application/json')
  if (!headers.has('Content-Type') && opts.method !== undefined && opts.method !== 'GET') {
    headers.set('Content-Type', 'application/json')
  }

  // Laravel/Sanctum SPA butuh header X-XSRF-TOKEN (dibaca dari cookie
  // XSRF-TOKEN yang di-set server) untuk validasi CSRF pada request
  // non-GET. axios (client.ts) melakukan ini otomatis lewat opsi
  // withXSRFToken, tapi fetch() native TIDAK — tanpa baris ini semua
  // POST/PUT/DELETE lewat apiFetch selalu gagal 419 "CSRF token
  // mismatch", persis kasus kirim pesan yang gagal di kedua HP.
  const method = (opts.method ?? 'GET').toUpperCase()
  if (method !== 'GET' && method !== 'HEAD' && !headers.has('X-XSRF-TOKEN')) {
    const token = getCookie('XSRF-TOKEN')
    if (token) headers.set('X-XSRF-TOKEN', token)
  }

  return fetch(url, { ...opts, credentials: 'include', headers })
}
