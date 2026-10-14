import { useCallback, useEffect, useRef, useState, type MouseEvent, type PointerEvent } from 'react'
import { createPortal } from 'react-dom'
import client from '../../api/client'
import { REACTION_PALETTE, applyReaction, type StoryPost } from './storyTypes'

// Catatan: tailwind.config.js menimpa rounded-full (= 0.75rem), jadi pil selalu rounded-[999px].

/**
 * Memberi / mengganti / mencabut reaksi dengan optimistic update (dikembalikan bila gagal).
 * Satu reaksi per user: emoji yang sama = dicabut, emoji lain = mengganti.
 */
export function useStoryReaction(post: StoryPost, onChange: (post: StoryPost) => void) {
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const react = useCallback(
    async (emoji: string) => {
      if (pending) return
      setPending(true)
      setError(null)

      const before = post
      onChange(applyReaction(post, emoji))

      try {
        const res = await client.post(`/discuss/story/${post.id}/reaction`, { emoji })
        onChange({ ...before, ...res.data }) // angka resmi dari server (reactions, my_reaction, reaction_total)
      } catch (err) {
        onChange(before)
        setError(err instanceof Error ? err.message : 'Could not save your reaction.')
      } finally {
        setPending(false)
      }
    },
    [pending, post, onChange],
  )

  return { react, pending, error }
}

/**
 * Hanya reaksi yang jumlahnya > 0. Menekan chip = memberi/mencabut reaksi itu.
 * Chip ditandai `data-no-hold` agar tidak memicu tooltip tahan-lama milik kartu.
 */
export function ReactionChips({
  post,
  pending,
  onReact,
}: {
  post: StoryPost
  pending: boolean
  onReact: (emoji: string) => void
}) {
  if (post.reactions.length === 0) return null

  return (
    <div className="flex flex-wrap gap-1.5" role="group" aria-label="Reactions" data-no-hold>
      {post.reactions.map(({ emoji, count }) => {
        const mine = post.my_reaction === emoji

        return (
          <button
            key={emoji}
            type="button"
            data-no-hold
            aria-pressed={mine}
            disabled={pending}
            onClick={() => onReact(emoji)}
            className={`flex  items-center gap-1.5 rounded-[999px] border px-3 py-1.5 text-sm transition-colors disabled:opacity-70 ${
              mine
                ? 'border-[#4a4a4a] bg-[#252525] text-white'
                : 'border-[#262626] text-neutral-400 hover:border-[#3a3a3a] hover:text-neutral-200'
            }`}
          >
            <span className="text-base leading-none">{emoji}</span>
            <span className="text-[13px] font-medium tabular-nums">{count}</span>
          </button>
        )
      })}
    </div>
  )
}

/** Tooltip pilihan reaksi di dekat titik tahan. Tampil lewat portal agar tidak terpotong layout. */
export function ReactionPicker({
  anchor,
  current,
  onPick,
  onClose,
}: {
  anchor: { x: number; y: number }
  current: string | null
  onPick: (emoji: string) => void
  onClose: () => void
}) {
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', onKey)
    window.addEventListener('scroll', onClose, { capture: true, passive: true })
    return () => {
      document.removeEventListener('keydown', onKey)
      window.removeEventListener('scroll', onClose, { capture: true })
    }
  }, [onClose])

  const width = REACTION_PALETTE.length * 44 + 16
  const left = Math.min(Math.max(anchor.x - width / 2, 8), Math.max(8, window.innerWidth - width - 8))
  // Di atas jari/kursor; jika tidak muat, di bawahnya.
  const top = anchor.y - 72 >= 8 ? anchor.y - 72 : anchor.y + 24

  return createPortal(
    <div className="fixed inset-0 z-[120]" onPointerDown={onClose} onContextMenu={(e) => e.preventDefault()}>
      <div
        role="menu"
        aria-label="Choose a reaction"
        style={{ left, top, width }}
        onPointerDown={(e) => e.stopPropagation()}
        className="absolute flex items-center justify-between rounded-[999px] border border-[#2e2e2e] bg-[#181818] p-2 shadow-2xl"
      >
        {REACTION_PALETTE.map((emoji) => (
          <button
            key={emoji}
            type="button"
            role="menuitem"
            aria-label={`React ${emoji}`}
            onClick={() => onPick(emoji)}
            className={`flex h-11 w-11 items-center justify-center rounded-[999px] text-2xl select-none transition-transform hover:scale-125 active:scale-125 ${
              current === emoji ? 'bg-[#2c2c2c]' : ''
            }`}
          >
            {emoji}
          </button>
        ))}
      </div>
    </div>,
    document.body,
  )
}

/**
 * Tahan (±0,45 dtk) di mana saja pada kartu → `onTrigger`. Klik kanan / menu konteks
 * (long-press Android) dipakai sebagai pemicu yang sama. Gerakan >10 px (scroll/swipe
 * carousel) membatalkan tahan. Klik yang menyusul setelah tahan ditelan agar tidak
 * ikut membuka media. Elemen bertanda `data-no-hold` (tombol reaksi) diabaikan.
 */
export function useLongPress(onTrigger: (point: { x: number; y: number }) => void, delay = 450) {
  const timer = useRef<number | null>(null)
  const origin = useRef<{ x: number; y: number } | null>(null)
  const fired = useRef(false)
  const lastFire = useRef(0)

  const clear = () => {
    if (timer.current !== null) {
      window.clearTimeout(timer.current)
      timer.current = null
    }
  }

  useEffect(() => clear, [])

  const fire = (x: number, y: number) => {
    if (Date.now() - lastFire.current < 800) return // pointer-timer + contextmenu bisa datang berurutan
    lastFire.current = Date.now()
    fired.current = true
    navigator.vibrate?.(12)
    onTrigger({ x, y })
  }

  const blocked = (target: EventTarget) => target instanceof Element && target.closest('[data-no-hold]') !== null

  return {
    onPointerDown: (e: PointerEvent) => {
      fired.current = false
      if ((e.pointerType === 'mouse' && e.button !== 0) || blocked(e.target)) return
      origin.current = { x: e.clientX, y: e.clientY }
      clear()
      timer.current = window.setTimeout(() => {
        timer.current = null
        if (origin.current) fire(origin.current.x, origin.current.y)
      }, delay)
    },
    onPointerMove: (e: PointerEvent) => {
      if (timer.current === null || !origin.current) return
      if (Math.hypot(e.clientX - origin.current.x, e.clientY - origin.current.y) > 10) clear()
    },
    onPointerUp: clear,
    onPointerCancel: clear,
    onPointerLeave: clear,
    onContextMenu: (e: MouseEvent) => {
      if (blocked(e.target)) return
      e.preventDefault()
      clear()
      fire(e.clientX, e.clientY)
    },
    onClickCapture: (e: MouseEvent) => {
      if (fired.current) {
        e.preventDefault()
        e.stopPropagation()
        fired.current = false
      }
    },
  }
}
