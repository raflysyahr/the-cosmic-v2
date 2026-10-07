import { useCallback, useEffect, useRef, useState, type ChangeEvent, type FC } from 'react'
import { ImagePlus, Pin, Play, Trash2, X } from 'lucide-react'
import client from '../../api/client'
import { MAX_VIDEO_BYTES, readVideoMeta, type VideoMeta } from '../../lib/media'
import { formatStoryDateTime, type StoryMedia, type StoryPost } from './storyTypes'

// Catatan: tailwind.config.js menimpa rounded-full (= 0.75rem), jadi pil/lingkaran selalu rounded-[999px].

interface StoryAdminPanelProps {
  /** Dipanggil setelah ada perubahan, supaya daftar yang dilihat user ikut dimuat ulang. */
  onChanged: () => void
}

const MAX_IMAGE_BYTES = 8 * 1024 * 1024 // sinkron dengan batas foto di AnnouncementRequest
const MEDIA_ACCEPT = 'image/png,image/jpeg,image/gif,image/webp,video/mp4,video/quicktime,video/webm'

const inputClass =
  'w-full rounded-[12px] border border-[#2a2a2a] bg-[#0b0b0b] px-3.5 py-2.5 text-sm text-white placeholder:text-neutral-600 focus:border-[#4a4a4a] focus:outline-none'

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

/** Panel kelola post Story — hanya dirender untuk admin platform (server tetap memvalidasi). */
const StoryAdminPanel: FC<StoryAdminPanelProps> = ({ onChanged }) => {
  const [items, setItems] = useState<StoryPost[]>([])
  const [editingId, setEditingId] = useState<string | null>(null)
  const [title, setTitle] = useState('')
  const [body, setBody] = useState('')
  const [linkUrl, setLinkUrl] = useState('')
  const [pinned, setPinned] = useState(false)
  const [publishAt, setPublishAt] = useState('')

  // Media: file baru (belum diunggah) atau media lama milik post yang sedang diedit.
  const [file, setFile] = useState<File | null>(null)
  const [preview, setPreview] = useState<string | null>(null)
  const [videoMeta, setVideoMeta] = useState<VideoMeta | null>(null)
  const [imageSize, setImageSize] = useState<{ width: number | null; height: number | null } | null>(null)
  const [existing, setExisting] = useState<StoryMedia | null>(null)
  const [removeExisting, setRemoveExisting] = useState(false)

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

  // Bebaskan object URL preview saat diganti / panel ditutup.
  useEffect(() => {
    return () => {
      if (preview) URL.revokeObjectURL(preview)
    }
  }, [preview])

  const clearFile = () => {
    setFile(null)
    setPreview(null)
    setVideoMeta(null)
    setImageSize(null)
  }

  const reset = () => {
    setEditingId(null)
    setTitle('')
    setBody('')
    setLinkUrl('')
    setPinned(false)
    setPublishAt('')
    clearFile()
    setExisting(null)
    setRemoveExisting(false)
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
    setExisting(item.media)
    setMessage(null)
  }

  const onPick = async (e: ChangeEvent<HTMLInputElement>) => {
    const picked = e.target.files?.[0]
    e.target.value = '' // boleh memilih file yang sama lagi
    if (!picked) return

    const isVideo = picked.type.startsWith('video/')
    const isImage = picked.type.startsWith('image/')

    if (!isVideo && !isImage) return setMessage({ kind: 'error', text: 'Choose a photo or a video.' })
    if (isVideo && picked.size > MAX_VIDEO_BYTES)
      return setMessage({ kind: 'error', text: 'Video is too large (max 50 MB).' })
    if (isImage && picked.size > MAX_IMAGE_BYTES)
      return setMessage({ kind: 'error', text: 'Photo is too large (max 8 MB).' })

    setMessage(null)
    clearFile()

    const url = URL.createObjectURL(picked)
    setFile(picked)
    setPreview(url)
    setRemoveExisting(false)

    // Durasi, ukuran, dan poster dibaca di browser (server tanpa ffmpeg), sama seperti di chat.
    if (isVideo) setVideoMeta(await readVideoMeta(picked))
    else setImageSize(await readImageSize(url))
  }

  const keptExisting = existing && !removeExisting && !file ? existing : null
  const hasMedia = Boolean(file || keptExisting)
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

      if (file) {
        fd.append('media', file)
        const w = videoMeta?.width ?? imageSize?.width
        const h = videoMeta?.height ?? imageSize?.height
        if (w) fd.append('width', String(w))
        if (h) fd.append('height', String(h))
        if (videoMeta?.duration != null) fd.append('duration', String(videoMeta.duration))
        if (videoMeta?.thumbnail) fd.append('thumbnail', new File([videoMeta.thumbnail], 'poster.jpg', { type: 'image/jpeg' }))
      } else if (editingId && existing && removeExisting) {
        fd.append('remove_media', '1')
      }

      // Update multipart: PHP tidak mem-parse body PUT asli, jadi POST + _method=PUT.
      if (editingId) fd.append('_method', 'PUT')

      await client.post(editingId ? `/admin/story/${editingId}` : '/admin/story', fd, {
        timeout: 180_000, // video bisa sampai 50 MB
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
    const label = item.title || item.body.slice(0, 40) || (item.media ? `this ${item.media.type}` : 'this post')
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
        <input ref={fileInput} type="file" accept={MEDIA_ACCEPT} className="hidden" onChange={onPick} />

        {/* Media */}
        {file && preview ? (
          <div className="relative overflow-hidden rounded-[14px] bg-black">
            {file.type.startsWith('video/') ? (
              <video src={preview} controls muted playsInline className="max-h-72 w-full object-contain" />
            ) : (
              <img src={preview} alt="" className="max-h-72 w-full object-contain" />
            )}
            <button
              type="button"
              onClick={clearFile}
              aria-label="Remove media"
              className="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-[999px] bg-black/65 text-white"
            >
              <X className="h-4 w-4" />
            </button>
          </div>
        ) : keptExisting ? (
          <div className="relative overflow-hidden rounded-[14px] bg-black">
            {keptExisting.type === 'video' ? (
              <video
                src={keptExisting.url}
                poster={keptExisting.thumbnail ?? undefined}
                controls
                muted
                playsInline
                className="max-h-72 w-full object-contain"
              />
            ) : (
              <img src={keptExisting.url} alt="" className="max-h-72 w-full object-contain" />
            )}
            <div className="absolute right-2 top-2 flex gap-2">
              <button
                type="button"
                onClick={() => fileInput.current?.click()}
                className="rounded-[999px] bg-black/65 px-3 py-1.5 text-xs font-semibold text-white"
              >
                Replace
              </button>
              <button
                type="button"
                onClick={() => setRemoveExisting(true)}
                aria-label="Remove media"
                className="flex h-8 w-8 items-center justify-center rounded-[999px] bg-black/65 text-white"
              >
                <X className="h-4 w-4" />
              </button>
            </div>
          </div>
        ) : (
          <button
            type="button"
            onClick={() => fileInput.current?.click()}
            className="flex flex-col items-center gap-2 rounded-[14px] border border-dashed border-[#333] px-4 py-7 text-neutral-400 transition-colors hover:border-[#4a4a4a] hover:text-neutral-200"
          >
            <ImagePlus className="h-7 w-7" strokeWidth={1.5} />
            <span className="text-sm font-medium">Add photo or video</span>
            <span className="text-xs text-neutral-600">Photo up to 8 MB · Video up to 50 MB</span>
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
            {busy ? (progress !== null && progress < 100 ? `Uploading ${progress}%` : 'Saving…') : editingId ? 'Save changes' : 'Publish'}
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
            const label = item.title || item.body || (item.media?.type === 'video' ? 'Video' : 'Photo')

            return (
              <div key={item.id} className="flex items-center gap-3 py-3">
                <button type="button" onClick={() => startEdit(item)} className="flex min-w-0 flex-1 items-center gap-3 text-left">
                  {item.media && (
                    <span className="relative h-12 w-12 shrink-0 overflow-hidden rounded-[10px] bg-black">
                      {item.media.thumbnail && (
                        <img src={item.media.thumbnail} alt="" loading="lazy" className="h-full w-full object-cover" />
                      )}
                      {item.media.type === 'video' && (
                        <span className="absolute inset-0 flex items-center justify-center bg-black/30">
                          <Play className="h-4 w-4 fill-white text-white" />
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
