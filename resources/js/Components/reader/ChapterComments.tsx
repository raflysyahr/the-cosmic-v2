import { useEffect, useState, type ReactNode } from 'react'
import { Link } from '@inertiajs/react'
import { Loader2, MessageSquare, SmilePlus, Trash2, X } from 'lucide-react'
import client from '../../api/client'
import { useAuth } from '../../contexts/AuthContext'

/**
 * Komentar halaman baca. Komentar = pesan biasa di grup diskusi komik yang sama:
 *  - hanya pesan yang dikirim lewat kotak ini yang tampil sebagai komentar tingkat atas;
 *  - balasan (dari sini ATAU dari chat Discuss) tampil di bawah komentarnya;
 *  - reaksi memakai emote & tabel reaksi Discuss yang sama.
 * Room, chapter, dan penanda komentar ditentukan server dari slug + index di URL.
 */

interface Emote { id: string; code: string; image_url: string | null; unicode: string | null }

interface GroupedReaction {
  emoteId: string
  emoteCode: string | null
  imageUrl: string | null
  unicode: string | null
  count: number
  userIds: string[]
}

interface CommentMsg {
  id: string
  body: string | null
  user: { id: string; display_name: string; avatar_url: string | null } | null
  reply_to: { id: string; body: string | null; is_deleted?: boolean; user: { display_name: string } } | null
  reply_to_id: string | null
  reactions: GroupedReaction[]
  is_deleted: boolean
  created_at: string
  thread_reply_count: number
}

interface ThreadState { open: boolean; loading: boolean; loaded: boolean; items: CommentMsg[]; error?: string }

interface ReplyTarget { id: string; rootId: string; name: string }

interface Meta { current_page: number; last_page: number; total: number }

const MAX_BODY = 2000

function errorText(e: unknown, fallback: string): string {
  return e instanceof Error && e.message ? e.message : fallback
}

function timeAgo(iso: string): string {
  const seconds = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 1000))
  if (seconds < 60) return 'just now'
  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes}m ago`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h ago`
  const days = Math.floor(hours / 24)
  if (days < 30) return `${days}d ago`
  return new Date(iso).toLocaleDateString()
}

/** Aturan reaksi Discuss: satu per user per pesan; sama = cabut, lain = ganti. */
function applyReaction(reactions: GroupedReaction[], emote: Emote, userId: string): GroupedReaction[] {
  const wasSame = reactions.some((r) => r.emoteId === emote.id && r.userIds.includes(userId))

  const without = reactions
    .map((r) => (r.userIds.includes(userId)
      ? { ...r, userIds: r.userIds.filter((id) => id !== userId), count: r.count - 1 }
      : r))
    .filter((r) => r.count > 0)

  if (wasSame) return without

  const existing = without.find((r) => r.emoteId === emote.id)
  if (existing) {
    return without.map((r) => (r === existing ? { ...r, userIds: [...r.userIds, userId], count: r.count + 1 } : r))
  }

  return [...without, {
    emoteId: emote.id,
    emoteCode: emote.code,
    imageUrl: emote.image_url,
    unicode: emote.unicode,
    count: 1,
    userIds: [userId],
  }]
}

function Glyph({ unicode, imageUrl, code }: { unicode: string | null; imageUrl: string | null; code: string | null }) {
  if (unicode) return <span className="text-sm leading-none">{unicode}</span>
  if (imageUrl) return <img src={imageUrl} alt={code ?? ''} className="h-4 w-4 object-contain" draggable={false} />
  return <span className="text-[10px]">{code}</span>
}

function Avatar({ name, url }: { name: string; url: string | null }) {
  return url ? (
    <img src={url} alt="" className="h-7 w-7 shrink-0 rounded-[999px] object-cover" />
  ) : (
    <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-[999px] bg-[#1A1A1A] text-[10px] font-bold uppercase text-[#999]">
      {name.slice(0, 1)}
    </span>
  )
}

interface RowProps {
  msg: CommentMsg
  rootId: string | null
  currentUserId: string
  emotes: Emote[]
  onReact: (msg: CommentMsg, emote: Emote) => void
  onReply: (msg: CommentMsg, rootId: string) => void
  onDelete: (msg: CommentMsg, rootId: string | null) => void
  children?: ReactNode
}

function CommentRow({ msg, rootId, currentUserId, emotes, onReact, onReply, onDelete, children }: RowProps) {
  const [picker, setPicker] = useState(false)
  const name = msg.user?.display_name ?? 'Unknown'
  const own = msg.user?.id === currentUserId
  const thread = rootId ?? msg.id

  return (
    <div className="flex gap-2.5">
      <Avatar name={name} url={msg.user?.avatar_url ?? null} />

      <div className="min-w-0 flex-1">
        <div className="flex items-baseline gap-2">
          <span className="truncate text-xs font-semibold text-white">{name}</span>
          <span className="shrink-0 text-[10px] text-[#555]">{timeAgo(msg.created_at)}</span>
        </div>

        {/* Balasan terhadap balasan lain: tunjukkan siapa yang dibalas. */}
        {msg.reply_to && msg.reply_to.id !== rootId && (
          <p className="mt-0.5 truncate border-l-2 border-[#2A2A2A] pl-2 text-[10px] text-[#555]">
            {msg.reply_to.user.display_name}: {msg.reply_to.is_deleted ? 'Message deleted' : msg.reply_to.body}
          </p>
        )}

        {/* Selalu sebagai teks (React meng-escape), tidak pernah HTML. */}
        <p className="mt-1 whitespace-pre-wrap break-words text-[13px] leading-snug text-[#ddd]">{msg.body}</p>

        <div className="mt-2 flex flex-wrap items-center gap-1.5">
          {msg.reactions.map((r) => {
            const mine = r.userIds.includes(currentUserId)
            const emote = emotes.find((e) => e.id === r.emoteId)
            return (
              <button
                key={r.emoteId}
                type="button"
                disabled={own || !emote}
                onClick={() => emote && onReact(msg, emote)}
                className={`flex items-center gap-1 border px-1.5 py-0.5 text-[11px] transition-colors disabled:cursor-default ${
                  mine ? 'border-white text-white' : 'border-[#2A2A2A] text-[#999] enabled:hover:border-white/30'
                }`}
              >
                <Glyph unicode={r.unicode} imageUrl={r.imageUrl} code={r.emoteCode} />
                <span>{r.count}</span>
              </button>
            )
          })}

          {!own && emotes.length > 0 && (
            <button
              type="button"
              onClick={() => setPicker((v) => !v)}
              aria-label="Add reaction"
              className="flex items-center border border-[#2A2A2A] px-1.5 py-0.5 text-[#555] transition-colors hover:border-white/30 hover:text-white"
            >
              <SmilePlus className="h-3 w-3" />
            </button>
          )}

          <button
            type="button"
            onClick={() => onReply(msg, thread)}
            className="px-1 text-[11px] text-[#555] transition-colors hover:text-white"
          >
            Reply
          </button>

          {own && (
            <button
              type="button"
              onClick={() => onDelete(msg, rootId)}
              aria-label="Delete"
              className="px-1 text-[#555] transition-colors hover:text-red-400"
            >
              <Trash2 className="h-3 w-3" />
            </button>
          )}
        </div>

        {picker && (
          <div className="mt-1.5 flex max-w-xs flex-wrap gap-1 border border-[#2A2A2A] bg-[#111] p-1.5">
            {emotes.map((emote) => (
              <button
                key={emote.id}
                type="button"
                title={emote.code}
                onClick={() => { setPicker(false); onReact(msg, emote) }}
                className="flex h-7 w-7 items-center justify-center transition-colors hover:bg-[#1A1A1A]"
              >
                <Glyph unicode={emote.unicode} imageUrl={emote.image_url} code={emote.code} />
              </button>
            ))}
          </div>
        )}

        {children}
      </div>
    </div>
  )
}

interface Props {
  slug: string
  chapterIndex: number
  /** Untuk tautan "buat grup diskusi" bagi admin bila komik belum punya grup. */
  seriesTitle?: string
}

export default function ChapterComments({ slug, chapterIndex, seriesTitle }: Props) {
  const { user } = useAuth()
  const userId = user?.id

  const [loading, setLoading] = useState(true)
  const [failed, setFailed] = useState(false)
  const [available, setAvailable] = useState(true)
  const [room, setRoom] = useState<{ slug: string; name: string } | null>(null)
  const [emotes, setEmotes] = useState<Emote[]>([])
  const [comments, setComments] = useState<CommentMsg[]>([])
  const [meta, setMeta] = useState<Meta>({ current_page: 1, last_page: 1, total: 0 })
  const [loadingMore, setLoadingMore] = useState(false)
  const [threads, setThreads] = useState<Record<string, ThreadState>>({})
  const [text, setText] = useState('')
  const [sending, setSending] = useState(false)
  const [replyTo, setReplyTo] = useState<ReplyTarget | null>(null)
  const [notice, setNotice] = useState('')

  const base = `/comics/${encodeURIComponent(slug)}/chapters/${chapterIndex}/comments`

  useEffect(() => {
    if (!userId) {
      setLoading(false)
      return
    }

    let cancelled = false
    setLoading(true)
    setFailed(false)

    client.get(base)
      .then((res) => {
        if (cancelled) return
        const d = res.data
        setAvailable(!!d.available)
        setRoom(d.room ?? null)
        setEmotes(d.emotes ?? [])
        setComments(d.data ?? [])
        setMeta(d.meta ?? { current_page: 1, last_page: 1, total: 0 })
      })
      .catch(() => { if (!cancelled) setFailed(true) })
      .finally(() => { if (!cancelled) setLoading(false) })

    return () => { cancelled = true }
  }, [base, userId])

  /** Terapkan perubahan ke satu pesan di daftar utama maupun di semua thread. */
  const mapMessages = (fn: (m: CommentMsg) => CommentMsg) => {
    setComments((prev) => prev.map(fn))
    setThreads((prev) => {
      const next: Record<string, ThreadState> = {}
      for (const [id, t] of Object.entries(prev)) next[id] = { ...t, items: t.items.map(fn) }
      return next
    })
  }

  const loadMore = async () => {
    if (loadingMore || meta.current_page >= meta.last_page) return
    setLoadingMore(true)
    try {
      const res = await client.get(base, { params: { page: meta.current_page + 1 } })
      setComments((prev) => {
        const seen = new Set(prev.map((c) => c.id))
        return [...prev, ...(res.data.data as CommentMsg[]).filter((c) => !seen.has(c.id))]
      })
      setMeta(res.data.meta)
    } catch (e) {
      setNotice(errorText(e, 'Could not load more comments.'))
    } finally {
      setLoadingMore(false)
    }
  }

  const loadThread = async (rootId: string) => {
    setThreads((prev) => ({
      ...prev,
      [rootId]: { open: true, loading: true, loaded: prev[rootId]?.loaded ?? false, items: prev[rootId]?.items ?? [] },
    }))
    try {
      const res = await client.get(`${base}/${rootId}/replies`)
      setThreads((prev) => ({ ...prev, [rootId]: { open: true, loading: false, loaded: true, items: res.data.data ?? [] } }))
      setComments((prev) => prev.map((c) => (c.id === rootId ? { ...c, thread_reply_count: res.data.total ?? c.thread_reply_count } : c)))
    } catch (e) {
      setThreads((prev) => ({
        ...prev,
        [rootId]: { open: true, loading: false, loaded: false, items: [], error: errorText(e, 'Could not load replies.') },
      }))
    }
  }

  const toggleThread = (rootId: string) => {
    const t = threads[rootId]
    if (t?.loaded) {
      setThreads((prev) => ({ ...prev, [rootId]: { ...prev[rootId], open: !prev[rootId].open } }))
    } else {
      loadThread(rootId)
    }
  }

  const submit = async () => {
    const body = text.trim()
    if (!body || sending) return

    setSending(true)
    setNotice('')
    try {
      const res = await client.post(base, { body, reply_to_id: replyTo?.id })
      const created = res.data.message as CommentMsg
      if (res.data.room) setRoom(res.data.room)
      setText('')

      if (replyTo) {
        const rootId = replyTo.rootId
        setReplyTo(null)
        setComments((prev) => prev.map((c) => (c.id === rootId ? { ...c, thread_reply_count: c.thread_reply_count + 1 } : c)))

        if (threads[rootId]?.loaded) {
          setThreads((prev) => ({ ...prev, [rootId]: { ...prev[rootId], open: true, items: [...prev[rootId].items, created] } }))
        } else {
          await loadThread(rootId) // thread belum dimuat: ambil semuanya (sudah termasuk balasan baru)
        }
      } else {
        setComments((prev) => [created, ...prev])
        setMeta((m) => ({ ...m, total: m.total + 1 }))
      }
    } catch (e) {
      setNotice(errorText(e, 'Could not post your comment.'))
    } finally {
      setSending(false)
    }
  }

  const react = async (msg: CommentMsg, emote: Emote) => {
    if (!userId) return

    const previous = msg.reactions
    mapMessages((m) => (m.id === msg.id ? { ...m, reactions: applyReaction(m.reactions, emote, userId) } : m)) // optimistik
    setNotice('')

    try {
      await client.post(`${base}/${msg.id}/reactions`, { emote_id: emote.id })
    } catch (e) {
      // Ditolak server (mis. di-ban): kembalikan seperti semula, jangan tampil seolah-olah sukses.
      mapMessages((m) => (m.id === msg.id ? { ...m, reactions: previous } : m))
      setNotice(errorText(e, 'Could not react to this comment.'))
    }
  }

  const remove = async (msg: CommentMsg, rootId: string | null) => {
    if (!room) return
    try {
      await client.delete(`/rooms/${room.slug}/messages/${msg.id}`)
    } catch (e) {
      setNotice(errorText(e, 'Could not delete this comment.'))
      return
    }

    if (rootId === null) {
      setComments((prev) => prev.filter((c) => c.id !== msg.id))
      setMeta((m) => ({ ...m, total: Math.max(0, m.total - 1) }))
      setThreads((prev) => {
        const next = { ...prev }
        delete next[msg.id]
        return next
      })
    } else {
      setThreads((prev) => ({ ...prev, [rootId]: { ...prev[rootId], items: prev[rootId].items.filter((i) => i.id !== msg.id) } }))
      setComments((prev) => prev.map((c) => (c.id === rootId ? { ...c, thread_reply_count: Math.max(0, c.thread_reply_count - 1) } : c)))
    }
  }

  const startReply = (msg: CommentMsg, rootId: string) => {
    setReplyTo({ id: msg.id, rootId, name: msg.user?.display_name ?? 'Unknown' })
  }

  // ------------------------------------------------------------------ render

  const header = (
    <div className="mb-4 flex items-center justify-between">
      <h2 className="flex items-center gap-2 text-sm font-bold text-white">
        <MessageSquare className="h-4 w-4" />
        Comments
        {user && available && !loading && <span className="text-xs font-normal text-[#555]">{meta.total}</span>}
      </h2>
      {user && available && room && (
        <Link href={`/discuss/${room.slug}`} className="text-[10px] text-[#555] transition-colors hover:text-white">
          Open discussion
        </Link>
      )}
    </div>
  )

  let content: ReactNode

  if (!user) {
    content = (
      <p className="text-xs text-[#777]">
        <Link href="/login" className="text-white underline">Sign in</Link> to read and write comments.
      </p>
    )
  } else if (loading) {
    content = <Loader2 className="h-4 w-4 animate-spin text-[#555]" />
  } else if (failed) {
    content = <p className="text-xs text-[#777]">Comments could not be loaded right now.</p>
  } else if (!available) {
    content = (
      <p className="text-xs text-[#777]">
        Comments open once this comic has a discussion group.
        {user.role === 'admin' && (
          <>
            {' '}
            <Link
              href={`/discuss/comic-${slug}?title=${encodeURIComponent(seriesTitle || slug)}`}
              className="text-white underline"
            >
              Create it
            </Link>
          </>
        )}
      </p>
    )
  } else {
    content = (
      <>
        <div className="mb-5">
          {replyTo && (
            <div className="mb-1.5 flex items-center justify-between border-l-2 border-[#2A2A2A] pl-2 text-[11px] text-[#777]">
              <span>Replying to <span className="text-white">{replyTo.name}</span></span>
              <button type="button" onClick={() => setReplyTo(null)} aria-label="Cancel reply" className="text-[#555] hover:text-white">
                <X className="h-3 w-3" />
              </button>
            </div>
          )}

          <textarea
            value={text}
            onChange={(e) => setText(e.target.value)}
            maxLength={MAX_BODY}
            rows={2}
            placeholder={replyTo ? 'Write a reply…' : 'Write a comment…'}
            className="w-full resize-y border border-[#2A2A2A] bg-[#111] px-3 py-2 text-[13px] text-white placeholder-[#555] outline-none focus:border-white/30"
          />

          <div className="mt-1.5 flex items-center justify-between">
            <span className="text-[10px] text-[#555]">{text.length}/{MAX_BODY}</span>
            <button
              type="button"
              onClick={submit}
              disabled={sending || !text.trim()}
              className="bg-white px-4 py-1.5 text-xs font-bold text-black transition-colors hover:bg-[#ccc] disabled:opacity-40"
            >
              {sending ? 'Posting…' : replyTo ? 'Reply' : 'Post'}
            </button>
          </div>
        </div>

        {comments.length === 0 ? (
          <p className="text-xs text-[#555]">No comments yet. Be the first!</p>
        ) : (
          <div className="flex flex-col gap-5">
            {comments.map((c) => {
              const t = threads[c.id]
              return (
                <CommentRow
                  key={c.id}
                  msg={c}
                  rootId={null}
                  currentUserId={user.id}
                  emotes={emotes}
                  onReact={react}
                  onReply={startReply}
                  onDelete={remove}
                >
                  {c.thread_reply_count > 0 && (
                    <button
                      type="button"
                      onClick={() => toggleThread(c.id)}
                      className="mt-2 text-[11px] font-medium text-[#777] transition-colors hover:text-white"
                    >
                      {t?.open
                        ? 'Hide replies'
                        : `View ${c.thread_reply_count} ${c.thread_reply_count === 1 ? 'reply' : 'replies'}`}
                    </button>
                  )}

                  {t?.open && (
                    <div className="mt-3 flex flex-col gap-4 border-l border-[#2A2A2A] pl-3">
                      {t.loading && <Loader2 className="h-3.5 w-3.5 animate-spin text-[#555]" />}
                      {t.error && <p className="text-[11px] text-red-400">{t.error}</p>}
                      {t.items.map((r) => (
                        <CommentRow
                          key={r.id}
                          msg={r}
                          rootId={c.id}
                          currentUserId={user.id}
                          emotes={emotes}
                          onReact={react}
                          onReply={startReply}
                          onDelete={remove}
                        />
                      ))}
                    </div>
                  )}
                </CommentRow>
              )
            })}

            {meta.current_page < meta.last_page && (
              <button
                type="button"
                onClick={loadMore}
                disabled={loadingMore}
                className="self-center border border-[#2A2A2A] px-4 py-1.5 text-xs text-[#999] transition-colors hover:border-white/30 hover:text-white disabled:opacity-50"
              >
                {loadingMore ? 'Loading…' : 'Load more comments'}
              </button>
            )}
          </div>
        )}

        {notice && <p className="mt-3 text-[11px] text-red-400">{notice}</p>}
      </>
    )
  }

  return (
    <section className="border-t border-[#2A2A2A] px-4 py-6">
      {header}
      {content}
    </section>
  )
}
