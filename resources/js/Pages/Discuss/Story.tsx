import { useCallback, useEffect, useState, type ReactNode } from 'react'
import { ExternalLink, Megaphone, Pin, Settings2 } from 'lucide-react'
import LayoutDiscuss from '../../Components/layout/LayoutDiscuss'
import StoryAdminPanel from '../../Components/discuss/StoryAdminPanel'
import StoryCarousel from '../../Components/discuss/StoryCarousel'
import StoryViewer from '../../Components/discuss/StoryViewer'
import {
  ReactionChips,
  ReactionPicker,
  useLongPress,
  useStoryReaction,
} from '../../Components/discuss/StoryReactions'
import { Empty } from '../../Components/profile/SharedContent'
import { formatStoryDate, type StoryMedia, type StoryPost } from '../../Components/discuss/storyTypes'
import client from '../../api/client'

// Catatan: tailwind.config.js menimpa rounded-full (= 0.75rem), jadi pil/lingkaran selalu rounded-[999px].

const CAPTION_CLAMP_CHARS = 220

interface ViewerState {
  items: StoryMedia[]
  index: number
}

function PostCard({
  post,
  onChange,
  onOpenMedia,
}: {
  post: StoryPost
  onChange: (post: StoryPost) => void
  onOpenMedia: (items: StoryMedia[], index: number) => void
}) {
  const [expanded, setExpanded] = useState(false)
  const [picker, setPicker] = useState<{ x: number; y: number } | null>(null)
  const { react, pending, error } = useStoryReaction(post, onChange)

  // Tahan kartu → tooltip pilihan reaksi.
  const hold = useLongPress(setPicker)

  const long = post.body.length > CAPTION_CLAMP_CHARS
  const clamped = long && !expanded
  const hasFooter = post.reactions.length > 0 || error

  return (
    <>
    <article
      {...hold}
      className="select-none overflow-hidden rounded-[20px] border border-[#262626] bg-[#0f0f0f] [-webkit-touch-callout:none]"
    >
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

      {/* Media (carousel bila lebih dari satu) */}
      <StoryCarousel media={post.media} onOpen={(index) => onOpenMedia(post.media, index)} />

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
                  data-no-hold
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
            data-no-hold
            className="inline-flex items-center gap-1.5 rounded-[999px] border border-[#2e2e2e] px-3.5 py-2 text-[13px] font-semibold text-neutral-200 transition-colors hover:bg-[#1a1a1a]"
          >
            Open link <ExternalLink className="h-3.5 w-3.5" />
          </a>
        </div>
      )}

      {/* Reaksi: hanya yang > 0; sisanya lewat tahan kartu */}
      <div className={hasFooter ? 'px-4 pb-4 pt-3' : 'pb-4'}>
        <ReactionChips post={post} pending={pending} onReact={react} />
        {error && <p className="mt-2 text-xs text-red-400">{error}</p>}
      </div>

    </article>

    {/* Di luar <article>: event dari portal bergelembung lewat React tree dan akan
        mengenai handler tahan-lama / klik milik kartu. */}
    {picker && (
      <ReactionPicker
        anchor={picker}
        current={post.my_reaction}
        onClose={() => setPicker(null)}
        onPick={(emoji) => {
          setPicker(null)
          void react(emoji)
        }}
      />
    )}
    </>
  )
}

/**
 * Story = post dari admin (teks, atau carousel foto/video, dengan caption). User
 * membaca dan memberi reaksi (tahan post untuk memilih emoji); admin platform juga
 * melihat panel untuk menulis dan mengelola post.
 */
export default function Story({ isAdmin }: { isAdmin: boolean }) {
  const [items, setItems] = useState<StoryPost[] | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [managing, setManaging] = useState(false)
  const [viewer, setViewer] = useState<ViewerState | null>(null)

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

  return (
    <div className="min-h-full bg-[#0b0b0b] px-4 pb-10 pt-6">
      <div className="mb-5 flex items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <span className="flex h-11 w-11 items-center justify-center rounded-[999px] bg-[#1c1c1c] text-neutral-300">
            <Megaphone className="h-5 w-5" strokeWidth={1.6} />
          </span>
          <div>
            <h1 className="text-[22px] font-bold leading-tight text-white">Story</h1>
            {items && items.length > 0 && <p className="text-xs text-neutral-600">Hold a post to react</p>}
          </div>
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
          <PostCard
            key={post.id}
            post={post}
            onChange={replacePost}
            onOpenMedia={(media, index) => setViewer({ items: media, index })}
          />
        ))}
      </div>

      {viewer && <StoryViewer items={viewer.items} startIndex={viewer.index} onClose={() => setViewer(null)} />}
    </div>
  )
}

Story.layout = (page: ReactNode) => <LayoutDiscuss>{page}</LayoutDiscuss>
