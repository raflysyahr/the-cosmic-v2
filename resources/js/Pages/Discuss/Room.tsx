import { useState, useEffect, useCallback, useMemo, useRef } from 'react'
import { router } from '@inertiajs/react'
import MessageList from '../../Components/discuss/MessageList'
import MessageInput from '../../Components/discuss/MessageInput'
import EmojiPicker from '../../Components/discuss/EmojiPicker'
import type { Message } from '../../Components/discuss/MessageItem'
import MessageItem from '../../Components/discuss/MessageItem'
import type { GroupedReaction } from '../../Components/discuss/ReactionBar'
import { apiFetch } from '../../api/fetch'
import client from '../../api/client'
import type { EditAttachment } from '../../Components/discuss/MessageInput'
import type { VideoMeta } from '../../lib/media'
import { putBlob } from '../../lib/mediaCache'
import { usePopup } from '../../contexts/PopupContext'
import Popup from '../../Components/ui/Popup'
import ReportReasonPicker from '../../Components/discuss/ReportReasonPicker'
import ModalHost from '../../Components/ui/Modal'
import { ArrowLeft, Megaphone, Pin, PinOff, X, MicOff, User, Users } from 'lucide-react'

interface RoomData {
  id: string
  slug: string
  name: string
  cover_url: string | null
  online_count: number
  context_type: string | null
}

interface DirectRecipient {
  id: string
  display_name: string
  avatar_url: string | null
}

interface PinnedMessage {
  id: string
  body: string
  created_at: string
  user: { id: string; display_name: string } | null
}

interface Member {
  userId: string
  username: string | null
  displayName: string
  avatarUrl: string | null
  role: 'member' | 'moderator' | 'admin'
  rank: { name: string; color: string } | null
  xpPoints: number
  isOnline: boolean
  mutedUntil: string | null
}

interface PageProps {
  room: RoomData
  directRecipient: DirectRecipient | null
  messages: Message[]
  members: Member[]
  emotes: { id: string; code: string; image_url: string | null; unicode: string | null }[]
  currentUserId: string
  pinnedMessages: PinnedMessage[]
}



async function extractErrorMessage(response: Response, fallback: string): Promise<string> {
  const body = await response.json().catch(() => null)
  const firstError = body?.errors ? Object.values(body.errors)[0] : null
  if (Array.isArray(firstError) && typeof firstError[0] === 'string') return firstError[0]
  if (typeof body?.message === 'string') return body.message
  return fallback
}

/** Formats a remaining duration in ms as "1h 05m 30s" / "05m 30s" / "30s". */
function formatCountdown(ms: number): string {
  const totalSeconds = Math.max(0, Math.ceil(ms / 1000))
  const hours = Math.floor(totalSeconds / 3600)
  const minutes = Math.floor((totalSeconds % 3600) / 60)
  const seconds = totalSeconds % 60

  const pad = (n: number) => String(n).padStart(2, '0')

  if (hours > 0) return `${hours}h ${pad(minutes)}m ${pad(seconds)}s`
  if (minutes > 0) return `${minutes}m ${pad(seconds)}s`
  return `${seconds}s`
}

// Multipart fields for a video upload: the file, plus the duration/size/poster
// frame the browser read (the server has no ffmpeg to do it).
function appendVideoFields(formData: FormData, file: File, meta?: VideoMeta) {
  formData.append('video', file)
  if (meta?.duration != null) formData.append('duration', String(meta.duration))
  if (meta?.width) formData.append('width', String(meta.width))
  if (meta?.height) formData.append('height', String(meta.height))
  if (meta?.thumbnail) formData.append('thumbnail', meta.thumbnail, 'thumbnail.jpg')
}

export default function DiscussRoom(props: PageProps) {
  // props.messages datang dari server urut DESC (terbaru dulu, lihat MessageService::paginate).
  // Balik dulu sebelum render pertama; kalau tidak, pesan terbaru sempat tampil di atas
  // sampai fetch di bawah menggantinya.
  const [messages, setMessages] = useState<Message[]>(() => [...(props.messages ?? [])].reverse())
  const [reactionAnimations, setReactionAnimations] = useState<Record<string, 'pop' | 'bump'>>({})
  const [members, setMembers] = useState<Member[]>(props.members ?? [])
  const [replyTo, setReplyTo] = useState<Message | undefined>(undefined)
  const [showEmoji, setShowEmoji] = useState(false)
  const [emojiInsert, setEmojiInsert] = useState<{ unicode: string; id: number } | null>(null)
  const [loading, setLoading] = useState(false)
  const [joining, setJoining] = useState(false)
  const [joinError, setJoinError] = useState<string | null>(null)
  const [now, setNow] = useState(() => Date.now())
  const [pinnedMessages, setPinnedMessages] = useState<PinnedMessage[]>(props.pinnedMessages)
  const [showPinnedModal, setShowPinnedModal] = useState(false)
  const currentUserId = props.currentUserId
  const { popup, showPopup, closePopup } = usePopup()

  const room = props.room
  const directRecipient = props.directRecipient
  const isDirectChat = room.context_type === 'direct'

  // Direct chats always start with both participants as members, so this
  // only matters for group rooms (e.g. browsing a public room before joining).
  const isMember = useMemo(
    () => isDirectChat || members.some((m) => m.userId === currentUserId),
    [isDirectChat, members, currentUserId],
  )

  const currentMember = useMemo(
    () => members.find((m) => m.userId === currentUserId),
    [members, currentUserId],
  )

  const mutedUntilMs = useMemo(() => {
    if (!currentMember?.mutedUntil) return null
    const ts = new Date(currentMember.mutedUntil).getTime()
    return Number.isNaN(ts) ? null : ts
  }, [currentMember?.mutedUntil])

  // Recomputed every second via `now`, so the mute banner disappears on its
  // own the moment the timer hits zero, without needing a page reload.
  const isMuted = mutedUntilMs !== null && mutedUntilMs > now

  useEffect(() => {
    if (mutedUntilMs === null) return
    const interval = setInterval(() => setNow(Date.now()), 1000)
    return () => clearInterval(interval)
  }, [mutedUntilMs])

  const refreshMembers = useCallback(() => {
    apiFetch(`/api/rooms/${room.slug}/members`)
      .then((r) => (r.ok ? r.json() : Promise.reject(new Error('fetch members failed'))))
      .then((m: Member[]) => setMembers(m))
      .catch(() => {})
  }, [room.slug])

  // Once the countdown reaches zero, refresh from the server so the member's
  // real mutedUntil (now null or in the past) replaces the stale local value.
  useEffect(() => {
    if (mutedUntilMs !== null && mutedUntilMs <= now) {
      refreshMembers()
    }
  }, [mutedUntilMs, now, refreshMembers])

  const handleJoin = useCallback(() => {
    setJoining(true)
    setJoinError(null)
    apiFetch(`/api/rooms/${room.slug}/join`, { method: 'POST' })
      .then(async (r) => {
        if (!r.ok) {
          const message = await extractErrorMessage(r, 'Could not join this room.')
          throw new Error(message)
        }
        return apiFetch(`/api/rooms/${room.slug}/members`)
      })
      .then((r) => (r.ok ? r.json() : Promise.reject(new Error('fetch members failed'))))
      .then((m: Member[]) => setMembers(m))
      .catch((err: Error) => (setJoinError(err.message || 'Could not join this room.'), showPopup({ type: 'warning', title: 'ERROR!!!', message: err.message || 'Could not join this room.' })))
      .finally(() => setJoining(false))
  }, [room.slug])

  const handleSend = useCallback(async (body: string, replyToId?: string): Promise<boolean> => {
    const tempId = `temp-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`
    const optimistic: Message = {
      id: tempId,
      body,
      type: 'text',
      user: {
        id: currentUserId,
        display_name: props.user?.display_name ?? 'You',
        avatar_url: null,
      },
      reply_count: 0,
      attachments: [],
      reactions: [],
      is_edited: false,
      is_deleted: false,
      created_at: new Date().toISOString(),
      sendStatus: 'sending',
      ...(replyToId ? { reply_to: { id: replyToId, body: '', user: { display_name: '' } } } : {}),
    }

    setMessages((prev) => [...prev, optimistic])
    setReplyTo(undefined)

    try {
      const payload: Record<string, unknown> = { body }
      if (replyToId) payload.reply_to_id = replyToId
      const r = await apiFetch(`/api/rooms/${room.slug}/messages`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      })
      if (!r.ok) throw new Error('send failed')
      const res: { message: Message } = await r.json()
      // Replace temp message with the real one from the server. Guard
      // against a race where the broadcast echo (WebSocket) arrives
      // before this HTTP response does — in that case the real message
      // is already in state (added by the MessageSent listener below),
      // so just drop the temp placeholder instead of adding a duplicate.
      setMessages((prev) => {
        const alreadyBroadcast = prev.some((m) => m.id === res.message.id)
        if (alreadyBroadcast) {
          return prev.filter((m) => m.id !== tempId)
        }
        return prev.map((m) => m.id === tempId ? { ...res.message, sendStatus: 'sent' } : m)
      })
      return true
    } catch {
      // Mark the optimistic message as failed
      setMessages((prev) => prev.map((m) => m.id === tempId ? { ...m, sendStatus: 'failed' } : m))
      return false
    }
  }, [room.slug, currentUserId, props.user?.display_name])

  // Original File objects of uploads still in flight / failed, keyed by temp
  // message id, so a failed upload can be retried (the server never got it).
  const pendingUploads = useRef(new Map<string, { kind: 'image' | 'file' | 'video'; file: File; meta?: VideoMeta }>())

  const handleSendImage = useCallback(async (file: File, caption: string, replyToId?: string): Promise<boolean> => {
    const tempId = `temp-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`
    pendingUploads.current.set(tempId, { kind: 'image', file })
    const localPreviewUrl = URL.createObjectURL(file)
    const optimistic: Message = {
      id: tempId,
      body: caption || null,
      type: 'image',
      user: {
        id: currentUserId,
        display_name: props.user?.display_name ?? 'You',
        avatar_url: null,
      },
      reply_count: 0,
      attachments: [localPreviewUrl],
      reactions: [],
      is_edited: false,
      is_deleted: false,
      created_at: new Date().toISOString(),
      sendStatus: 'sending',
      ...(replyToId ? { reply_to: { id: replyToId, body: '', user: { display_name: '' } } } : {}),
    }

    setMessages((prev) => [...prev, optimistic])
    setReplyTo(undefined)

    try {
      const formData = new FormData()
      formData.append('image', file)
      if (caption) formData.append('body', caption)
      if (replyToId) formData.append('reply_to_id', replyToId)
      const res = await client.post(`/rooms/${room.slug}/messages`, formData)
      const message: Message = res.data.message
      setMessages((prev) => {
        const alreadyBroadcast = prev.some((m) => m.id === message.id)
        if (alreadyBroadcast) {
          return prev.filter((m) => m.id !== tempId)
        }
        return prev.map((m) => m.id === tempId ? { ...message, sendStatus: 'sent' } : m)
      })
      URL.revokeObjectURL(localPreviewUrl)
      pendingUploads.current.delete(tempId)
      // We already have the bytes locally — cache them under the final URL so
      // opening our own photo never re-downloads it from the server.
      if (message.attachments[0]) putBlob(message.attachments[0], file)
      return true
    } catch {
      setMessages((prev) => prev.map((m) => m.id === tempId ? { ...m, sendStatus: 'failed' } : m))
      return false
    }
  }, [room.slug, currentUserId, props.user?.display_name])

  const handleSendFile = useCallback(async (file: File, caption: string, replyToId?: string): Promise<boolean> => {
    const tempId = `temp-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`
    pendingUploads.current.set(tempId, { kind: 'file', file })
    const optimistic: Message = {
      id: tempId,
      body: caption || null,
      type: 'file',
      user: {
        id: currentUserId,
        display_name: props.user?.display_name ?? 'You',
        avatar_url: null,
      },
      reply_count: 0,
      attachments: [],
      metadata: { file: { name: file.name, size: file.size, mime: file.type } },
      reactions: [],
      is_edited: false,
      is_deleted: false,
      created_at: new Date().toISOString(),
      sendStatus: 'sending',
      ...(replyToId ? { reply_to: { id: replyToId, body: '', user: { display_name: '' } } } : {}),
    }

    setMessages((prev) => [...prev, optimistic])
    setReplyTo(undefined)

    try {
      const formData = new FormData()
      formData.append('file', file)
      if (caption) formData.append('body', caption)
      if (replyToId) formData.append('reply_to_id', replyToId)
      // Documents can be large — override the client's default 15s timeout.
      const res = await client.post(`/rooms/${room.slug}/messages`, formData, { timeout: 120000 })
      const message: Message = res.data.message
      setMessages((prev) => {
        const alreadyBroadcast = prev.some((m) => m.id === message.id)
        if (alreadyBroadcast) {
          return prev.filter((m) => m.id !== tempId)
        }
        return prev.map((m) => m.id === tempId ? { ...message, sendStatus: 'sent' } : m)
      })
      pendingUploads.current.delete(tempId)
      if (message.attachments[0]) putBlob(message.attachments[0], file)
      return true
    } catch {
      setMessages((prev) => prev.map((m) => m.id === tempId ? { ...m, sendStatus: 'failed' } : m))
      return false
    }
  }, [room.slug, currentUserId, props.user?.display_name])

  const handleSendVideo = useCallback(async (file: File, caption: string, replyToId?: string, meta?: VideoMeta): Promise<boolean> => {
    const tempId = `temp-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`
    pendingUploads.current.set(tempId, { kind: 'video', file, meta })
    // Show the poster frame right away, while the upload is still running.
    const localThumb = meta?.thumbnail ? URL.createObjectURL(meta.thumbnail) : null
    const optimistic: Message = {
      id: tempId,
      body: caption || null,
      type: 'video',
      user: {
        id: currentUserId,
        display_name: props.user?.display_name ?? 'You',
        avatar_url: null,
      },
      reply_count: 0,
      attachments: [],
      metadata: {
        video: {
          name: file.name,
          size: file.size,
          mime: file.type,
          duration: meta?.duration ?? null,
          width: meta?.width ?? null,
          height: meta?.height ?? null,
          thumbnail: localThumb,
        },
      },
      reactions: [],
      is_edited: false,
      is_deleted: false,
      created_at: new Date().toISOString(),
      sendStatus: 'sending',
      ...(replyToId ? { reply_to: { id: replyToId, body: '', user: { display_name: '' } } } : {}),
    }

    setMessages((prev) => [...prev, optimistic])
    setReplyTo(undefined)

    try {
      const formData = new FormData()
      appendVideoFields(formData, file, meta)
      if (caption) formData.append('body', caption)
      if (replyToId) formData.append('reply_to_id', replyToId)
      // Videos are big — allow far longer than the client's default 15s.
      const res = await client.post(`/rooms/${room.slug}/messages`, formData, { timeout: 600000 })
      const message: Message = res.data.message
      setMessages((prev) => {
        const alreadyBroadcast = prev.some((m) => m.id === message.id)
        if (alreadyBroadcast) {
          return prev.filter((m) => m.id !== tempId)
        }
        return prev.map((m) => m.id === tempId ? { ...message, sendStatus: 'sent' } : m)
      })
      if (localThumb) URL.revokeObjectURL(localThumb)
      pendingUploads.current.delete(tempId)
      if (message.attachments[0]) putBlob(message.attachments[0], file)
      const finalThumb = message.metadata?.video?.thumbnail
      if (finalThumb && meta?.thumbnail) putBlob(finalThumb, meta.thumbnail)
      return true
    } catch {
      setMessages((prev) => prev.map((m) => m.id === tempId ? { ...m, sendStatus: 'failed' } : m))
      return false
    }
  }, [room.slug, currentUserId, props.user?.display_name])

  const handleReply = useCallback((msg: Message) => {
    setEditingMessage(undefined)
    setReplyTo(msg)
  }, [])

  const handleCancelReply = useCallback(() => {
    setReplyTo(undefined)
  }, [])

  const handleEmojiSelect = useCallback((unicode: string) => {
    setEmojiInsert({ unicode, id: Date.now() })
  }, [])

  const handleDelete = useCallback((messageId: string) => {
    apiFetch(`/api/rooms/${room.slug}/messages/${messageId}`, { method: 'DELETE' })
      .then((r) => { if (!r.ok) throw new Error('delete failed') })
      .then(() => {
        setMessages((prev) =>
          prev.map((m) => (m.id === messageId ? { ...m, is_deleted: true } : m)),
        )
      })
      .catch(() => {})
  }, [room.slug])

  // Editing happens in the bottom MessageInput (edit mode), not inline in the bubble.
  const [editingMessage, setEditingMessage] = useState<Message | undefined>()

  const handleStartEdit = useCallback((msg: Message) => {
    setReplyTo(undefined)
    setEditingMessage(msg)
  }, [])

  const handleCancelEdit = useCallback(() => {
    setEditingMessage(undefined)
  }, [])

  const handleSubmitEdit = useCallback(async (
    messageId: string,
    newBody: string,
    attachment?: EditAttachment,
  ): Promise<boolean> => {
    try {
      let edited: Message
      if (attachment) {
        // Multipart can't be sent with PUT in PHP, so spoof it via _method.
        const formData = new FormData()
        formData.append('_method', 'PUT')
        formData.append('body', newBody)
        if (attachment.kind === 'video') appendVideoFields(formData, attachment.file, attachment.meta)
        else formData.append(attachment.kind, attachment.file)
        const res = await client.post(`/rooms/${room.slug}/messages/${messageId}`, formData, {
          timeout: attachment.kind === 'video' ? 600000 : 120000,
        })
        edited = res.data.message
        if (edited.attachments[0]) putBlob(edited.attachments[0], attachment.file)
        if (attachment.kind === 'video' && attachment.meta?.thumbnail) {
          const thumbUrl = edited.metadata?.video?.thumbnail
          if (thumbUrl) putBlob(thumbUrl, attachment.meta.thumbnail)
        }
      } else {
        const r = await apiFetch(`/api/rooms/${room.slug}/messages/${messageId}`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ body: newBody }),
        })
        if (!r.ok) throw new Error('edit failed')
        const res: { message: Message } = await r.json()
        edited = res.message
      }
      // The MessageEdited broadcast also echoes back to the sender, but it only
      // maps over existing messages (idempotent), so applying it here too is safe.
      setMessages((prev) =>
        prev.map((m) => (m.id === messageId
          ? { ...m, body: edited.body, type: edited.type, attachments: edited.attachments, metadata: edited.metadata, is_edited: true }
          : m)),
      )
      setEditingMessage(undefined)
      return true
    } catch {
      return false
    }
  }, [room.slug])

  const handleRetry = useCallback(async (failedMsg: Message) => {
    // Failed image/file upload: re-send the original File instead of a text-only body.
    const pending = pendingUploads.current.get(failedMsg.id)
    if (pending) {
      pendingUploads.current.delete(failedMsg.id)
      setMessages((prev) => prev.filter((m) => m.id !== failedMsg.id))
      const caption = failedMsg.body ?? ''
      const replyToId = failedMsg.reply_to?.id
      if (pending.kind === 'video') await handleSendVideo(pending.file, caption, replyToId, pending.meta)
      else if (pending.kind === 'file') await handleSendFile(pending.file, caption, replyToId)
      else await handleSendImage(pending.file, caption, replyToId)
      return
    }

    const tempId = `temp-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`
    const retry: Message = {
      ...failedMsg,
      id: tempId,
      sendStatus: 'sending',
      created_at: new Date().toISOString(),
    }

    setMessages((prev) => prev.map((m) => m.id === failedMsg.id ? retry : m))

    try {
      const payload: Record<string, unknown> = { body: failedMsg.body }
      if (failedMsg.reply_to?.id) payload.reply_to_id = failedMsg.reply_to.id
      const r = await apiFetch(`/api/rooms/${room.slug}/messages`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      })
      if (!r.ok) throw new Error('send failed')
      const res: { message: Message } = await r.json()
      setMessages((prev) => prev.map((m) => m.id === tempId ? { ...res.message, sendStatus: 'sent' } : m))
    } catch {
      setMessages((prev) => prev.map((m) => m.id === tempId ? { ...m, sendStatus: 'failed' } : m))
    }
  }, [room.slug, handleSendFile, handleSendImage, handleSendVideo])

  const handleReact = useCallback((messageId: string, emoteId: string) => {
    const userId = currentUserId

    // Optimistic update — mirror backend ReactionService::toggle() logic
    setMessages((prev) =>
      prev.map((m) => {
        if (m.id !== messageId) return m
        const reactions = [...m.reactions]
        const existingIdx = reactions.findIndex((r) => r.userIds.includes(userId))
        const sameEmoteIdx = reactions.findIndex((r) => r.emoteId === emoteId)

        if (existingIdx >= 0 && reactions[existingIdx].emoteId === emoteId) {
          // Case 1: User already reacted with same emote → toggle OFF
          const updated = { ...reactions[existingIdx], count: reactions[existingIdx].count - 1, userIds: reactions[existingIdx].userIds.filter((id) => id !== userId) }
          if (updated.count <= 0) reactions.splice(existingIdx, 1)
          else reactions[existingIdx] = updated
        } else if (existingIdx >= 0) {
          // Case 2: User has different emote → switch (remove old, join target)
          const oldEmoteId = reactions[existingIdx].emoteId
          const updated = { ...reactions[existingIdx], count: reactions[existingIdx].count - 1, userIds: reactions[existingIdx].userIds.filter((id) => id !== userId) }
          if (updated.count <= 0) reactions.splice(existingIdx, 1)
          else reactions[existingIdx] = updated

          if (sameEmoteIdx >= 0) {
            // Target emote group exists → join it
            reactions[sameEmoteIdx] = { ...reactions[sameEmoteIdx], count: reactions[sameEmoteIdx].count + 1, userIds: [...reactions[sameEmoteIdx].userIds, userId] }
          } else {
            // Target emote group doesn't exist → create new
            const emote = props.emotes.find((e) => e.id === emoteId)
            reactions.push({ emoteId, emoteCode: emote?.code ?? '', imageUrl: emote?.image_url ?? null, unicode: emote?.unicode ?? null, count: 1, userIds: [userId] })
          }
          setReactionAnimations((prev) => ({
            ...prev,
            [`${messageId}:${oldEmoteId}`]: 'bump',
            [`${messageId}:${emoteId}`]: 'pop',
          }))
        } else {
          // Case 3: No existing reaction → join target group or create new
          if (sameEmoteIdx >= 0) {
            reactions[sameEmoteIdx] = { ...reactions[sameEmoteIdx], count: reactions[sameEmoteIdx].count + 1, userIds: [...reactions[sameEmoteIdx].userIds, userId] }
          } else {
            const emote = props.emotes.find((e) => e.id === emoteId)
            reactions.push({ emoteId, emoteCode: emote?.code ?? '', imageUrl: emote?.image_url ?? null, unicode: emote?.unicode ?? null, count: 1, userIds: [userId] })
          }
          setReactionAnimations((prev) => ({ ...prev, [`${messageId}:${emoteId}`]: 'pop' }))
        }
        return { ...m, reactions }
      }),
    )

    // Fire-and-forget API call; revert on failure
    apiFetch(`/api/rooms/${room.slug}/messages/${messageId}/reactions`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ emote_id: emoteId }),
    }).catch(() => {
      // Revert optimistic update by re-fetching messages
      apiFetch(`/api/rooms/${room.slug}/messages?take=50`)
        .then((r) => { if (r.ok) return r.json() })
        .then((res) => { if (res?.data) setMessages(res.data.reverse()) })
        .catch(() => {})
    })
  }, [room.slug, currentUserId, props.emotes])

  const clearReactionAnimation = useCallback((msgId: string, emoteId: string) => {
    setReactionAnimations(prev => {
      const next = { ...prev }
      delete next[`${msgId}:${emoteId}`]
      return next
    })
  }, [])

  const handlePin = useCallback((messageId: string) => {
    // Optimistic update — add to pinned immediately
    setMessages((prev) => {
      const msg = prev.find((m) => m.id === messageId)
      if (msg) {
        setPinnedMessages((prev) => [...prev, {
          id: msg.id,
          body: msg.body,
          created_at: msg.created_at,
          user: msg.user,
        }])
      }
      return prev
    })
    apiFetch(`/api/rooms/${room.slug}/pin`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message_id: messageId }),
    }).catch(() => {
      // Revert on failure — re-fetch from server
      apiFetch(`/api/rooms/${room.slug}/messages?take=50`)
        .then((r) => { if (r.ok) return r.json() })
        .then((res) => { if (res?.data) setMessages(res.data.reverse()) })
        .catch(() => {})
    })
  }, [room.slug])

  const handleUnpin = useCallback((messageId: string) => {
    // Optimistic update — remove from pinned immediately
    setPinnedMessages((prev) => prev.filter((p) => p.id !== messageId))
    apiFetch(`/api/rooms/${room.slug}/pin`, {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message_id: messageId }),
    }).catch(() => {
      // Revert on failure — re-fetch from server
      apiFetch(`/api/rooms/${room.slug}/messages?take=50`)
        .then((r) => { if (r.ok) return r.json() })
        .then((res) => { if (res?.data) setMessages(res.data.reverse()) })
        .catch(() => {})
    })
  }, [room.slug])

  const pinnedMessageIds = useMemo(
    () => new Set(pinnedMessages.map((p) => p.id)),
    [pinnedMessages],
  )

  const handleTogglePin = useCallback((messageId: string) => {
    if (pinnedMessageIds.has(messageId)) {
      handleUnpin(messageId)
    } else {
      handlePin(messageId)
    }
  }, [pinnedMessageIds, handlePin, handleUnpin])

  // Helpful / Best Answer. Tidak optimistik: aturan (penanya/moderator,
  // batas harian, dll.) ada di server, jadi UI menunggu hasilnya lalu
  // menimpa `marks` pesan dengan state dari server.
  const handleMark = useCallback(async (messageId: string, kind: 'helpful' | 'best_answer') => {
    try {
      const path = kind === 'helpful' ? 'helpful' : 'best-answer'
      const res = await client.post(`/rooms/${room.slug}/messages/${messageId}/${path}`)
      const marks = res.data?.marks
      if (marks) {
        setMessages((prev) => prev.map((m) => m.id === messageId ? { ...m, marks } : m))
      }
    } catch (err) {
      // api/client.ts sudah meratakan error menjadi Error(message) (untuk 422
      // berisi pesan validasi pertama), jadi baca err.message — bukan err.response.
      const reason = err instanceof Error && err.message ? err.message : 'Could not update this mark.'
      showPopup({ type: 'warning', title: 'Not allowed', message: reason })
    }
  }, [room.slug, showPopup])

  // Report pesan. Popup menutup dirinya 200ms setelah konfirmasi (lihat
  // Popup.handleClose) — hasil request ditampilkan setelah jeda itu, kalau
  // tidak popup hasil ikut tertutup oleh close yang tertunda.
  const handleReport = useCallback((messageId: string) => {
    const selected = { reason: 'spam' }
    const showLater = (config: Parameters<typeof showPopup>[0]) => setTimeout(() => showPopup(config), 250)

    showPopup({
      type: 'confirm',
      title: 'Report message',
      confirmText: 'Report',
      children: <ReportReasonPicker onChange={(reason) => { selected.reason = reason }} />,
      onConfirm: async () => {
        try {
          await client.post(`/rooms/${room.slug}/messages/${messageId}/report`, { reason: selected.reason })
          showLater({ type: 'notification', title: 'Report sent', message: 'Thanks. A moderator will review it.' })
        } catch (err) {
          const reason = err instanceof Error && err.message ? err.message : 'Could not send this report.'
          showLater({ type: 'warning', title: 'Could not report', message: reason })
        }
      },
    })
  }, [room.slug, showPopup])

  const currentMemberRole = useMemo(
    () => members.find((m) => m.userId === currentUserId)?.role ?? null,
    [members, currentUserId],
  )

  useEffect(() => {
    apiFetch(`/api/rooms/${room.slug}/messages?take=50`)
      .then((r) => { if (!r.ok) throw new Error('fetch messages failed'); return r.json() })
      .then((res: { data: Message[] }) => setMessages(res.data.reverse()))
      .catch(() => {})
      .finally(() => setLoading(false))
  }, [room.slug])

  useEffect(() => {
    refreshMembers()
  }, [refreshMembers])

  useEffect(() => {
    if (!room.id || !window.Echo) return
    const channel = window.Echo.join(`room.${room.id}`)


    channel.listen('.App\\Modules\\Discuss\\Events\\MessageSent', (e: { message: Message }) => {
      // Guard terhadap duplikasi: pengirim pesan sendiri sudah menambahkan
      // optimistic message + menggantinya dengan response HTTP (lihat
      // handleSend di atas). Tanpa cek ini, broadcast echo balik ke
      // pengirim sendiri (dia ikut subscribe channel room-nya sendiri)
      // akan menambahkan pesan yang sama untuk kedua kalinya.
      setMessages((prev) =>
        prev.some((m) => m.id === e.message.id) ? prev : [...prev, e.message],
      )
    })

    channel.listen('.App\\Modules\\Discuss\\Events\\MessageDeleted', (e: { message_id: string }) => {
      setMessages((prev) =>
        prev.map((m) => (m.id === e.message_id ? { ...m, is_deleted: true } : m)),
      )
    })

    channel.listen('.App\\Modules\\Discuss\\Events\\MessageEdited', (e: { message_id: string; body: string; type?: Message['type']; attachments?: string[]; metadata?: Message['metadata'] }) => {
      setMessages((prev) =>
        prev.map((m) => (m.id === e.message_id
          ? {
              ...m,
              body: e.body,
              is_edited: true,
              // Edits can now add/replace an attachment, so keep those in sync too.
              ...(e.type ? { type: e.type } : {}),
              ...(e.attachments ? { attachments: e.attachments } : {}),
              ...(e.metadata !== undefined ? { metadata: e.metadata } : {}),
            }
          : m)),
      )
    })

    channel.listen('.App\\Modules\\Discuss\\Events\\ReactionToggled', (e: { message_id: string; user_id: string; emote_id: string; action: string }) => {
      // Skip events from self — optimistic update already handled it
        //
      if (e.user_id === currentUserId) return

      const emote = props.emotes.find((em) => em.id === e.emote_id)
      setMessages((prev) =>
        prev.map((m) => {
          if (m.id !== e.message_id) return m
          const reactions = [...m.reactions]
          const idx = reactions.findIndex((r) => r.emoteId === e.emote_id)
          if (e.action === 'added') {
            const animKey = `${e.message_id}:${e.emote_id}`
            if (idx >= 0) {
              reactions[idx] = { ...reactions[idx], count: reactions[idx].count + 1, userIds: [...reactions[idx].userIds, e.user_id] }

              setReactionAnimations(prev => ({ ...prev, [animKey]: 'bump' }))
            } else {
              reactions.push({
                emoteId: e.emote_id,
                emoteCode: emote?.code ?? '',
                imageUrl: emote?.image_url ?? null,
                unicode: emote?.unicode ?? null,
                count: 1,
                userIds: [e.user_id],
              })
              setReactionAnimations(prev => ({ ...prev, [animKey]: 'pop' }))
            }
          } else {
            if (idx >= 0) {
              const updated = { ...reactions[idx], count: reactions[idx].count - 1, userIds: reactions[idx].userIds.filter((id) => id !== e.user_id) }
              if (updated.count <= 0) reactions.splice(idx, 1)
              else reactions[idx] = updated
            }
          }
          return { ...m, reactions }
        }),
      )
    })

    channel.listen('.App\\Modules\\Discuss\\Events\\MessageMarked', (e: { message_id: string; marks: NonNullable<Message['marks']> }) => {
      // Payload membawa state lengkap — cukup timpa, aman kalau datang dobel.
      setMessages((prev) => prev.map((m) => m.id === e.message_id ? { ...m, marks: e.marks } : m))
    })

    channel.listen('.App\\Modules\\Discuss\\Events\\PinnedMessageUpdated', (e: { room_id: string; message_ids: string[] }) => {
      // Re-fetch pinned messages from server for accuracy
      apiFetch(`/api/rooms/${room.slug}/messages?take=50`)
        .then((r) => { if (r.ok) return r.json() })
        .then((res) => {
          if (res?.data) {
            setMessages(res.data.reverse())
            // Build pinned list from messages
            const pinned = e.message_ids
              .map((id) => res.data.find((m: Message) => m.id === id))
              .filter(Boolean)
              .map((m: Message) => ({ id: m.id, body: m.body, created_at: m.created_at, user: m.user }))
            setPinnedMessages(pinned)
          }
        })
        .catch(() => {})
    })

    channel.listen('.App\\Modules\\Discuss\\Events\\MemberJoined', (e: { user_id: string; display_name: string }) => {
      setMessages((prev) => [
        ...prev,
        {
          id: `system-join-${e.user_id}-${Date.now()}`,
          body: `${e.display_name} joined the room`,
          type: 'system',
          user: { id: e.user_id, display_name: e.display_name, avatar_url: null },
          reply_count: 0,
          attachments: [],
          reactions: [],
          is_edited: false,
          is_deleted: false,
          created_at: new Date().toISOString(),
        },
      ])
      refreshMembers()
    })

    channel.listen('.App\\Modules\\Discuss\\Events\\MemberLeft', (e: { user_id: string; display_name: string }) => {
      setMessages((prev) => [
        ...prev,
        {
          id: `system-left-${e.user_id}-${Date.now()}`,
          body: `${e.display_name} left the room`,
          type: 'system',
          user: { id: e.user_id, display_name: e.display_name, avatar_url: null },
          reply_count: 0,
          attachments: [],
          reactions: [],
          is_edited: false,
          is_deleted: false,
          created_at: new Date().toISOString(),
        },
      ])
      setMembers((prev) => prev.filter((m) => m.userId !== e.user_id))
    })

    channel.listen('.App\\Modules\\Discuss\\Events\\MemberBanned', (e: { user_id: string; display_name: string }) => {
      setMessages((prev) => [
        ...prev,
        {
          id: `system-banned-${e.user_id}-${Date.now()}`,
          body: `${e.display_name} was banned from the room`,
          type: 'system',
          user: { id: e.user_id, display_name: e.display_name, avatar_url: null },
          reply_count: 0,
          attachments: [],
          reactions: [],
          is_edited: false,
          is_deleted: false,
          created_at: new Date().toISOString(),
        },
      ])
      setMembers((prev) => prev.filter((m) => m.userId !== e.user_id))

      // Kalau diri sendiri yang dibanned, keluar dari room segera —
      // otorisasi channel juga akan ditolak server di percobaan
      // subscribe berikutnya (Broadcast::channel di RoomChannel.php
      // sudah mengecek is_banned), jadi redirect ini murni untuk UX
      // instan tanpa nunggu error itu.
      if (e.user_id === currentUserId) {
        window.location.href = '/discuss'
      }
    })

    channel.here((users: { id: string; display_name: string }[]) => {
      setMembers((prev) => prev.map((m) => ({ ...m, isOnline: users.some((u) => u.id === m.userId) })))
    })

    channel.joining((user: { id: string }) => {
      setMembers((prev) => prev.map((m) => (m.userId === user.id ? { ...m, isOnline: true } : m)))
    })

    channel.leaving((user: { id: string }) => {
      setMembers((prev) => prev.map((m) => (m.userId === user.id ? { ...m, isOnline: false } : m)))
    })

    return () => {
      window.Echo?.leave(`room.${room.id}`)
    }
  }, [room.id, props.emotes, currentUserId, refreshMembers])

  const onlineCount = members.filter((m) => m.isOnline).length
  const recipientIsOnline = directRecipient
    ? members.some((m) => m.userId === directRecipient.id && m.isOnline)
    : false

  const headerTitle = isDirectChat && directRecipient ? directRecipient.display_name : room.name
  const headerAvatar = isDirectChat ? directRecipient?.avatar_url ?? null : room.cover_url
  const headerSubtitle = isDirectChat
    ? (recipientIsOnline ? 'Online' : 'Offline')
    : `${members.length} members, ${onlineCount} online`

  return (
    <div className="mx-auto flex min-h-screen max-w-md flex-col bg-surface shadow-2xl">
      {/* Top AppBar */}
      <header className="fixed top-0 z-50 flex w-full max-w-md items-center justify-between border-b-0 border-outline-variant bg-surface px-gutter-md h-14">
        <div className="flex items-center gap-3">
          <button
            onClick={() => router.visit(isDirectChat ? '/direct' : '/discuss')}
            className="active:opacity-70 transition-opacity p-1 -ml-1"
          >
            <ArrowLeft className="text-primary" />
          </button>
          <div
            className={`flex items-center gap-3 bg-surface-container-low rounded-[90px] overflow-hidden py-1 px-2 ${!isDirectChat ? 'cursor-pointer' : ''}`}
            onClick={() => {
              if (!isDirectChat) router.visit(`/discuss/${room.slug}/about`)
            }}
          >
            <div className="flex h-10 w-10 items-center justify-center overflow-hidden rounded-[99px] bg-surface-container-highest">
              {headerAvatar ? (
                <img src={headerAvatar} alt="" className="h-full w-full object-cover" />
              ) : (
                isDirectChat ? <User className="text-on-surface-variant" /> : <Users className="text-on-surface-variant" />
              )}
            </div>
            <div className="flex flex-col w-[180px]">
              <h1 className="font-headline-sm-mobile truncate text-headline-sm-mobile text-primary leading-tight">{headerTitle}</h1>
              <span className="font-label-sm text-label-sm text-on-surface-variant">
                {headerSubtitle}
              </span>
            </div>
          </div>
        </div>
      </header>

      {/* Pinned Messages — hidden for direct chats and when no pinned messages */}
      {!isDirectChat && pinnedMessages.length > 0 && (
        <div
          className="fixed top-14 left-0 right-0 z-40 mx-auto flex max-w-md items-center gap-3 border-b border-outline-variant bg-surface-container-low px-gutter-md py-3"
          onClick={() => pinnedMessages.length > 1 && setShowPinnedModal(true)}
          role={pinnedMessages.length > 1 ? 'button' : undefined}
        >
          <Megaphone className="h-3.5 w-3.5 text-secondary" />
          <div className="min-w-0 flex-1 overflow-hidden">
            <p className="mb-0.5 font-label-sm text-label-sm uppercase tracking-wider text-secondary">
              Pinned{pinnedMessages.length > 1 ? ` (${pinnedMessages.length})` : ''}
              {pinnedMessages[0].user ? ` — ${pinnedMessages[0].user.display_name}` : ''}
            </p>
            <p className="truncate font-body-sm text-body-sm text-on-surface">
              {pinnedMessages[0].body}
            </p>
          </div>
          <button
            onClick={(e) => { e.stopPropagation(); handleUnpin(pinnedMessages[0].id) }}
            className="transition-colors hover:text-error"
            title="Unpin"
          >

            <Pin className="rotate-[30deg] h-[17px] w-[17px] text-on-surface-variant"  />
          </button>
        </div>
      )}

      {/* Pinned Messages Modal — full screen */}
      {showPinnedModal && (
        <div className="fixed inset-0 z-[100] flex flex-col bg-surface">
          {/* Header */}
          <div className="flex items-center justify-between border-b border-outline-variant px-gutter-md py-3">
            <h3 className="font-title-md text-title-md text-on-surface">Pinned Messages ({pinnedMessages.length})</h3>
            <button onClick={() => setShowPinnedModal(false)} className="transition-colors hover:text-on-surface">
              <X className="text-on-surface-variant" />
            </button>
          </div>

          {/* List — render full MessageItem for each pinned message */}
          <div className="flex-1 overflow-y-auto px-gutter-md py-4">
            <div className="mx-auto max-w-md flex flex-col">
              {pinnedMessages.map((pm) => {
                const fullMsg = messages.find((m) => m.id === pm.id)
                if (!fullMsg) {
                  return (
                    <div key={pm.id} className="flex items-start gap-3 py-3 border-b border-outline-variant last:border-0">
                      <div className="min-w-0 flex-1">
                        <p className="font-label-sm text-label-sm text-on-surface-variant">{pm.user?.display_name ?? 'Unknown'}</p>
                        <p className="font-body-sm text-body-sm text-on-surface mt-0.5">{pm.body}</p>
                      </div>
                    </div>
                  )
                }
                return (
                  <div key={pm.id}>
                    <MessageItem
                      message={fullMsg}
                      currentUserId={currentUserId}
                      emotes={props.emotes}
                      onReply={() => {}}
                      onDelete={() => {}}
                      onReact={() => {}}
                      isPinned={true}
                      userRole={currentMemberRole}
                      reactionAnimations={reactionAnimations}
                      onReactionAnimationEnd={clearReactionAnimation}
                    />
                  </div>
                )
              })}
            </div>
          </div>
        </div>
      )}

      {/* Content Canvas */}
      <main className={`flex-1 ${isDirectChat ? 'pt-[56px]' : pinnedMessages.length > 0 ? 'pt-[112px]' : 'pt-[56px]'} px-gutter-md flex flex-col gap-stack-md overflow-y-auto overflow-x-hidden scrollbar-hide ${
        showEmoji ? 'pb-[420px]' : 'pb-[84px]'
      }`}>
        <MessageList
          messages={messages}
          currentUserId={currentUserId}
          emotes={props.emotes}
          onReply={handleReply}
          onDelete={handleDelete}
          onStartEdit={handleStartEdit}
          onReact={handleReact}
          onPin={handleTogglePin}
          onMark={handleMark}
          onReport={handleReport}
          onRetry={handleRetry}
          pinnedMessageIds={pinnedMessageIds}
          userRole={currentMemberRole}
          reactionAnimations={reactionAnimations}
          onReactionAnimationEnd={clearReactionAnimation}
          loading={loading}
        />
      </main>

      {/* Bottom drawer: input + emoji picker, a mute notice, or a Join Room prompt */}
      <div className="fixed bottom-0 left-0 right-0 z-50 mx-auto max-w-md">
        {!isMember ? (
          <nav className="flex h-[72px] flex-col items-center justify-center gap-1 border-t border-outline-variant bg-surface-container-low px-gutter-md">
            <button
              onClick={handleJoin}
              disabled={joining}
              className="w-full rounded-full bg-primary py-2.5 font-label-lg text-label-lg text-on-primary transition-opacity active:opacity-80 disabled:opacity-50"
            >
              {joining ? 'Joining...' : 'Join Room'}
            </button>
          </nav>
        ) : isMuted && mutedUntilMs !== null ? (
          <nav className="flex h-[72px] flex-col items-center justify-center gap-0.5 border-t border-outline-variant bg-surface-container-low px-gutter-md">
            <div className="flex items-center gap-1.5 text-error">
              <MicOff className="h-3.5 w-3.5" />
              <span className="font-label-md text-label-md">You are muted</span>
            </div>
            <span className="font-body-sm text-body-sm text-on-surface-variant tabular-nums">
              You can chat again in {formatCountdown(mutedUntilMs - now)}
            </span>
          </nav>
        ) : (
          <>
            {/* Message input */}
            <nav className="flex min-h-[72px] items-center gap-stack-sm  bg-transparent px-gutter-md">
              <MessageInput
                onSend={handleSend}
                onSendImage={handleSendImage}
                onSendFile={handleSendFile}
                onSendVideo={handleSendVideo}
                editing={editingMessage}
                onCancelEdit={handleCancelEdit}
                onSubmitEdit={handleSubmitEdit}
                replyTo={replyTo}
                onCancelReply={handleCancelReply}
                showEmoji={showEmoji}
                toggleEmoji={() => setShowEmoji((p) => !p)}
                emojiInsert={emojiInsert}
              />
            </nav>

            {/* Emoji picker - below input when open */}
            {showEmoji && (
              <div className="border-t border-outline-variant bg-surface-container">
                <EmojiPicker onSelect={handleEmojiSelect} onClose={() => setShowEmoji(false)} />
              </div>
            )}
          </>
        )}
      </div>

      {/* Popup layer — Room.tsx doesn't use <Layout>, so it must render its own,
          same as Layout.tsx's PopupLayer does for pages that use Layout. */}
      {popup && <Popup config={popup} onClose={closePopup} />}
      <ModalHost />
    </div>
  )
}
