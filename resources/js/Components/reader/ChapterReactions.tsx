import { useEffect, useRef, useState } from 'react'
import { Link } from '@inertiajs/react'
import client from '../../api/client'
import { useAuth } from '../../contexts/AuthContext'

/**
 * Reaksi untuk HALAMAN chapter (bukan komentar). Tabel & endpoint terpisah dari Discuss:
 * GET/POST /api/series/{slug}/chapters/{index}/reactions. Server menyimpan kunci ASCII
 * (like, love, ...); emoji hanya urusan tampilan di sini.
 */

const REACTIONS = [
  { key: 'like', emoji: '👍', label: 'Like' },
  { key: 'love', emoji: '❤️', label: 'Love' },
  { key: 'laugh', emoji: '😂', label: 'Funny' },
  { key: 'wow', emoji: '😮', label: 'Wow' },
  { key: 'sad', emoji: '😢', label: 'Sad' },
  { key: 'fire', emoji: '🔥', label: 'Fire' },
] as const

interface Summary {
  reactions: { key: string; count: number }[]
  my_reaction: string | null
  total: number
}

const EMPTY: Summary = { reactions: [], my_reaction: null, total: 0 }

/** Sama dengan aturan server: tekan yang sama = cabut, tekan yang lain = ganti. */
function applyToggle(summary: Summary, key: string): Summary {
  const counts = new Map(summary.reactions.map((r) => [r.key, r.count]))

  if (summary.my_reaction) {
    counts.set(summary.my_reaction, (counts.get(summary.my_reaction) ?? 1) - 1)
  }

  const next = summary.my_reaction === key ? null : key
  if (next) counts.set(next, (counts.get(next) ?? 0) + 1)

  const reactions = REACTIONS
    .map((r) => ({ key: r.key as string, count: counts.get(r.key) ?? 0 }))
    .filter((r) => r.count > 0)

  return { reactions, my_reaction: next, total: reactions.reduce((n, r) => n + r.count, 0) }
}

export default function ChapterReactions({ slug, chapterIndex }: { slug: string; chapterIndex: number }) {
  const { user } = useAuth()
  const [summary, setSummary] = useState<Summary>(EMPTY)
  const [busy, setBusy] = useState(false)
  const [notice, setNotice] = useState('')
  const mounted = useRef(true)

  const url = `/series/${encodeURIComponent(slug)}/chapters/${chapterIndex}/reactions`

  useEffect(() => {
    mounted.current = true
    return () => { mounted.current = false }
  }, [])

  // Muat ulang saat chapter atau status login berubah (my_reaction bergantung pada user).
  useEffect(() => {
    let cancelled = false
    setSummary(EMPTY)
    setNotice('')
    client.get(url)
      .then((res) => { if (!cancelled) setSummary(res.data) })
      .catch(() => { /* jumlah reaksi bersifat tambahan; diam saja bila gagal */ })
    return () => { cancelled = true }
  }, [url, user?.id])

  const react = async (key: string) => {
    if (!user) {
      setNotice('login')
      return
    }
    if (busy) return

    const previous = summary
    setNotice('')
    setSummary(applyToggle(previous, key)) // optimistik
    setBusy(true)

    try {
      const res = await client.post(url, { reaction: key })
      if (mounted.current) setSummary(res.data) // pakai angka dari server
    } catch (e) {
      // Server menolak: kembalikan tampilan seperti sebelum diklik, jangan biarkan terlihat sukses.
      if (mounted.current) {
        setSummary(previous)
        setNotice(e instanceof Error ? e.message : 'Could not save your reaction.')
      }
    } finally {
      if (mounted.current) setBusy(false)
    }
  }

  const countOf = (key: string) => summary.reactions.find((r) => r.key === key)?.count ?? 0

  return (
    <div className="border-t border-[#2A2A2A] px-4 py-6">
      <p className="mb-3 text-[10px] font-semibold uppercase tracking-wider text-[#555]">
        How was this chapter?
      </p>

      <div className="flex flex-wrap gap-2">
        {REACTIONS.map(({ key, emoji, label }) => {
          const mine = summary.my_reaction === key
          const count = countOf(key)
          return (
            <button
              key={key}
              type="button"
              onClick={() => react(key)}
              disabled={busy}
              aria-pressed={mine}
              aria-label={`${label}${count ? `, ${count}` : ''}`}
              className={`flex items-center gap-1.5 border px-3 py-1.5 text-xs transition-colors disabled:opacity-60 ${
                mine
                  ? 'border-white bg-white font-bold text-black'
                  : 'border-[#2A2A2A] bg-[#111] text-[#999] hover:border-white/30 hover:text-white'
              }`}
            >
              <span className="text-sm leading-none">{emoji}</span>
              {count > 0 && <span>{count}</span>}
            </button>
          )
        })}
      </div>

      {notice === 'login' ? (
        <p className="mt-3 text-[11px] text-[#777]">
          <Link href="/login" className="text-white underline">Sign in</Link> to react to this chapter.
        </p>
      ) : notice ? (
        <p className="mt-3 text-[11px] text-red-400">{notice}</p>
      ) : null}
    </div>
  )
}
