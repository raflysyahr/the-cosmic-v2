import { useState } from 'react'
import { Link } from '@inertiajs/react'
import { ChevronDown } from 'lucide-react'
import client from '../../api/client'
import {
  REACTION_PALETTE,
  applyReaction,
  formatStoryDateTime,
  type StoryPost,
  type StoryReactionCount,
} from './storyTypes'

// Catatan: tailwind.config.js menimpa rounded-full (= 0.75rem), jadi pil selalu rounded-[999px].

interface Reactor {
  user_id: string
  username: string | null
  display_name: string
  emoji: string
  reacted_at: string | null
}

interface ReactorsResponse {
  total: number
  reactions: StoryReactionCount[]
  reactors: Reactor[]
}

interface StoryReactionsProps {
  post: StoryPost
  isAdmin: boolean
  onChange: (post: StoryPost) => void
}

/**
 * Baris reaksi di bawah post. Semua user bisa memilih satu reaksi (tekan lagi untuk
 * mencabut). Admin bisa membuka daftar siapa bereaksi apa.
 */
export default function StoryReactions({ post, isAdmin, onChange }: StoryReactionsProps) {
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [showWho, setShowWho] = useState(false)
  const [who, setWho] = useState<ReactorsResponse | null>(null)
  const [whoError, setWhoError] = useState<string | null>(null)

  const countOf = (emoji: string) => post.reactions.find((r) => r.emoji === emoji)?.count ?? 0

  const react = async (emoji: string) => {
    if (pending) return
    setPending(true)
    setError(null)

    const before = post
    onChange(applyReaction(post, emoji)) // optimistic

    try {
      const res = await client.post(`/discuss/story/${post.id}/reaction`, { emoji })
      onChange({ ...before, ...res.data }) // angka resmi dari server (reactions, my_reaction, reaction_total)
      if (showWho) loadWho()
    } catch (err) {
      onChange(before) // rollback
      setError(err instanceof Error ? err.message : 'Could not save your reaction.')
    } finally {
      setPending(false)
    }
  }

  const loadWho = () => {
    setWhoError(null)
    client
      .get(`/admin/story/${post.id}/reactions`)
      .then((res) => setWho(res.data))
      .catch((err: Error) => setWhoError(err.message))
  }

  const toggleWho = () => {
    const next = !showWho
    setShowWho(next)
    if (next) loadWho()
  }

  return (
    <div className="border-t border-[#222] px-4 py-3">
      <div className="flex flex-wrap gap-1.5" role="group" aria-label="Reactions">
        {REACTION_PALETTE.map((emoji) => {
          const active = post.my_reaction === emoji
          const count = countOf(emoji)

          return (
            <button
              key={emoji}
              type="button"
              aria-pressed={active}
              disabled={pending}
              onClick={() => react(emoji)}
              className={`flex items-center gap-1.5 rounded-[999px] border px-3 py-1.5 text-sm transition-colors disabled:opacity-70 ${
                active
                  ? 'border-[#4a4a4a] bg-[#252525] text-white'
                  : 'border-[#262626] text-neutral-400 hover:border-[#3a3a3a] hover:text-neutral-200'
              }`}
            >
              <span className="text-base leading-none">{emoji}</span>
              {count > 0 && <span className="text-[13px] font-medium tabular-nums">{count}</span>}
            </button>
          )
        })}
      </div>

      {error && <p className="mt-2 text-xs text-red-400">{error}</p>}

      {isAdmin && (
        <>
          <button
            type="button"
            onClick={toggleWho}
            aria-expanded={showWho}
            className="mt-3 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-neutral-500 transition-colors hover:text-neutral-300"
          >
            {post.reaction_total} {post.reaction_total === 1 ? 'reaction' : 'reactions'} · Who reacted
            <ChevronDown className={`h-3.5 w-3.5 transition-transform ${showWho ? 'rotate-180' : ''}`} />
          </button>

          {showWho && (
            <div className="mt-2 rounded-[14px] border border-[#222] bg-[#0b0b0b]">
              {whoError && <p className="px-3 py-3 text-xs text-red-400">{whoError}</p>}
              {!who && !whoError && <p className="px-3 py-3 text-xs text-neutral-500">Loading…</p>}
              {who && who.reactors.length === 0 && (
                <p className="px-3 py-3 text-xs text-neutral-500">No reactions yet.</p>
              )}
              {who && who.reactors.length > 0 && (
                <ul className="divide-y divide-[#1c1c1c]">
                  {who.reactors.map((r) => {
                    const row = (
                      <>
                        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-[999px] bg-[#1c1c1c] text-xs font-semibold text-neutral-300">
                          {r.display_name.charAt(0).toUpperCase() || '?'}
                        </span>
                        <span className="min-w-0 flex-1">
                          <span className="block truncate text-[13px] font-medium text-white">{r.display_name}</span>
                          <span className="block text-[11px] text-neutral-500">{formatStoryDateTime(r.reacted_at)}</span>
                        </span>
                        <span className="text-lg leading-none">{r.emoji}</span>
                      </>
                    )

                    return (
                      <li key={r.user_id}>
                        {r.username ? (
                          <Link href={`/u/${r.username}`} className="flex items-center gap-3 px-3 py-2.5">
                            {row}
                          </Link>
                        ) : (
                          <div className="flex items-center gap-3 px-3 py-2.5">{row}</div>
                        )}
                      </li>
                    )
                  })}
                </ul>
              )}
            </div>
          )}
        </>
      )}
    </div>
  )
}
