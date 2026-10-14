import { useRef, useState } from 'react'
import { ChevronLeft, ChevronRight, Play } from 'lucide-react'
import { formatDuration } from '../../lib/media'
import type { StoryMedia } from './storyTypes'

// Catatan: tailwind.config.js menimpa rounded-full (= 0.75rem), jadi pil/lingkaran selalu rounded-[999px].

/** Rasio tampilan dibatasi agar feed tidak terlalu tinggi / terlalu gepeng. */
const aspectOf = (media: StoryMedia): number =>
  media.width && media.height ? Math.min(Math.max(media.width / media.height, 0.8), 1.91) : 4 / 3

function Slide({ media, eager, onOpen }: { media: StoryMedia; eager: boolean; onOpen: () => void }) {
  const isVideo = media.type === 'video'

  return (
    <button
      type="button"
      onClick={onOpen}
      aria-label={isVideo ? 'Play video' : 'Open photo'}
      className="relative h-full w-full min-w-full shrink-0 snap-center bg-black"
    >
      {media.thumbnail && (
        <img
          src={media.thumbnail}
          alt=""
          draggable={false}
          loading={eager ? 'eager' : 'lazy'}
          className="h-full w-full object-contain"
        />
      )}

      {isVideo && (
        <>
          <span className="absolute inset-0 flex items-center justify-center">
            <span className="flex h-14 w-14 items-center justify-center rounded-[999px] bg-black/55 backdrop-blur-sm">
              <Play className="h-6 w-6 translate-x-0.5 fill-white text-white" />
            </span>
          </span>
          {media.duration ? (
            <span className="absolute bottom-2 left-2 rounded-[8px] bg-black/55 px-2 py-1 text-[13px] font-medium text-white backdrop-blur-sm">
              {formatDuration(media.duration)}
            </span>
          ) : null}
        </>
      )}
    </button>
  )
}

/**
 * Media post di feed: satu item tampil biasa; beberapa item menjadi carousel geser
 * (scroll-snap) dengan penunjuk titik, penghitung, dan panah di layar lebar.
 * Tinggi mengikuti rasio item pertama agar kartu tidak melompat saat digeser.
 */
export default function StoryCarousel({ media, onOpen }: { media: StoryMedia[]; onOpen: (index: number) => void }) {
  const track = useRef<HTMLDivElement>(null)
  const [active, setActive] = useState(0)
  const many = media.length > 1

  if (media.length === 0) return null

  const goTo = (index: number) => {
    const el = track.current
    if (el) el.scrollTo({ left: index * el.clientWidth, behavior: 'smooth' })
  }

  return (
    <div className="relative mt-3 overflow-hidden bg-black" style={{ aspectRatio: aspectOf(media[0]) }}>
      <div
        ref={track}
        onScroll={(e) => {
          const el = e.currentTarget
          setActive(Math.round(el.scrollLeft / Math.max(1, el.clientWidth)))
        }}
        className="flex h-full w-full snap-x snap-mandatory overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
      >
        {media.map((m, i) => (
          <Slide key={m.id} media={m} eager={i === 0} onOpen={() => onOpen(i)} />
        ))}
      </div>

      {many && (
        <>
          <span className="pointer-events-none absolute right-2 top-2 rounded-[999px] bg-black/60 px-2.5 py-0.5 text-xs font-semibold text-white backdrop-blur-sm">
            {active + 1}/{media.length}
          </span>

          <div className="pointer-events-none absolute inset-x-0 bottom-2 flex justify-center gap-1.5">
            {media.map((m, i) => (
              <span
                key={m.id}
                className={`h-1.5 rounded-[999px] transition-all ${i === active ? 'w-4 bg-white' : 'w-1.5 bg-white/45'}`}
              />
            ))}
          </div>

          {active > 0 && (
            <button
              type="button"
              aria-label="Previous"
              onClick={() => goTo(active - 1)}
              className="absolute left-2 top-1/2 hidden h-9 w-9 -translate-y-1/2 items-center justify-center rounded-[999px] bg-black/55 text-white md:flex"
            >
              <ChevronLeft className="h-5 w-5" />
            </button>
          )}
          {active < media.length - 1 && (
            <button
              type="button"
              aria-label="Next"
              onClick={() => goTo(active + 1)}
              className="absolute right-2 top-1/2 hidden h-9 w-9 -translate-y-1/2 items-center justify-center rounded-[999px] bg-black/55 text-white md:flex"
            >
              <ChevronRight className="h-5 w-5" />
            </button>
          )}
        </>
      )}
    </div>
  )
}
