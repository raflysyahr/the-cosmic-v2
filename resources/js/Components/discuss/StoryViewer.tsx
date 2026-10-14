import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react'
import { ChevronLeft, ChevronRight, X } from 'lucide-react'
import type { StoryMedia } from './storyTypes'

// Catatan: tailwind.config.js menimpa rounded-full (= 0.75rem), jadi pil/lingkaran selalu rounded-[999px].

/**
 * Penampil layar penuh untuk semua media satu post: geser kiri/kanan (atau tombol
 * panah / tombol keyboard ←→), foto memakai file asli, video diputar otomatis saat
 * slide-nya aktif dan dijeda saat ditinggalkan.
 */
export default function StoryViewer({
  items,
  startIndex,
  onClose,
}: {
  items: StoryMedia[]
  startIndex: number
  onClose: () => void
}) {
  const track = useRef<HTMLDivElement>(null)
  const videos = useRef<(HTMLVideoElement | null)[]>([])
  const [active, setActive] = useState(startIndex)

  const goTo = useCallback((index: number) => {
    const el = track.current
    if (el) el.scrollTo({ left: index * el.clientWidth, behavior: 'smooth' })
  }, [])

  // Mulai dari slide yang diketuk (tanpa animasi, sebelum paint) + kunci scroll halaman di belakang.
  useLayoutEffect(() => {
    const el = track.current
    if (el) el.scrollLeft = startIndex * el.clientWidth

    const previous = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return () => {
      document.body.style.overflow = previous
    }
  }, [startIndex])

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
      if (e.key === 'ArrowRight') goTo(Math.min(items.length - 1, active + 1))
      if (e.key === 'ArrowLeft') goTo(Math.max(0, active - 1))
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [onClose, goTo, active, items.length])

  // Hanya video pada slide aktif yang diputar.
  useEffect(() => {
    videos.current.forEach((video, i) => {
      if (!video) return
      if (i === active) void video.play().catch(() => {})
      else video.pause()
    })
  }, [active])

  const many = items.length > 1

  return (
    <div className="fixed inset-0 z-[110] bg-black" role="dialog" aria-modal="true" aria-label="Story media">
      <div
        ref={track}
        onScroll={(e) => {
          const el = e.currentTarget
          setActive(Math.round(el.scrollLeft / Math.max(1, el.clientWidth)))
        }}
        className="flex h-full w-full snap-x snap-mandatory overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        onClick={(e) => {
          if (e.target === e.currentTarget) onClose()
        }}
      >
        {items.map((m, i) => (
          <div
            key={m.id}
            className="flex h-full w-full min-w-full shrink-0 snap-center items-center justify-center"
            onClick={(e) => {
              if (e.target === e.currentTarget) onClose()
            }}
          >
            {m.type === 'video' ? (
              <video
                ref={(el) => {
                  videos.current[i] = el
                }}
                src={m.url}
                poster={m.thumbnail ?? undefined}
                controls
                playsInline
                preload={i === startIndex ? 'auto' : 'metadata'}
                className="max-h-full max-w-full"
              />
            ) : (
              <img src={m.url} alt="" draggable={false} className="max-h-full max-w-full object-contain" />
            )}
          </div>
        ))}
      </div>

      <button
        type="button"
        aria-label="Close"
        onClick={onClose}
        className="absolute right-3 top-[max(0.75rem,env(safe-area-inset-top))] z-10 flex h-10 w-10 items-center justify-center rounded-[999px] bg-black/60 text-white"
      >
        <X className="h-5 w-5" />
      </button>

      {many && (
        <>
          <span className="absolute left-3 top-[max(0.75rem,env(safe-area-inset-top))] z-10 rounded-[999px] bg-black/60 px-3 py-1.5 text-sm font-semibold text-white">
            {active + 1} / {items.length}
          </span>

          {active > 0 && (
            <button
              type="button"
              aria-label="Previous"
              onClick={() => goTo(active - 1)}
              className="absolute left-3 top-1/2 z-10 hidden h-11 w-11 -translate-y-1/2 items-center justify-center rounded-[999px] bg-black/60 text-white md:flex"
            >
              <ChevronLeft className="h-6 w-6" />
            </button>
          )}
          {active < items.length - 1 && (
            <button
              type="button"
              aria-label="Next"
              onClick={() => goTo(active + 1)}
              className="absolute right-3 top-1/2 z-10 hidden h-11 w-11 -translate-y-1/2 items-center justify-center rounded-[999px] bg-black/60 text-white md:flex"
            >
              <ChevronRight className="h-6 w-6" />
            </button>
          )}
        </>
      )}
    </div>
  )
}
