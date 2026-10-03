/**
 * Aturan rute untuk shell Discuss. Satu tempat untuk menjawab:
 *   - halaman mana yang "tab utama" (navigasi bawah tampil)?
 *   - ke mana tombol back membawa user dari halaman detail?
 *   - apa judul default halaman detail?
 *
 * Halaman baru di dalam shell cukup didaftarkan di sini; tidak perlu
 * menyentuh LayoutDiscuss atau NavigationBar.
 */

/** Tab utama. Hanya di path ini navigasi bawah tampil. */
export const TAB_ROOTS = ['/discuss', '/discuss/story', '/discuss/leaderboard', '/direct', '/profile']

export function normalizePath(url: string): string {
  return url.split('?')[0].replace(/\/+$/, '') || '/'
}

export function isTabRoot(path: string): boolean {
  return TAB_ROOTS.includes(path)
}

/** Judul default untuk halaman detail yang judulnya statis. */
const TITLES: Array<[RegExp, string]> = [
  [/^\/discuss\/leaderboard\/admin$/, 'Manage CP'],
  [/^\/discuss\/[^/]+\/about$/, 'About'],
  [/^\/discuss\/[^/]+\/reports$/, 'Reports'],
  [/^\/u\/[^/]+$/, 'Profile'],
]

export function defaultTitle(path: string): string {
  return TITLES.find(([re]) => re.test(path))?.[1] ?? ''
}

/**
 * Tujuan tombol back. `null` berarti "pakai history browser" — dipakai untuk
 * halaman yang bisa dibuka dari banyak tempat (mis. profil user lain dari
 * daftar member, pesan, atau leaderboard).
 */
export function parentPath(path: string): string | null {
  if (/^\/u\/[^/]+$/.test(path)) return null

  const reports = path.match(/^\/discuss\/([^/]+)\/reports$/)
  if (reports) return `/discuss/${reports[1]}/about`

  if (path === '/discuss/leaderboard/admin') return '/discuss/leaderboard'

  // Default: naik satu segmen, tapi tidak keluar dari area Discuss.
  const up = path.replace(/\/[^/]+$/, '')
  return up && up !== '' ? up : '/discuss'
}
