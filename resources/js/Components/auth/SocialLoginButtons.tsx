import { useEffect, useState, type ReactNode } from 'react'
import { apiFetch } from '../../api/fetch'

/**
 * Tombol login social. `providers` datang dari server (prop Inertia
 * `socialProviders`) dan hanya berisi provider yang sudah dikonfigurasi,
 * jadi tombol tidak pernah mengarah ke endpoint yang 404.
 *
 * Alur: GET /api/auth/{provider}/redirect -> { redirect_url } -> pindah ke
 * halaman provider. Callback ditangani server, lalu user diarahkan ke "/"
 * (sukses) atau "/login?social_error=<kode>" (ditolak).
 */

function GoogleIcon() {
  return (
    <svg className="h-4 w-4" viewBox="0 0 48 48" aria-hidden="true">
      <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z" />
      <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z" />
      <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z" />
      <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z" />
    </svg>
  )
}

function FacebookIcon() {
  return (
    <svg className="h-4 w-4" viewBox="0 0 24 24" aria-hidden="true">
      <path
        fill="#1877F2"
        d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"
      />
    </svg>
  )
}

const PROVIDERS: Record<string, { name: string; icon: ReactNode }> = {
  google: { name: 'Google', icon: <GoogleIcon /> },
  facebook: { name: 'Facebook', icon: <FacebookIcon /> },
}

/** Kode `social_error` dari server -> pesan untuk user. */
const SOCIAL_ERRORS: Record<string, string> = {
  email_in_use:
    'This email is already registered and could not be verified for social sign-in. Please sign in with your email and password (or reset your password).',
  banned: 'This account has been banned.',
  invalid_state: 'Your sign-in session expired. Please try again.',
  provider_error: 'Social sign-in failed. Please try again.',
}

/**
 * Baca `?social_error=` dari URL (diisi server saat login social ditolak).
 * Murni (tanpa efek samping), aman dipakai di initializer useState.
 * Mengembalikan null bila tidak ada error.
 */
export function readSocialError(): string | null {
  if (typeof window === 'undefined') return null
  const code = new URLSearchParams(window.location.search).get('social_error')
  if (code === null) return null
  return SOCIAL_ERRORS[code] ?? 'Sign-in failed. Please try again.'
}

/** Hapus `social_error` dari address bar supaya pesan tidak muncul lagi saat refresh. */
export function clearSocialErrorParam(): void {
  if (typeof window === 'undefined') return
  const url = new URL(window.location.href)
  if (!url.searchParams.has('social_error')) return
  url.searchParams.delete('social_error')
  // Pertahankan history.state milik Inertia, kalau tidak back/forward bisa rusak.
  window.history.replaceState(window.history.state, '', url.pathname + url.search + url.hash)
}

interface Props {
  providers?: string[]
  onError?: (message: string) => void
  disabled?: boolean
}

export default function SocialLoginButtons({ providers = [], onError, disabled = false }: Props) {
  const [pending, setPending] = useState<string | null>(null)

  // Tombol "back" browser dari halaman provider mengembalikan halaman dari bfcache
  // dengan state lama (tombol masih loading) — reset.
  useEffect(() => {
    const reset = (e: PageTransitionEvent) => { if (e.persisted) setPending(null) }
    window.addEventListener('pageshow', reset)
    return () => window.removeEventListener('pageshow', reset)
  }, [])

  const known = providers.filter((p) => p in PROVIDERS)
  if (known.length === 0) return null

  const start = async (provider: string) => {
    if (pending) return
    setPending(provider)
    try {
      const res = await apiFetch(`/api/auth/${provider}/redirect`)
      if (!res.ok) throw new Error('redirect failed')
      const { redirect_url } = await res.json()
      if (typeof redirect_url !== 'string' || !/^https?:\/\//.test(redirect_url)) {
        throw new Error('invalid redirect url')
      }
      window.location.assign(redirect_url)
      // Sengaja tidak reset `pending`: halaman sedang berpindah.
    } catch {
      setPending(null)
      onError?.(`Could not start ${PROVIDERS[provider].name} sign-in. Please try again.`)
    }
  }

  return (
    <div className="mt-6">
      <div className="mb-4 flex items-center gap-3 text-[10px] uppercase tracking-wider text-[#555]">
        <span className="h-px flex-1 bg-[#2A2A2A]" />
        or
        <span className="h-px flex-1 bg-[#2A2A2A]" />
      </div>

      <div className="flex flex-col gap-2">
        {known.map((provider) => (
          <button
            key={provider}
            type="button"
            onClick={() => start(provider)}
            disabled={disabled || pending !== null}
            className="flex h-9 w-full items-center justify-center gap-2 border border-[#2A2A2A] bg-[#111] text-xs font-semibold text-white transition-colors hover:border-white/30 disabled:opacity-50"
          >
            {PROVIDERS[provider].icon}
            {pending === provider ? 'Redirecting…' : `Continue with ${PROVIDERS[provider].name}`}
          </button>
        ))}
      </div>
    </div>
  )
}
