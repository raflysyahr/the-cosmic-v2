import { useCallback, useEffect, useState, type ReactNode } from 'react'
import { ExternalLink, Megaphone, Pin, Play, Settings2 } from 'lucide-react'
import LayoutDiscuss from '../../Components/layout/LayoutDiscuss'
import StoryAdminPanel from '../../Components/discuss/StoryAdminPanel'
import StoryReactions from '../../Components/discuss/StoryReactions'
import { Empty, MediaViewer, type MediaItem } from '../../Components/profile/SharedContent'
import { formatStoryDate, type StoryPost } from '../../Components/discuss/storyTypes'
import { formatDuration } from '../../lib/media'
import client from '../../api/client'

// Catatan: tailwind.config.js menimpa rounded-full (= 0.75rem), jadi pil/lingkaran selalu rounded-[999px].

const CAPTION_CLAMP_CHARS = 220

/** Rasio tampilan media dibatasi agar feed tidak terlalu tinggi / terlalu gepeng. */
const aspectOf = (media: NonNullable<StoryPost['media']>): number => {
  if (!media.width || !media.height) return 4 / 3
  return Math.min(Math.max(media.width / media.height, 0.8), 1.91)
}

function PostMedia({ media, onOpen }: { media: NonNullable<StoryPost['media']>; onOpen: () => void }) {
  const isVideo = media.type === 'video'

  return (
    <button
      type="button"
      onClick={onOpen}
      aria-label={isVideo ? 'Play video' : 'Open photo'}
      style={{ aspectRatio: aspectOf(media) }}
      className="relative mt-3 block w-full overflow-hidden bg-black"
    >
      {media.thumbnail ? (
        <img src={media.thumbnail} alt="" loading="lazy" className="h-full w-full object-cover" />
      ) : null}

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

function PostCard({
  post,
  isAdmin,
  onChange,
  onOpenMedia,
}: {
  post: StoryPost
  isAdmin: boolean
  onChange: (post: StoryPost) => void
  onOpenMedia: (post: StoryPost) => void
}) {
  const [expanded, setExpanded] = useState(false)
  const long = post.body.length > CAPTION_CLAMP_CHARS
  const clamped = long && !expanded

  return (
    <article className="overflow-hidden rounded-[20px] border border-[#262626] bg-[#0f0f0f]">
      {/* Header */}
      <div className="flex items-center gap-3 px-4 pt-4">
        <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-[999px] bg-[#1c1c1c] text-neutral-300">
          <Megaphone className="h-5 w-5" strokeWidth={1.6} />
        </span>
        <div className="min-w-0 flex-1">
          <p className="text-sm font-semibold text-white">Announcement</p>
          <p className="text-xs text-neutral-500">{formatStoryDate(post.published_at)}</p>
        </div>
        {post.is_pinned && <Pin className="h-4 w-4 shrink-0 text-neutral-500" aria-label="Pinned" />}
        {post.is_new && (
          <span className="shrink-0 rounded-[999px] bg-white px-2.5 py-0.5 text-[11px] font-bold text-black">New</span>
        )}
      </div>

      {/* Media */}
      {post.media && <PostMedia media={post.media} onOpen={() => onOpenMedia(post)} />}

      {/* Judul + caption */}
      {(post.title || post.body) && (
        <div className="px-4 pt-3">
          {post.title && <h2 className="break-words text-base font-bold text-white">{post.title}</h2>}
          {post.body && (
            <>
              <p
                className={`whitespace-pre-line break-words text-[15px] leading-snug text-neutral-200 ${post.title ? 'mt-1' : ''}`}
                style={
                  clamped
                    ? { display: '-webkit-box', WebkitLineClamp: 4, WebkitBoxOrient: 'vertical', overflow: 'hidden' }
                    : undefined
                }
              >
                {post.body}
              </p>
              {long && (
                <button
                  type="button"
                  onClick={() => setExpanded((v) => !v)}
                  className="mt-1 text-[13px] font-semibold text-neutral-500 transition-colors hover:text-neutral-300"
                >
                  {expanded ? 'Show less' : 'Show more'}
                </button>
              )}
            </>
          )}
        </div>
      )}

      {post.link_url && (
        <div className="px-4 pt-3">
          <a
            href={post.link_url}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-1.5 rounded-[999px] border border-[#2e2e2e] px-3.5 py-2 text-[13px] font-semibold text-neutral-200 transition-colors hover:bg-[#1a1a1a]"
          >
            Open link <ExternalLink className="h-3.5 w-3.5" />
          </a>
        </div>
      )}

      <div className="mt-3">
        <StoryReactions post={post} isAdmin={isAdmin} onChange={onChange} />
      </div>
    </article>
  )
}

/**
 * Story = post dari admin (teks, foto atau video, dengan caption). User membaca dan
 * memberi reaksi; admin platform juga melihat panel untuk menulis, mengelola, dan
 * melihat siapa bereaksi apa.
 */
export default function Story({ isAdmin }: { isAdmin: boolean }) {
  const [items, setItems] = useState<StoryPost[] | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [managing, setManaging] = useState(false)
  const [viewer, setViewer] = useState<MediaItem | null>(null)

  const load = useCallback(() => {
    setError(null)
    client
      .get('/discuss/story')
      .then((res) => setItems(res.data?.announcements ?? []))
      .catch((err: Error) => setError(err.message))
  }, [])

  useEffect(load, [load])

  // Tandai sudah dibaca setelah daftar tampil. Penanda "New" pada kartu tetap
  // terlihat di kunjungan ini (is_new dihitung server sebelum ditandai);
  // badge di navigasi dihapus lewat event.
  useEffect(() => {
    if (items === null) return
    client
      .post('/discuss/story/seen')
      .then(() => window.dispatchEvent(new Event('story:seen')))
      .catch(() => {
        /* badge hanya tambahan */
      })
  }, [items === null])

  const replacePost = useCallback((next: StoryPost) => {
    setItems((prev) => prev?.map((p) => (p.id === next.id ? next : p)) ?? prev)
  }, [])

  const openMedia = (post: StoryPost) => {
    if (!post.media) return
    setViewer({
      id: post.id,
      type: post.media.type,
      url: post.media.url,
      thumbnail: post.media.thumbnail,
      duration: post.media.duration,
      createdAt: post.published_at,
    })
  }

  return (
    <div className="min-h-full bg-[#0b0b0b] px-4 pb-10 pt-6">
      <div className="mb-5 flex items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <span className="flex h-11 w-11 items-center justify-center rounded-[999px] bg-[#1c1c1c] text-neutral-300">
            <Megaphone className="h-5 w-5" strokeWidth={1.6} />
          </span>
          <h1 className="text-[22px] font-bold text-white">Story</h1>
        </div>
        {isAdmin && (
          <button
            type="button"
            onClick={() => setManaging((v) => !v)}
            className="flex items-center gap-2 rounded-[999px] border border-[#2e2e2e] px-4 py-2 text-[13px] font-semibold text-neutral-200 transition-colors hover:bg-[#1a1a1a]"
          >
            <Settings2 className="h-4 w-4" />
            {managing ? 'Close' : 'Manage'}
          </button>
        )}
      </div>

      {isAdmin && managing && <StoryAdminPanel onChanged={load} />}

      {error && (
        <div className="flex flex-col items-center gap-3 py-12 text-center">
          <p className="text-sm text-red-400">{error}</p>
          <button
            type="button"
            onClick={load}
            className="rounded-[999px] border border-[#2e2e2e] px-4 py-2 text-xs font-semibold text-neutral-300 transition-colors hover:bg-[#1a1a1a]"
          >
            Try again
          </button>
        </div>
      )}

      {items === null && !error && (
        <div className="flex flex-col gap-4">
          {[0, 1].map((i) => (
            <div key={i} className="animate-pulse rounded-[20px] border border-[#262626] bg-[#0f0f0f] p-4">
              <div className="flex items-center gap-3">
                <div className="h-10 w-10 rounded-[999px] bg-[#1c1c1c]" />
                <div className="h-4 w-32 rounded-[999px] bg-[#1c1c1c]" />
              </div>
              <div className="mt-4 h-40 rounded-[12px] bg-[#151515]" />
            </div>
          ))}
        </div>
      )}

      {items && items.length === 0 && <Empty icon={Megaphone} text="No posts yet" />}

      <div className="flex flex-col gap-4">
        {items?.map((post) => (
          <PostCard key={post.id} post={post} isAdmin={isAdmin} onChange={replacePost} onOpenMedia={openMedia} />
        ))}
      </div>

      {viewer && <MediaViewer item={viewer} onClose={() => setViewer(null)} />}
    </div>
  )
}

Story.layout = (page: ReactNode) => <LayoutDiscuss>{page}</LayoutDiscuss>
