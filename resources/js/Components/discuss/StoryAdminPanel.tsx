import { useCallback, useEffect, useState, type FC } from 'react'
import { Pin, Trash2 } from 'lucide-react'
import client from '../../api/client'

interface AdminAnnouncement {
  id: string
  title: string
  body: string
  link_url: string | null
  is_pinned: boolean
  published_at: string | null
  is_published: boolean
}

interface StoryAdminPanelProps {
  /** Dipanggil setelah ada perubahan, supaya daftar yang dilihat user ikut dimuat ulang. */
  onChanged: () => void
}

const inputClass =
  'w-full rounded-lg border border-outline-variant/60 bg-surface-container-low px-3 py-2 text-sm text-on-surface placeholder:text-on-surface-variant/50 focus:border-primary/40 focus:outline-none'

const toLocalInput = (iso: string | null): string => {
  if (!iso) return ''
  const d = new Date(iso)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

/** Panel kelola pemberitahuan — hanya dirender untuk admin platform (server tetap memvalidasi). */
const StoryAdminPanel: FC<StoryAdminPanelProps> = ({ onChanged }) => {
  const [items, setItems] = useState<AdminAnnouncement[]>([])
  const [editingId, setEditingId] = useState<string | null>(null)
  const [title, setTitle] = useState('')
  const [body, setBody] = useState('')
  const [linkUrl, setLinkUrl] = useState('')
  const [pinned, setPinned] = useState(false)
  const [publishAt, setPublishAt] = useState('')
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState<{ kind: 'ok' | 'error'; text: string } | null>(null)

  const fail = (err: unknown) =>
    setMessage({ kind: 'error', text: err instanceof Error ? err.message : 'Something went wrong.' })

  const load = useCallback(() => {
    client.get('/admin/story').then((res) => setItems(res.data?.announcements ?? [])).catch(fail)
  }, [])

  useEffect(load, [load])

  const reset = () => {
    setEditingId(null)
    setTitle('')
    setBody('')
    setLinkUrl('')
    setPinned(false)
    setPublishAt('')
  }

  const startEdit = (item: AdminAnnouncement) => {
    setEditingId(item.id)
    setTitle(item.title)
    setBody(item.body)
    setLinkUrl(item.link_url ?? '')
    setPinned(item.is_pinned)
    setPublishAt(toLocalInput(item.published_at))
    setMessage(null)
  }

  const submit = async () => {
    setBusy(true)
    setMessage(null)
    try {
      const payload = {
        title: title.trim(),
        body: body.trim(),
        link_url: linkUrl.trim() || null,
        is_pinned: pinned,
        // Kosong = tayang sekarang (server yang mengisi).
        published_at: publishAt ? new Date(publishAt).toISOString() : null,
      }
      if (editingId) await client.put(`/admin/story/${editingId}`, payload)
      else await client.post('/admin/story', payload)

      setMessage({ kind: 'ok', text: editingId ? 'Announcement updated.' : 'Announcement published.' })
      reset()
      load()
      onChanged()
    } catch (err) {
      fail(err)
    } finally {
      setBusy(false)
    }
  }

  const remove = async (item: AdminAnnouncement) => {
    if (!window.confirm(`Delete "${item.title}"?`)) return
    try {
      await client.delete(`/admin/story/${item.id}`)
      if (editingId === item.id) reset()
      load()
      onChanged()
    } catch (err) {
      fail(err)
    }
  }

  return (
    <div className="mb-6 rounded-xl border border-outline-variant/30 bg-surface-container-low p-3">
      <h2 className="mb-3 font-label-md text-label-md font-semibold text-on-surface">
        {editingId ? 'Edit announcement' : 'New announcement'}
      </h2>

      <div className="flex flex-col gap-2">
        <input className={inputClass} placeholder="Title" maxLength={120} value={title} onChange={(e) => setTitle(e.target.value)} />
        <textarea className={inputClass} rows={4} maxLength={5000} placeholder="Message" value={body} onChange={(e) => setBody(e.target.value)} />
        <input className={inputClass} placeholder="Link (optional, https://...)" value={linkUrl} onChange={(e) => setLinkUrl(e.target.value)} />
        <div className="flex flex-wrap items-center gap-3">
          <label className="flex items-center gap-1.5 font-label-md text-label-md text-on-surface-variant">
            <input type="checkbox" checked={pinned} onChange={(e) => setPinned(e.target.checked)} />
            Pin to top
          </label>
          <label className="flex items-center gap-1.5 font-label-md text-label-md text-on-surface-variant">
            Publish at
            <input
              type="datetime-local"
              className="rounded-lg border border-outline-variant/60 bg-surface-container-low px-2 py-1 text-sm text-on-surface"
              value={publishAt}
              onChange={(e) => setPublishAt(e.target.value)}
            />
          </label>
        </div>
        <p className="font-label-sm text-label-sm text-on-surface-variant">
          Leave "Publish at" empty to publish now. A future time schedules it.
        </p>

        <div className="flex gap-2">
          <button
            type="button"
            onClick={submit}
            disabled={busy || !title.trim() || !body.trim()}
            className="rounded-lg bg-primary px-4 py-2 font-label-md text-label-md font-semibold text-on-primary transition-colors hover:bg-primary-container disabled:opacity-50"
          >
            {busy ? 'Saving...' : editingId ? 'Save changes' : 'Publish'}
          </button>
          {editingId && (
            <button
              type="button"
              onClick={reset}
              className="rounded-lg border border-outline-variant px-4 py-2 font-label-md text-label-md text-on-surface-variant transition-colors hover:bg-surface-container-high"
            >
              Cancel
            </button>
          )}
        </div>

        {message && (
          <p className={`font-label-md text-label-md ${message.kind === 'ok' ? 'text-green-400' : 'text-red-400'}`}>
            {message.text}
          </p>
        )}
      </div>

      {items.length > 0 && (
        <div className="mt-4 flex flex-col divide-y divide-outline-variant/30 border-t border-outline-variant/30">
          {items.map((item) => (
            <div key={item.id} className="flex items-center gap-2 py-2">
              <button type="button" onClick={() => startEdit(item)} className="min-w-0 flex-1 text-left">
                <span className="flex items-center gap-1.5 font-label-md text-label-md text-on-surface">
                  {item.is_pinned && <Pin className="h-3 w-3 shrink-0" />}
                  <span className="truncate">{item.title}</span>
                </span>
                <span className="font-label-sm text-label-sm text-on-surface-variant">
                  {item.is_published ? 'Published' : 'Scheduled'}
                  {item.published_at ? ` · ${new Date(item.published_at).toLocaleString()}` : ''}
                </span>
              </button>
              <button
                type="button"
                onClick={() => remove(item)}
                aria-label="Delete"
                className="shrink-0 text-on-surface-variant transition-colors hover:text-error"
              >
                <Trash2 className="h-4 w-4" />
              </button>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}

export default StoryAdminPanel
