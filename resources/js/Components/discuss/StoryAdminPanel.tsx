import { useCallback, useEffect, useRef, useState, type ChangeEvent, type FC } from 'react'
import { ImagePlus, Pin, Play, Plus, Trash2, X } from 'lucide-react'
import client from '../../api/client'
import { MAX_VIDEO_BYTES, readVideoMeta, type VideoMeta } from '../../lib/media'
import { STORY_MAX_MEDIA, formatStoryDateTime, type StoryMedia, type StoryPost } from './storyTypes'

// Catatan: tailwind.config.js menimpa rounded-full (= 0.75rem), jadi pil/lingkaran selalu rounded-[999px].

interface StoryAdminPanelProps {
  /** Dipanggil setelah ada perubahan, supaya daftar yang dilihat user ikut dimuat ulang. */
  onChanged: () => void
}

/** File yang baru dipilih dan belum diunggah. */
interface DraftFile {
  key: string
  file: File
  preview: string
  isVideo: boolean
  videoMeta: VideoMeta | null
  size: { width: number | null; height: number | null } | null
}

const MAX_IMAGE_BYTES = 8 * 1024 * 1024 // sinkron dengan batas foto di AnnouncementRequest
const MEDIA_ACCEPT = 'image/png,image/jpeg,image/gif,image/webp,video/mp4,video/quicktime,video/webm'

const inputClass =
  'w-full rounded-[12px] border border-[#2a2a2a] bg-[#0b0b0b] px-3.5 py-2.5 text-sm text-white placeholder:text-neutral-600 focus:border-[#4a4a4a] focus:outline-none'

const tileClass = 'relative h-20 w-20 shrink-0 overflow-hidden rounded-[12px] bg-black'

const toLocalInput = (iso: string | null): string => {
  if (!iso) return ''
  const d = new Date(iso)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

const readImageSize = (url: string): Promise<{ width: number | null; height: number | null }> =>
  new Promise((resolve) => {
    const img = new Image()
    img.onload = () => resolve({ width: img.naturalWidth || null, height: img.naturalHeight || null })
    img.onerror = () => resolve({ width: null, height: null })
    img.src = url
  })

const reactionSummary = (post: StoryPost): string =>
  post.reactions.length === 0 ? 'No reactions' : post.reactions.map((r) => `${r.emoji} ${r.count}`).join('  ')

const removeButton = (label: string, onClick: () => void) => (
  <button
    type="button"
    onClick={onClick}
    aria-label={label}
    className="absolute right-1 top-1 flex h-6 w-6 items-center justify-center rounded-[999px] bg-black/70 text-white"
  >
    <X className="h-3.5 w-3.5" />
  </button>
)

/** Panel kelola post Story — hanya dirender untuk admin platform (server tetap memvalidasi). */
const StoryAdminPanel: FC<StoryAdminPanelProps> = ({ onChanged }) => {
  const [items, setItems] = useState<StoryPost[]>([])
  const [editingId, setEditingId] = useState<string | null>(null)
  const [title, setTitle] = useState('')
  const [body, setBody] = useState('')
  const [linkUrl, setLinkUrl] = useState('')
  const [pinned, setPinned] = useState(false)
  const [publishAt, setPublishAt] = useState('')

  // Media: item lama yang dipertahankan (mode edit) + file baru. Urutan tampil = lama dulu, lalu baru.
  const [kept, setKept] = useState<StoryMedia[]>([])
  const [drafts, setDrafts] = useState<DraftFile[]>([])
  const draftsRef = useRef<DraftFile[]>([])
  draftsRef.current = drafts

  const [busy, setBusy] = useState(false)
  const [progress, setProgress] = useState<number | null>(null)
  const [message, setMessage] = useState<{ kind: 'ok' | 'error'; text: string } | null>(null)
  const fileInput = useRef<HTMLInputElement>(null)

  const fail = (err: unknown) =>
    setMessage({ kind: 'error', text: err instanceof Error ? err.message : 'Something went wrong.' })

  const load = useCallback(() => {
    client
      .get('/admin/story')
      .then((res) => setItems(res.data?.announcements ?? []))
      .catch(fail)
  }, [])

  useEffect(load, [load])

  // Bebaskan semua object URL saat panel ditutup.
  useEffect(() => {
    return () => draftsRef.current.forEach((d) => URL.revokeObjectURL(d.preview))
  }, [])

  const clearDrafts = () => {
    draftsRef.current.forEach((d) => URL.revokeObjectURL(d.preview))
    setDrafts([])
  }

  const removeDraft = (key: string) => {
    const target = drafts.find((d) => d.key === key)
    if (target) URL.revokeObjectURL(target.preview)
    setDrafts((prev) => prev.filter((d) => d.key !== key))
  }

  const reset = () => {
    setEditingId(null)
    setTitle('')
    setBody('')
    setLinkUrl('')
    setPinned(false)
    setPublishAt('')
    clearDrafts()
    setKept([])
    setProgress(null)
  }

  const startEdit = (item: StoryPost) => {
    reset()
    setEditingId(item.id)
    setTitle(item.title)
    setBody(item.body)
    setLinkUrl(item.link_url ?? '')
    setPinned(item.is_pinned)
    setPublishAt(toLocalInput(item.published_at))
    setKept(item.media)
    setMessage(null)
  }

  const total = kept.length + drafts.length

  const onPick = async (e: ChangeEvent<HTMLInputElement>) => {
    const picked = Array.from(e.target.files ?? [])
    e.target.value = '' // boleh memilih file yang sama lagi
    if (picked.length === 0) return

    setMessage(null)
    const added: DraftFile[] = []
    const problems: string[] = []
    let room = STORY_MAX_MEDIA - total

    for (const file of picked) {
      const isVideo = file.type.startsWith('video/')
      const isImage = file.type.startsWith('image/')

      if (!isVideo && !isImage) problems.push(`${file.name}: not a photo or video`)
      else if (isVideo && file.size > MAX_VIDEO_BYTES) problems.push(`${file.name}: video over 50 MB`)
      else if (isImage && file.size > MAX_IMAGE_BYTES) problems.push(`${file.name}: photo over 8 MB`)
      else if (room <= 0) problems.push(`${file.name}: limit of ${STORY_MAX_MEDIA} reached`)
      else {
        room -= 1
        const preview = URL.createObjectURL(file)
        // Durasi, ukuran, dan poster dibaca di browser (server tanpa ffmpeg), sama seperti di chat.
        added.push({
          key: `${file.name}-${file.size}-${file.lastModified}-${Math.random().toString(36).slice(2, 7)}`,
          file,
          preview,
          isVideo,
          videoMeta: isVideo ? await readVideoMeta(file) : null,
          size: isImage ? await readImageSize(preview) : null,
        })
      }
    }

    if (added.length) setDrafts((prev) => [...prev, ...added])
    if (problems.length) setMessage({ kind: 'error', text: problems.join(' · ') })
  }

  const hasMedia = total > 0
  const canSubmit = !busy && (hasMedia || (title.trim() !== '' && body.trim() !== ''))

  const submit = async () => {
    setBusy(true)
    setMessage(null)
    setProgress(null)

    try {
      const fd = new FormData()
      fd.append('title', title.trim())
      fd.append('body', body.trim())
      fd.append('link_url', linkUrl.trim())
      fd.append('is_pinned', pinned ? '1' : '0')
      // Kosong = tayang sekarang (server yang mengisi).
      fd.append('published_at', publishAt ? new Date(publishAt).toISOString() : '')

      // File baru memakai key berindeks; metadata tiap file memakai indeks yang sama.
      drafts.forEach((d, i) => {
        fd.append(`media[${i}]`, d.file)
        const w = d.videoMeta?.width ?? d.size?.width
        const h = d.videoMeta?.height ?? d.size?.height
        if (w) fd.append(`widths[${i}]`, String(w))
        if (h) fd.append(`heights[${i}]`, String(h))
        if (d.videoMeta?.duration != null) fd.append(`durations[${i}]`, String(d.videoMeta.duration))
        if (d.videoMeta?.thumbnail) {
          fd.append(`thumbnails[${i}]`, new File([d.videoMeta.thumbnail], 'poster.jpg', { type: 'image/jpeg' }))
        }
      })

      if (editingId) {
        // Item lama yang tidak disebut di keep_media dihapus oleh server.
        fd.append('sync_media', '1')
        kept.forEach((m) => fd.append('keep_media[]', m.id))
        // Update multipart: PHP tidak mem-parse body PUT asli, jadi POST + _method=PUT.
        fd.append('_method', 'PUT')
      }

      await client.post(editingId ? `/admin/story/${editingId}` : '/admin/story', fd, {
        timeout: 300_000, // sampai 10 file, video bisa 50 MB per file
        onUploadProgress: (ev) => setProgress(ev.total ? Math.round((ev.loaded / ev.total) * 100) : null),
      })

      setMessage({ kind: 'ok', text: editingId ? 'Post updated.' : 'Post published.' })
      reset()
      load()
      onChanged()
    } catch (err) {
      fail(err)
    } finally {
      setBusy(false)
      setProgress(null)
    }
  }

  const remove = async (item: StoryPost) => {
    const label = item.title || item.body.slice(0, 40) || (item.media.length ? 'this media post' : 'this post')
    if (!window.confirm(`Delete "${label}"? Its media and reactions will be removed too.`)) return
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
    <div className="mb-6 rounded-[20px] border border-[#262626] bg-[#0f0f0f] p-4">
      <h2 className="mb-3 text-base font-bold text-white">{editingId ? 'Edit post' : 'New post'}</h2>

      <div className="flex flex-col gap-2.5">
        <input ref={fileInput} type="file" accept={MEDIA_ACCEPT} multiple className="hidden" onChange={onPick} />

        {/* Media: tray geser */}
        {hasMedia ? (
          <div>
            <div className="flex gap-2 overflow-x-auto pb-1">
              {kept.map((m) => (
                <div key={m.id} className={tileClass}>
                  {m.type === 'video' && !m.thumbnail ? (
                    <video src={m.url} muted playsInline className="h-full w-full object-cover" />
                  ) : (
                    <img src={m.thumbnail ?? m.url} alt="" className="h-full w-full object-cover" />
                  )}
                  {m.type === 'video' && (
                    <span className="absolute bottom-1 left-1 flex h-5 w-5 items-center justify-center rounded-[999px] bg-black/65">
                      <Play className="h-3 w-3 fill-white text-white" />
                    </span>
                  )}
                  {removeButton('Remove media', () => setKept((prev) => prev.filter((x) => x.id !== m.id)))}
                </div>
              ))}

              {drafts.map((d) => (
                <div key={d.key} className={tileClass}>
                  {d.isVideo ? (
                    <video src={d.preview} muted playsInline className="h-full w-full object-cover" />
                  ) : (
                    <img src={d.preview} alt="" className="h-full w-full object-cover" />
                  )}
                  {d.isVideo && (
                    <span className="absolute bottom-1 left-1 flex h-5 w-5 items-center justify-center rounded-[999px] bg-black/65">
                      <Play className="h-3 w-3 fill-white text-white" />
                    </span>
                  )}
                  <span className="absolute bottom-1 right-1 rounded-[6px] bg-white px-1 text-[9px] font-bold text-black">
                    NEW
                  </span>
                  {removeButton('Remove media', () => removeDraft(d.key))}
                </div>
              ))}

              {total < STORY_MAX_MEDIA && (
                <button
                  type="button"
                  onClick={() => fileInput.current?.click()}
                  aria-label="Add more media"
                  className={`${tileClass} flex items-center justify-center border border-dashed border-[#333] bg-transparent text-neutral-400 transition-colors hover:border-[#4a4a4a] hover:text-neutral-200`}
                >
                  <Plus className="h-6 w-6" />
                </button>
              )}
            </div>
            <p className="mt-1 text-xs text-neutral-600">
              {total}/{STORY_MAX_MEDIA} · shown as a swipeable carousel in this order
            </p>
          </div>
        ) : (
          <button
            type="button"
            onClick={() => fileInput.current?.click()}
            className="flex flex-col items-center gap-2 rounded-[14px] border border-dashed border-[#333] px-4 py-7 text-neutral-400 transition-colors hover:border-[#4a4a4a] hover:text-neutral-200"
          >
            <ImagePlus className="h-7 w-7" strokeWidth={1.5} />
            <span className="text-sm font-medium">Add photos or videos</span>
            <span className="text-xs text-neutral-600">
              Up to {STORY_MAX_MEDIA} · Photo up to 8 MB · Video up to 50 MB
            </span>
          </button>
        )}

        <textarea
          className={inputClass}
          rows={4}
          maxLength={5000}
          placeholder={hasMedia ? 'Write a caption…' : 'Message'}
          value={body}
          onChange={(e) => setBody(e.target.value)}
        />
        <input
          className={inputClass}
          placeholder={hasMedia ? 'Title (optional)' : 'Title'}
          maxLength={120}
          value={title}
          onChange={(e) => setTitle(e.target.value)}
        />
        <input
          className={inputClass}
          placeholder="Link (optional, https://...)"
          value={linkUrl}
          onChange={(e) => setLinkUrl(e.target.value)}
        />

        <div className="flex flex-wrap items-center gap-x-4 gap-y-2 text-[13px] text-neutral-400">
          <label className="flex items-center gap-2">
            <input type="checkbox" checked={pinned} onChange={(e) => setPinned(e.target.checked)} />
            Pin to top
          </label>
          <label className="flex items-center gap-2">
            Publish at
            <input
              type="datetime-local"
              className="rounded-[10px] border border-[#2a2a2a] bg-[#0b0b0b] px-2 py-1 text-sm text-white"
              value={publishAt}
              onChange={(e) => setPublishAt(e.target.value)}
            />
          </label>
        </div>
        <p className="text-xs text-neutral-600">Leave "Publish at" empty to publish now. A future time schedules it.</p>

        {busy && progress !== null && (
          <div className="h-1.5 overflow-hidden rounded-[999px] bg-[#1c1c1c]" aria-label="Upload progress">
            <div className="h-full bg-white transition-[width]" style={{ width: `${progress}%` }} />
          </div>
        )}

        <div className="flex gap-2">
          <button
            type="button"
            onClick={submit}
            disabled={!canSubmit}
            className="rounded-[999px] bg-white px-5 py-2.5 text-sm font-bold text-black transition-colors hover:bg-[#ddd] disabled:opacity-50"
          >
            {busy
              ? progress !== null && progress < 100
                ? `Uploading ${progress}%`
                : 'Saving…'
              : editingId
                ? 'Save changes'
                : 'Publish'}
          </button>
          {editingId && (
            <button
              type="button"
              onClick={reset}
              disabled={busy}
              className="rounded-[999px] border border-[#2e2e2e] px-5 py-2.5 text-sm font-semibold text-neutral-300 transition-colors hover:bg-[#1a1a1a] disabled:opacity-50"
            >
              Cancel
            </button>
          )}
        </div>

        {message && (
          <p className={`text-[13px] ${message.kind === 'ok' ? 'text-green-400' : 'text-red-400'}`}>{message.text}</p>
        )}
      </div>

      {items.length > 0 && (
        <div className="mt-5 flex flex-col divide-y divide-[#1f1f1f] border-t border-[#262626]">
          {items.map((item) => {
            const cover = item.media[0]
            const label = item.title || item.body || (cover?.type === 'video' ? 'Video' : cover ? 'Photo' : 'Post')

            return (
              <div key={item.id} className="flex items-center gap-3 py-3">
                <button type="button" onClick={() => startEdit(item)} className="flex min-w-0 flex-1 items-center gap-3 text-left">
                  {cover && (
                    <span className="relative h-12 w-12 shrink-0 overflow-hidden rounded-[10px] bg-black">
                      {cover.thumbnail && (
                        <img src={cover.thumbnail} alt="" loading="lazy" className="h-full w-full object-cover" />
                      )}
                      {cover.type === 'video' && (
                        <span className="absolute inset-0 flex items-center justify-center bg-black/30">
                          <Play className="h-4 w-4 fill-white text-white" />
                        </span>
                      )}
                      {item.media.length > 1 && (
                        <span className="absolute bottom-0.5 right-0.5 rounded-[6px] bg-black/70 px-1 text-[10px] font-bold text-white">
                          {item.media.length}
                        </span>
                      )}
                    </span>
                  )}
                  <span className="min-w-0">
                    <span className="flex items-center gap-1.5 text-sm font-medium text-white">
                      {item.is_pinned && <Pin className="h-3 w-3 shrink-0 text-neutral-500" />}
                      <span className="truncate">{label}</span>
                    </span>
                    <span className="block text-xs text-neutral-500">
                      {item.is_published ? 'Published' : 'Scheduled'}
                      {item.published_at ? ` · ${formatStoryDateTime(item.published_at)}` : ''}
                    </span>
                    <span className="block text-xs text-neutral-400">{reactionSummary(item)}</span>
                  </span>
                </button>
                <button
                  type="button"
                  onClick={() => remove(item)}
                  aria-label="Delete"
                  className="shrink-0 rounded-[999px] p-2 text-neutral-500 transition-colors hover:bg-[#1c1c1c] hover:text-red-400"
                >
                  <Trash2 className="h-4 w-4" />
                </button>
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}

export default StoryAdminPanel
