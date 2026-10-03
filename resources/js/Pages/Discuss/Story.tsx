import { useCallback, useEffect, useState, type ReactNode } from 'react'
import { ExternalLink, Megaphone, Pin, Settings2 } from 'lucide-react'
import LayoutDiscuss from '../../Components/layout/LayoutDiscuss'
import StoryAdminPanel from '../../Components/discuss/StoryAdminPanel'
import client from '../../api/client'

interface Announcement {
  id: string
  title: string
  body: string
  link_url: string | null
  is_pinned: boolean
  published_at: string | null
  is_new: boolean
}

const formatDate = (iso: string | null): string =>
  iso ? new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : ''

/**
 * Story = pemberitahuan dari admin. User hanya membaca; admin platform juga
 * melihat panel untuk menulis dan mengelola pemberitahuan.
 */
export default function Story({ isAdmin }: { isAdmin: boolean }) {
  const [items, setItems] = useState<Announcement[] | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [managing, setManaging] = useState(false)

  const load = useCallback(() => {
    setError(null)
    client.get('/discuss/story')
      .then((res) => setItems(res.data?.announcements ?? []))
      .catch((err: Error) => setError(err.message))
  }, [])

  useEffect(load, [load])

  // Tandai sudah dibaca setelah daftar tampil. Penanda "New" pada kartu tetap
  // terlihat di kunjungan ini (is_new dihitung server sebelum ditandai);
  // badge di navigasi dihapus lewat event.
  useEffect(() => {
    if (items === null) return
    client.post('/discuss/story/seen')
      .then(() => window.dispatchEvent(new Event('story:seen')))
      .catch(() => { /* badge hanya tambahan */ })
  }, [items === null])

  return (
    <div className="mx-auto max-w-2xl px-3 pb-6 pt-4">
      <div className="mb-4 flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Megaphone className="h-5 w-5 text-on-surface-variant" />
          <h1 className="font-headline-sm text-headline-sm text-on-surface">Story</h1>
        </div>
        {isAdmin && (
          <button
            type="button"
            onClick={() => setManaging((v) => !v)}
            className="flex items-center gap-1.5 font-label-md text-label-md text-on-surface-variant transition-colors hover:text-on-surface"
          >
            <Settings2 className="h-4 w-4" />
            {managing ? 'Close' : 'Manage'}
          </button>
        )}
      </div>

      {isAdmin && managing && <StoryAdminPanel onChanged={load} />}

      {error && <p className="py-6 text-center font-body-sm text-body-sm text-red-400">{error}</p>}
      {items === null && !error && (
        <p className="py-10 text-center font-body-sm text-body-sm text-on-surface-variant">Loading...</p>
      )}
      {items && items.length === 0 && (
        <p className="py-12 text-center font-body-sm text-body-sm text-on-surface-variant">
          No announcements yet.
        </p>
      )}

      <div className="flex flex-col gap-3">
        {items?.map((item) => (
          <article key={item.id} className="rounded-xl border border-outline-variant/30 bg-surface-container-low px-4 py-3.5">
            <div className="mb-1 flex items-center gap-2">
              {item.is_pinned && <Pin className="h-3.5 w-3.5 shrink-0 text-on-surface-variant" />}
              <h2 className="min-w-0 flex-1 font-label-md text-label-md font-semibold text-on-surface">{item.title}</h2>
              {item.is_new && (
                <span className="shrink-0 rounded-[999px] bg-primary px-2 py-0.5 font-label-sm text-label-sm font-semibold text-on-primary">
                  New
                </span>
              )}
            </div>
            <p className="font-label-sm text-label-sm text-on-surface-variant">{formatDate(item.published_at)}</p>
            <p className="mt-2 whitespace-pre-line break-words font-body-sm text-body-sm text-on-surface">{item.body}</p>
            {item.link_url && (
              <a
                href={item.link_url}
                target="_blank"
                rel="noopener noreferrer"
                className="mt-3 inline-flex items-center gap-1.5 font-label-md text-label-md text-primary underline-offset-2 hover:underline"
              >
                Open link <ExternalLink className="h-3.5 w-3.5" />
              </a>
            )}
          </article>
        ))}
      </div>
    </div>
  )
}

Story.layout = (page: ReactNode) => <LayoutDiscuss>{page}</LayoutDiscuss>
