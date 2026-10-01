import { useState, useRef, useCallback, useEffect, type FC, type PointerEvent, type MouseEvent } from 'react'
import ReactionBar from './ReactionBar'
import MarkBadges, { type MessageMarks } from './MarkBadges'
import MessageActionMenu from './MessageActionMenu'
import EmojiText from '../../lib/emoji-renderer'
import type { GroupedReaction } from './ReactionBar'
import { ShieldBan, CheckCheck, UserPlus, LogOut, Loader2, AlertTriangle, X, Undo2, Reply, Download, FileText, Play, MoreVertical, Save } from 'lucide-react'
import Crest from '../cultivation/Crest'
import { usePopup } from '../../contexts/PopupContext'
import VideoPlayer from './VideoPlayer'
import { formatDuration, getReplyThumb, type VideoInfo } from '../../lib/media'
import { formatFileSize } from '../../lib/downloadedFiles'
import { isCached, getCachedBlobUrl, warmCache, saveToDevice } from '../../lib/mediaCache'

interface MessageUser {
  id: string
  display_name: string
  avatar_url: string | null
  rank?: { name: string; color: string }
  realm?: { realm_name: string; realm_slug: string; stage: number; level: number; description:string } | null
}

interface ReplyTo {
  id: string
  body: string
  type?: string
  /** Poster/preview for photo & video replies (resolved by the server). */
  thumbnail?: string | null
  file_name?: string | null
  user: { id?: string; display_name: string; avatar_url?: string | null }
}

interface Message {
  id: string
  body: string | null
  type: 'text' | 'image' | 'file' | 'video' | 'sticker' | 'system'
  user: MessageUser
  reply_to?: ReplyTo
  reply_count: number
  attachments: string[]
  metadata?: { file?: { name?: string; size?: number; mime?: string }; video?: VideoInfo; thumbnail?: string | null } | null
  reactions: GroupedReaction[]
  /** Helpful / Best Answer. Opsional: pesan optimistik belum punya. */
  marks?: MessageMarks
  is_edited: boolean
  is_deleted: boolean
  created_at: string
  sendStatus?: 'sending' | 'sent' | 'failed'
}

interface MessageItemProps {
  message: Message
  currentUserId: string
  emotes: { id: string; code: string; image_url: string | null; unicode: string | null }[]
  onReply: (msg: Message) => void
  onDelete: (id: string) => void
  onStartEdit?: (msg: Message) => void
  onReact: (msgId: string, emoteId: string) => void
  onPin?: (msgId: string) => void
  onMark?: (msgId: string, kind: 'helpful' | 'best_answer') => void
  onReport?: (msgId: string) => void
  onRetry?: (msg: Message) => void
  isPinned?: boolean
  userRole?: 'member' | 'moderator' | 'admin' | null
  reactionAnimations: Record<string, 'pop' | 'bump'>
  onReactionAnimationEnd: (msgId: string, emoteId: string) => void
  showAvatar?: boolean
}

const LONG_PRESS_MS = 400
// Swipe-to-reply (ala Telegram/WhatsApp): geser bubble ke kiri buat balas pesan.
const SWIPE_REPLY_THRESHOLD = 60 // px — jarak geser minimal buat trigger reply saat dilepas
const SWIPE_MAX_DRAG = 72 // px — batas maksimal geser visual biar bubble tidak "terbang"
const SWIPE_ACTIVATION_DEADZONE = 10 // px — gerakan minimal sebelum dianggap swipe (bukan tap/scroll list)

function formatTime(iso: string): string {
  const d = new Date(iso)
  return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
}

const MessageItem: FC<MessageItemProps> = ({ message, currentUserId, emotes, onReply, onDelete, onStartEdit, onReact, onPin, onMark, onReport, onRetry, isPinned = false, userRole, reactionAnimations, onReactionAnimationEnd, showAvatar = true }) => {
  const [menu, setMenu] = useState<{ x: number; y: number; align: 'left' | 'right' } | null>(null)
  const [dragX, setDragX] = useState(0)
  const [lightboxUrl, setLightboxUrl] = useState<string | null>(null)
  const [lightboxCaptionHidden, setLightboxCaptionHidden] = useState(false)
  const [captionH, setCaptionH] = useState(0)
  const longPressTimer = useRef<ReturnType<typeof setTimeout> | null>(null)
  const dragStart = useRef<{ x: number; y: number } | null>(null)
  const isSwipingRef = useRef(false)
  const bubbleRef = useRef<HTMLDivElement>(null)
  const isOwner = message.user?.id === currentUserId
  const { showPopup } = usePopup()
  // "Downloaded" == present in the local media cache. The sender's own upload
  // is cached right after sending (see Room.tsx), so assume true immediately
  // for a smooth UI, then correct it below if that turns out to be wrong.
  const [fileDownloaded, setFileDownloaded] = useState(isOwner)
  const [fileDownloading, setFileDownloading] = useState(false)
  const canPin = userRole === 'admin' || userRole === 'moderator'
  // Helpful/Best Answer: hanya kebijakan tampilan menu — server tetap
  // memvalidasi ulang (MarkService). Pesan yang masih dikirim/gagal/sistem
  // tidak bisa ditandai.
  const isSettled = (!message.sendStatus || message.sendStatus === 'sent') && message.type !== 'system'
  const questionAuthorId = message.reply_to?.user?.id
  const canHelpful = !!onMark && isSettled && !isOwner
  const canBestAnswer = !!onMark && isSettled && !isOwner
    && !!questionAuthorId
    && message.user?.id !== questionAuthorId
    && (currentUserId === questionAuthorId || canPin)
  const canReport = !!onReport && isSettled && !isOwner
  const isHelpful = !!message.marks?.helpful_user_ids.includes(currentUserId)
  const isBestAnswer = !!message.marks?.is_best_answer
  // Lightbox media (image or video): resolved from cache when possible, and
  // whether Save (three-dot menu) is currently actionable.
  const [lightboxSrc, setLightboxSrc] = useState<string | null>(null)
  const [saveReady, setSaveReady] = useState(false)
  const [saveMenuOpen, setSaveMenuOpen] = useState(false)
  const lightboxBlobUrl = useRef<string | null>(null)

  const captionRef = useCallback((el: HTMLDivElement | null) => { if (el) setCaptionH(el.offsetHeight) }, [])

  const openLightbox = (url: string, video: boolean) => {
    setLightboxCaptionHidden(false)
    setLightboxUrl(url)
    setLightboxSrc(null)
    setSaveReady(false)
    setSaveMenuOpen(false)

    if (video) {
      // Native <video> streams the URL directly (progressive, seekable) so
      // playback isn't blocked on a full download. If we already have the
      // whole thing cached from a previous view, though, play from that
      // instead — instant start, zero network.
      isCached(url).then((yes) => {
        if (!yes) {
          warmCache(url).then(() => setSaveReady(true))
          return
        }
        getCachedBlobUrl(url).then((blobUrl) => {
          lightboxBlobUrl.current = blobUrl
          setLightboxSrc(blobUrl)
          setSaveReady(true)
        })
      })
    } else {
      // Images: always resolve through the cache so a repeat view never
      // re-fetches from the server (bubble already shows a small thumbnail
      // as an instant placeholder while this resolves).
      getCachedBlobUrl(url).then((blobUrl) => {
        lightboxBlobUrl.current = blobUrl
        setLightboxSrc(blobUrl)
        setSaveReady(true)
      })
    }
  }

  const closeLightbox = () => {
    setLightboxUrl(null)
    setLightboxSrc(null)
    setLightboxCaptionHidden(false)
    setSaveMenuOpen(false)
    if (lightboxBlobUrl.current) {
      URL.revokeObjectURL(lightboxBlobUrl.current)
      lightboxBlobUrl.current = null
    }
  }

  const handleSaveMedia = async () => {
    if (!lightboxUrl || !saveReady) return
    const name = isVideo
      ? (video?.name || `video-${message.id}.mp4`)
      : `image-${message.id}.jpg`
    await saveToDevice(lightboxUrl, name)
    setSaveMenuOpen(false)
  }

  // Editing happens in the main message input at the bottom of the room
  // (see MessageInput edit mode), not inline in the bubble.
  const startEdit = () => onStartEdit?.(message)

  // Popup menu ala Telegram: long-press (touch/mouse) ATAU right-click
  // (desktop) memunculkan menu di posisi sentuhan. Posisi horizontal
  // menu (align) mengikuti sisi bubble — pesan sendiri rata kanan jadi
  // menu dibuka ke kiri dari titik tap, punya orang lain sebaliknya —
  // supaya menu tidak terpotong di luar layar pada HP sempit.
  const openMenuAt = useCallback((clientX: number, clientY: number) => {
    setMenu({ x: clientX, y: clientY, align: isOwner ? 'right' : 'left' })
  }, [isOwner])

  const handlePointerDown = (e: PointerEvent) => {
    const x = e.clientX
    const y = e.clientY
    dragStart.current = { x, y }
    isSwipingRef.current = false
    longPressTimer.current = setTimeout(() => openMenuAt(x, y), LONG_PRESS_MS)
  }

  const cancelLongPress = () => {
    if (longPressTimer.current) {
      clearTimeout(longPressTimer.current)
      longPressTimer.current = null
    }
  }

  const resetSwipe = () => {
    isSwipingRef.current = false
    dragStart.current = null
    setDragX(0)
  }

  // Selama pointer bergerak: tentukan dulu apakah ini gestur swipe
  // horizontal (buat reply) atau scroll vertikal biasa pada list chat —
  // begitu salah satu jelas terlihat, kunci ke mode itu supaya keduanya
  // tidak "rebutan" gestur yang sama.
  const handlePointerMove = (e: PointerEvent) => {
    if (!dragStart.current) return
    const dx = e.clientX - dragStart.current.x
    const dy = e.clientY - dragStart.current.y

    if (!isSwipingRef.current) {
      if (Math.abs(dx) > SWIPE_ACTIVATION_DEADZONE && Math.abs(dx) > Math.abs(dy)) {
        isSwipingRef.current = true
        cancelLongPress()
      } else if (Math.abs(dy) > SWIPE_ACTIVATION_DEADZONE) {
        // Gerakan vertikal — ini scroll list, bukan swipe. Batalkan long-press
        // juga supaya menu tidak nyelonong muncul saat user lagi scroll.
        dragStart.current = null
        cancelLongPress()
        return
      } else {
        return
      }
    }

    // Swipe reply cuma ke kiri (dx negatif); clamp biar bubble tidak
    // "terbang" lebih jauh dari SWIPE_MAX_DRAG walau jari digeser lebih jauh.
    setDragX(Math.max(-SWIPE_MAX_DRAG, Math.min(0, dx)))
  }

  const handlePointerUp = () => {
    cancelLongPress()
    if (isSwipingRef.current && dragX <= -SWIPE_REPLY_THRESHOLD) {
      onReply(message)
    }
    resetSwipe()
  }

  // Dipakai saat pointer keluar dari bubble atau di-cancel browser (mis.
  // scroll native ambil alih) — batalkan tanpa trigger reply.
  const handlePointerCancel = () => {
    cancelLongPress()
    resetSwipe()
  }

  const handleContextMenu = (e: MouseEvent) => {
    e.preventDefault()
    openMenuAt(e.clientX, e.clientY)
  }

  if (message.type === 'system') {
    const isJoin = message.body?.toLowerCase().includes('added') || message.body?.toLowerCase().includes('joined')
    return (
      <div className="flex flex-col items-center gap-stack-xs py-2">
        <div className="flex items-center gap-2 rounded-full bg-surface-container px-4 py-1.5">
          {isJoin ? <UserPlus className="h-3.5 w-3.5 text-on-surface-variant" /> : <LogOut className="h-3.5 w-3.5 text-on-surface-variant" />}
          <p className="font-body-sm text-body-sm text-on-surface">{message.body}</p>
        </div>
        <span className="font-label-sm text-label-sm text-on-surface-variant">{formatTime(message.created_at)}</span>
      </div>
    )
  }

  if (message.is_deleted) {
    return (
      <div className="flex gap-2 group py-1">
        <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-surface-container-highest">
          <ShieldBan className="h-3.5 w-3.5 text-on-surface-variant" />
        </div>
        <div className="flex-1 pt-1.5">
          <p className="font-body-sm text-body-sm italic text-on-surface-variant">Message deleted</p>
        </div>
      </div>
    )
  }

  const avatarFallback = message.user.display_name?.charAt(0).toUpperCase() || '?'
  const showHeader = !isOwner && showAvatar
  const bodyText = message.body?.trim() || ''

  // ── File messages ──
  const isFile = message.type === 'file'
  const isVideo = message.type === 'video'
  const imageAttachments = isFile || isVideo ? [] : message.attachments
  const fileUrl = message.attachments[0]
  const fileName = message.metadata?.file?.name || decodeURIComponent(fileUrl?.split('/').pop() ?? 'file')
  const fileSize = formatFileSize(message.metadata?.file?.size)
  const fileReady = !!fileUrl && (!message.sendStatus || message.sendStatus === 'sent')

  // Confirm the cache actually has it (covers a non-owner who downloaded it
  // earlier, and corrects the owner's optimistic guess if it was evicted).
  useEffect(() => {
    if (!isFile || !fileUrl) return
    let cancelled = false
    isCached(fileUrl).then((yes) => { if (!cancelled) setFileDownloaded(yes) })
    return () => { cancelled = true }
  }, [isFile, fileUrl])

  // Cache-first: a file is only ever fetched from the server once. First
  // download caches it; every download after that (including "Save" from the
  // long-press menu) saves straight from the cache, no network at all.
  const doFileDownload = async () => {
    setFileDownloading(true)
    try {
      const alreadyCached = await isCached(fileUrl)
      if (!alreadyCached) {
        const throwaway = await getCachedBlobUrl(fileUrl) // fetches + caches as a side effect
        URL.revokeObjectURL(throwaway)
      }
      await saveToDevice(fileUrl, fileName)
      setFileDownloaded(true)
    } catch {
      showPopup({ type: 'warning', title: 'Download failed', message: `Could not download "${fileName}".` })
    } finally {
      setFileDownloading(false)
    }
  }

  const handleFileClick = () => {
    if (!fileReady || fileDownloading) return
    if (fileDownloaded) {
      doFileDownload()
      return
    }
    showPopup({
      type: 'confirm',
      title: 'Download file',
      message: `Download "${fileName}"${fileSize ? ` (${fileSize})` : ''}?`,
      confirmText: 'Download',
      onConfirm: doFileDownload,
    })
  }

  const fileCard = isFile ? (
    <button
      type="button"
      onClick={handleFileClick}
      disabled={!fileReady}
      className="flex w-[220px] max-w-full items-center gap-3 rounded-xl bg-black/20 p-2 text-left transition-colors hover:bg-black/30 disabled:cursor-default"
    >
      <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/20 text-primary">
        {(!fileReady && message.sendStatus === 'sending') || fileDownloading ? (
          <Loader2 className="h-5 w-5 animate-spin" />
        ) : fileDownloaded ? (
          <FileText className="h-5 w-5" />
        ) : (
          <Download className="h-5 w-5" />
        )}
      </span>
      <span className="min-w-0 flex-1">
        <span className="block truncate font-body-md text-body-md text-on-surface">{fileName}</span>
        {fileSize && <span className="block font-label-sm text-label-sm text-on-surface-variant">{fileSize}</span>}
      </span>
    </button>
  ) : null

  // ── Video messages: poster + duration + size in the chat, custom player on click ──
  const video = message.metadata?.video
  const videoUrl = isVideo ? message.attachments[0] : undefined
  const videoReady = !!videoUrl && (!message.sendStatus || message.sendStatus === 'sent')
  const videoInfoLine = [video?.duration != null ? formatDuration(video.duration) : '', formatFileSize(video?.size)]
    .filter(Boolean)
    .join(' · ')

  const videoCard = isVideo ? (
    <button
      type="button"
      onClick={() => videoUrl && videoReady && openLightbox(videoUrl, true)}
      disabled={!videoReady}
      className="relative block w-[200px] max-w-full overflow-hidden rounded-lg bg-black/40 disabled:cursor-default"
      style={{
        aspectRatio: video?.width && video?.height ? `${video.width} / ${video.height}` : '16 / 9',
        maxHeight: 300,
      }}
    >
      {video?.thumbnail ? (
        <img src={video.thumbnail} alt="" className="h-full w-full object-cover" draggable={false} />
      ) : videoUrl ? (
        // No poster frame (generation failed): let the browser paint the first frame.
        <video src={`${videoUrl}#t=0.1`} preload="metadata" muted playsInline className="pointer-events-none h-full w-full object-cover" />
      ) : null}
      <span className="absolute inset-0 flex items-center justify-center">
        <span className="flex h-11 w-11 items-center justify-center rounded-full bg-black/55 text-white">
          {!videoReady && message.sendStatus === 'sending' ? (
            <Loader2 className="h-5 w-5 animate-spin" />
          ) : (
            <Play className="ml-0.5 h-5 w-5" fill="currentColor" />
          )}
        </span>
      </span>
      {videoInfoLine && (
        <span className="absolute bottom-1 left-1 rounded bg-black/60 px-1.5 py-0.5 font-label-sm text-label-sm text-white">
          {videoInfoLine}
        </span>
      )}
    </button>
  ) : null

  // ── Reply quote inside the bubble: photo/video replies show a 40×40 thumbnail, no caption ──
  const replyThumb = getReplyThumb(message.reply_to)
  // Tap the reply quote inside a bubble → jump to (and briefly flash) the
  // original message it's replying to, ala Telegram/WhatsApp.
  const jumpToMessage = (id: string) => {
    const el = document.getElementById(`msg-${id}`)
    if (!el) return // not loaded in this batch — nothing to scroll to yet
    el.scrollIntoView({ behavior: 'smooth', block: 'center' })
    el.classList.remove('message-flash')
    // Force a reflow so re-adding the class restarts the animation even if
    // the same message was just jumped to a moment ago.
    void el.offsetWidth
    el.classList.add('message-flash')
    setTimeout(() => el.classList.remove('message-flash'), 1200)
  }

  const replyQuote = (cls: string) => message.reply_to && (
    <button
      type="button"
      onClick={(e) => { e.stopPropagation(); jumpToMessage(message.reply_to!.id) }}
      className={`flex w-full items-center gap-2 text-left transition-opacity active:opacity-70 ${cls}`}
    >
      {replyThumb && (
        <div className="relative h-10 w-10 shrink-0 overflow-hidden rounded bg-black/30">
          {replyThumb.url && <img src={replyThumb.url} alt="" loading="lazy" className="h-full w-full object-cover" draggable={false} />}
          {replyThumb.isVideo && (
            <span className="absolute inset-0 flex items-center justify-center">
              <Play className="h-4 w-4 text-white/90" fill="currentColor" />
            </span>
          )}
        </div>
      )}
      <div className="min-w-0 flex-1">
        <p className={`text-[10px] font-medium text-[#aaa] leading-none ${replyThumb ? '' : 'mb-0.5'}`}>{message.reply_to.user.display_name}</p>
        {!replyThumb && (
          <p className="text-[10px] text-[#777] leading-tight truncate">{message.reply_to.body || message.reply_to.file_name}</p>
        )}
      </div>
    </button>
  )

  const isLongBody = bodyText.length > 40
  const sizeClass = isLongBody ? 'w-[80%]' : 'w-[90%]'
  const emojiOnly = !message.reply_to && bodyText.length > 0 && bodyText.length <= 10 && /^(\p{Extended_Pictographic}|\uFE0F|\u200D|\u20E3|\p{Emoji_Modifier_Base}|\p{RI})+$/u.test(bodyText)

  return (
    <div className={`group transition-colors ${showAvatar ? 'py-1' : 'py-0.5'}`}>
      {menu && (
        <MessageActionMenu
          x={menu.x}
          y={menu.y}
          align={menu.align}
          canEdit={isOwner && !!onStartEdit && ['text', 'image', 'file', 'video'].includes(message.type) && (!message.sendStatus || message.sendStatus === 'sent')}
          canPin={canPin}
          isPinned={isPinned}
          canSave={isFile && fileDownloaded}
          canHelpful={canHelpful}
          isHelpful={isHelpful}
          canBestAnswer={canBestAnswer}
          isBestAnswer={isBestAnswer}
          canReport={canReport}
          emotes={emotes}
          onReply={() => onReply(message)}
          onEdit={startEdit}
          onDelete={() => onDelete(message.id)}
          onPin={() => onPin?.(message.id)}
          onSave={() => saveToDevice(fileUrl, fileName)}
          onHelpful={() => onMark?.(message.id, 'helpful')}
          onBestAnswer={() => onMark?.(message.id, 'best_answer')}
          onReport={() => onReport?.(message.id)}
          onReact={(emoteId) => onReact(message.id, emoteId)}
          onClose={() => setMenu(null)}
        />
      )}

      {/* Own message — right aligned, no avatar */}
      {isOwner ? (
        <div className={`flex flex-col items-end gap-stack-xs self-end ${sizeClass} ml-auto`}>
          {emojiOnly ? (
            <div className="relative">
              <div
                className="pointer-events-none absolute right-1 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full bg-surface-container-highest transition-opacity"
                style={{ opacity: Math.min(1, Math.abs(dragX) / SWIPE_REPLY_THRESHOLD) }}
              >
                <Reply className="h-3.5 w-3.5 text-on-surface-variant" />
              </div>
              <div
                ref={bubbleRef}
                onPointerDown={handlePointerDown}
                onPointerMove={handlePointerMove}
                onPointerUp={handlePointerUp}
                onPointerLeave={handlePointerCancel}
                onPointerCancel={handlePointerCancel}
                onContextMenu={handleContextMenu}
                className="select-none"
                style={{
                  transform: `translateX(${dragX}px)`,
                  transition: dragX === 0 ? 'transform 150ms ease-out' : 'none',
                  touchAction: 'pan-y',
                }}
              >
                <EmojiText text={bodyText} size="3rem" className="leading-none" />
              </div>
            </div>
          ) : (
            <div className="relative">
              {/* Indikator swipe-reply — makin kelihatan makin dekat ke threshold */}
              <div
                className="pointer-events-none absolute right-1 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full bg-surface-container-highest transition-opacity"
                style={{ opacity: Math.min(1, Math.abs(dragX) / SWIPE_REPLY_THRESHOLD) }}
              >
                <Reply className="h-3.5 w-3.5 text-on-surface-variant" />
              </div>
              <div
                ref={bubbleRef}
                onPointerDown={handlePointerDown}
                onPointerMove={handlePointerMove}
                onPointerUp={handlePointerUp}
                onPointerLeave={handlePointerCancel}
                onPointerCancel={handlePointerCancel}
                onContextMenu={handleContextMenu}
                className="message-bubble-self px-2 py-2 relative select-none"
                style={{
                  transform: `translateX(${dragX}px)`,
                  transition: dragX === 0 ? 'transform 150ms ease-out' : 'none',
                  touchAction: 'pan-y',
                }}
              >
              {/* Reply preview inside bubble */}
              {replyQuote('mb-2 border-l-2 border-[#555] bg-[#2C2C2C] px-2 py-1')}

              {/* Body */}
              <>
                  {fileCard}
{videoCard}
{/* Image attachments — shown above the caption, like other chat apps */}
                  {imageAttachments.length > 0 && (
                    <div className="relative flex flex-wrap gap-1 max-w-[200px] min-w-fit max-h-[300px] w-fit overflow-hidden">
                      {imageAttachments.map((url, i) => (
                        <button
                          key={i}
                          type="button"
                          onClick={() => openLightbox(url, false)}
                          className="block overflow-hidden rounded-lg"
                          style={{ WebkitTouchCallout: 'none' }}
                        >
                          <img
                            src={message.metadata?.thumbnail || url}
                            alt={`attachment ${i + 1}`}
                            loading="lazy"
                            className=" w-auto h-auto object-cover"
                            draggable={false}
                            style={{ WebkitTouchCallout: 'none', WebkitUserSelect: 'none' }}
                          />
                        </button>
                      ))}
                    </div>
                  )}

                  {/* Caption — always below the image */}
                  {message.body && (
                    <EmojiText text={message.body} className={`font-body-md pr-1 ${imageAttachments.length > 0 || isFile || isVideo ? 'mt-1' : ''}`} />
                  )}
                </>
              </div>
            </div>
          )}

          {/* Footer: edited badge + time + send status + reply count */}
          <div className="flex items-center gap-1 mr-1 ">
            <div className="flex items-center bg-black px-1 rounded-full ml-1">
              {message.reply_count > 0 && (
                <>
                  <span className="text-outline-variant">·</span>
                  <span className="flex items-center gap-[1.5px] font-label-sm text-label-sm text-on-surface-variant">
                    {message.reply_count} <Undo2 className="h-3.5 w-3.5 text-on-surface-variant" />
                  </span>
                </>
              )}
              {/* Reactions */}
              <ReactionBar
                reactions={message.reactions}
                onToggle={(emoteId) => onReact(message.id, emoteId)}
                currentUserId={currentUserId}
                reactionAnimations={reactionAnimations}
                messageId={message.id}
                onAnimationEnd={onReactionAnimationEnd}
              />
              <MarkBadges marks={message.marks} currentUserId={currentUserId} />
            </div>
            {message.is_edited && (
              <span className="font-label-sm text-label-sm text-on-surface-variant italic">(edited)</span>
            )}
            <span className="font-label-sm text-label-sm text-on-surface-variant">{formatTime(message.created_at)}</span>
            {message.sendStatus === 'sending' ? (
              <Loader2 className="h-3.5 w-3.5 text-on-surface-variant animate-spin" />
            ) : message.sendStatus === 'failed' ? (
              <button onClick={() => onRetry?.(message)} className="transition-colors hover:opacity-70" title="Retry sending">
                <AlertTriangle className="h-3.5 w-3.5 text-error" />
              </button>
            ) : (
              <CheckCheck className="h-3.5 w-3.5 text-primary" />
            )}
          </div>
        </div>
      ) : (
        /* Other's message — left aligned, with avatar */
        <div className={`flex gap-3 ${sizeClass}`}>
          {/* Avatar column */}
          {showAvatar ? (
            <div className="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-[50px] ring-[3px] ring-gray-800 ring-offset-2 ring-offset-[black] bg-surface-container-highest">
              {message.user.avatar_url ? (
                <img src={message.user.avatar_url} alt="" className="h-full w-full object-cover" />
              ) : (
                <span className="font-label-sm text-label-sm text-on-surface font-bold ">{avatarFallback}</span>
              )}
            </div>
          ) : (
            <div className="w-8 shrink-0" />
          )}

          <div className="min-w-0 flex-1 flex flex-col gap-stack-xs">
            {/* Header — only for other's first message */}
            {showHeader && (
              <div className="flex items-center gap-2 mb-2">
                <span className="font-label-md text-label-md text-on-surface-variant ml-1">
                  {message.user.display_name}
                </span>



                {message.user.realm && (
                  <div className="flex items-center">
                    <Crest realm={message.user.realm} size={30}/>






                    <span className="flex items-center px-2 text-xs rounded-full bg-gray-800">
                      {message.user.realm.realm_name} {message.user.realm.stage}
                    </span>
                  </div>
                )}
              </div>
            )}

            <div className="relative w-fit gap-2 flex flex-col">
            {emojiOnly ? (
              <div className="relative">
                <div
                  className="pointer-events-none absolute right-1 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full bg-surface-container-highest transition-opacity"
                  style={{ opacity: Math.min(1, Math.abs(dragX) / SWIPE_REPLY_THRESHOLD) }}
                >
                  <Reply className="h-3.5 w-3.5 text-on-surface-variant" />
                </div>
                <div
                  ref={bubbleRef}
                  onPointerDown={handlePointerDown}
                  onPointerMove={handlePointerMove}
                  onPointerUp={handlePointerUp}
                  onPointerLeave={handlePointerCancel}
                  onPointerCancel={handlePointerCancel}
                  onContextMenu={handleContextMenu}
                  className="select-none"
                  style={{
                    transform: `translateX(${dragX}px)`,
                    transition: dragX === 0 ? 'transform 150ms ease-out' : 'none',
                    touchAction: 'pan-y',
                  }}
                >
                  <EmojiText text={bodyText} size="3rem" className="leading-none" />
                </div>
              </div>
            ) : (
              <div className="relative">
                {/* Indikator swipe-reply — makin kelihatan makin dekat ke threshold */}
                <div
                  className="pointer-events-none absolute right-1 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full bg-surface-container-highest transition-opacity"
                  style={{ opacity: Math.min(1, Math.abs(dragX) / SWIPE_REPLY_THRESHOLD) }}
                >
                  <Reply className="h-3.5 w-3.5 text-on-surface-variant" />
                </div>
                <div
                  ref={bubbleRef}
                  onPointerDown={handlePointerDown}
                  onPointerMove={handlePointerMove}
                  onPointerUp={handlePointerUp}
                  onPointerLeave={handlePointerCancel}
                  onPointerCancel={handlePointerCancel}
                  onContextMenu={handleContextMenu}
                  className="message-bubble-other w-fit  px-2 py-2 select-none"
                  style={{
                    transform: `translateX(${dragX}px)`,
                    transition: dragX === 0 ? 'transform 150ms ease-out' : 'none',
                    touchAction: 'pan-y',
                  }}
                >
                {/* Reply preview inside bubble */}
                {replyQuote('mb-1 border-l-2 border-[#666] bg-[#343434] px-2 py-1')}


                {fileCard}
{videoCard}
{/* Image attachments — shown above the caption, like other chat apps */}
                {imageAttachments.length > 0 && (
                  <div className=" relative flex flex-wrap gap-1 max-w-[200px] min-w-fit max-h-[300px] w-fit overflow-hidden ">
                    {imageAttachments.map((url, i) => (
                      <button
                        key={i}
                        type="button"
                        onClick={() => openLightbox(url, false)}
                        className="block overflow-hidden rounded-lg"
                        style={{ WebkitTouchCallout: 'none' }}
                      >
                        <img
                          src={message.metadata?.thumbnail || url}
                          alt={`attachment ${i + 1}`}
                          loading="lazy"
                          className=" w-auto h-auto object-cover"
                          draggable={false}
                          style={{ WebkitTouchCallout: 'none', WebkitUserSelect: 'none' }}
                        />
                      </button>
                    ))}
                  </div>
                )}


                {/* Caption — always below the image */}
                {message.body && (
                  <EmojiText text={message.body} className={`font-body-md text-on-surface ${imageAttachments.length > 0 || isFile || isVideo ? 'mt-1' : ''}`} />
                )}
                </div>
              </div>
            )}


            {/* Footer: edited badge + time + reply count */}
            <div className="flex items-center justify-end gap-1 ml-1">
              <div className="flex items-center bg-black px-1 rounded-full ml-1">
                {message.reply_count > 0 && (
                  <>
                    <span className="text-outline-variant">·</span>
                    <span className="flex items-center gap-[1.5px] font-label-sm text-label-sm text-on-surface-variant">
                      {message.reply_count} <Undo2 className="h-3.5 w-3.5 text-on-surface-variant" />
                    </span>
                  </>
                )}
                {/* Reactions */}
                <ReactionBar
                  reactions={message.reactions}
                  onToggle={(emoteId) => onReact(message.id, emoteId)}
                  currentUserId={currentUserId}
                  reactionAnimations={reactionAnimations}
                  messageId={message.id}
                  onAnimationEnd={onReactionAnimationEnd}
                />
                <MarkBadges marks={message.marks} currentUserId={currentUserId} />
              </div>
              {message.is_edited && (
                <span className="font-label-sm text-label-sm text-on-surface-variant italic">(edited)</span>
              )}
              <span className="font-label-sm text-label-sm text-on-surface-variant">{formatTime(message.created_at)}</span>
            </div>
            </div>
          </div>
        </div>
      )}


      {/* Fullscreen image lightbox — click the image to toggle the caption, X or backdrop to close */}
      {lightboxUrl && (
        <div
          className="fixed inset-0 z-[60] flex flex-col bg-black"
          onClick={closeLightbox}
        >
          <button
            onClick={(e) => { e.stopPropagation(); closeLightbox() }}
            className="absolute left-2 top-2 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-black/20 text-white transition-colors hover:bg-black/70"
          >
            <X className="h-5 w-5" />
          </button>

          {/* Three-dot menu — Save, from whatever's already cached (no re-download) */}
          <div className="absolute right-2 top-2 z-10">
            <button
              onClick={(e) => { e.stopPropagation(); setSaveMenuOpen((p) => !p) }}
              className="flex h-9 w-9 items-center justify-center rounded-full bg-black/20 text-white transition-colors hover:bg-black/70"
            >
              <MoreVertical className="h-5 w-5" />
            </button>
            {saveMenuOpen && (
              <div
                className="absolute right-0 top-11 w-40 overflow-hidden rounded-xl bg-surface-container-highest py-1 shadow-lg"
                onClick={(e) => e.stopPropagation()}
              >
                <button
                  onClick={handleSaveMedia}
                  disabled={!saveReady}
                  className="flex w-full items-center gap-3 px-4 py-2.5 text-left font-body-md text-body-md text-on-surface transition-colors hover:bg-surface-container-higher disabled:opacity-50"
                >
                  {saveReady ? <Save className="h-4 w-4" /> : <Loader2 className="h-4 w-4 animate-spin" />}
                  {saveReady ? 'Save' : 'Preparing…'}
                </button>
              </div>
            )}
          </div>

          <div className={`flex flex-1 items-center justify-center overflow-hidden ${isVideo ? '' : 'p-4'}`}>
            {isVideo ? (
              // Custom controls; tapping the picture toggles controls + caption, like the image viewer.
              // Streams straight from the server (progressive) unless a full copy is
              // already cached from a previous view, in which case that plays instead.
              <div className="h-full w-full" onClick={(e) => e.stopPropagation()}>
                <VideoPlayer
                  src={lightboxSrc ?? lightboxUrl}
                  poster={video?.thumbnail}
                  autoPlay
                  durationHint={video?.duration}
                  chromeHidden={lightboxCaptionHidden}
                  onToggleChrome={() => setLightboxCaptionHidden((prev) => !prev)}
                  bottomOffset={message.body && !lightboxCaptionHidden ? captionH : 0}
                />
              </div>
            ) : (
              <img
                // Instant low-res placeholder (already loaded for the bubble) while the
                // full-resolution copy resolves from cache — or downloads once and is
                // cached, so it's never fetched from the server a second time.
                src={lightboxSrc ?? message.metadata?.thumbnail ?? lightboxUrl}
                alt="Attachment"
                onClick={(e) => { e.stopPropagation(); setLightboxCaptionHidden((prev) => !prev) }}
                className=" cursor-zoom-out object-contain"
                draggable={false}
              />
            )}
          </div>

          {message.body && !lightboxCaptionHidden && (
            <div
              ref={captionRef}
              className="border-t absolute bottom-0 w-full border-white/10 bg-gray-800/50 px-4 py-3"
              onClick={(e) => e.stopPropagation()}
            >
              <EmojiText text={message.body} className="font-body-md text-white" />
            </div>
          )}
        </div>
      )}
    </div>
  )
}

export type { Message, MessageUser, ReplyTo, GroupedReaction }
export default MessageItem
