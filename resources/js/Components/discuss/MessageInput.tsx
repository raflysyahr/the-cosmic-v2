import { useState, useRef, useEffect, type FC, type KeyboardEvent, type ChangeEvent } from 'react'
import { X, Paperclip, Smile, Send, Loader2, Reply, Image as ImageIcon, FileText, Edit3, Check, Video as VideoIcon, Play } from 'lucide-react'
import EmojiPicker from './EmojiPicker'
import VideoPlayer from './VideoPlayer'
import { formatFileSize } from '../../lib/downloadedFiles'
import { readVideoMeta, getReplyThumb, MAX_VIDEO_BYTES, VIDEO_ACCEPT, type VideoMeta, type VideoInfo } from '../../lib/media'

interface ReplyTo {
  id: string
  body: string
  user: { display_name: string; avatar_url?: string | null }
  // Present when the replied-to message is a full Message (used for the thumbnail).
  type?: string
  attachments?: string[]
  metadata?: { file?: { name?: string }; video?: VideoInfo } | null
}

// Message currently being edited (edit mode reuses this input instead of an inline editor).
interface EditingMessage {
  id: string
  body: string | null
  attachments: string[]
}

export type EditAttachment = { kind: 'image' | 'file' | 'video'; file: File; meta?: VideoMeta }

interface MessageInputProps {
  onSend: (body: string, replyToId?: string) => Promise<boolean>
  onSendImage?: (file: File, caption: string, replyToId?: string) => Promise<boolean>
  onSendFile?: (file: File, caption: string, replyToId?: string) => Promise<boolean>
  onSendVideo?: (file: File, caption: string, replyToId?: string, meta?: VideoMeta) => Promise<boolean>
  replyTo?: ReplyTo
  onCancelReply: () => void
  editing?: EditingMessage
  onCancelEdit?: () => void
  onSubmitEdit?: (id: string, body: string, attachment?: EditAttachment) => Promise<boolean>
  disabled?: boolean
  disabledReason?: string
  showEmoji?: boolean
  toggleEmoji?: () => void
  emojiInsert?: { unicode: string; id: number } | null
}



// Must stay in sync with the backend rules in SendMessageRequest ('file').
const MAX_FILE_BYTES = 20 * 1024 * 1024
const FILE_ACCEPT = '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.rtf,.odt,.ods,.odp,.zip,.rar,.7z,.mp3,.wav,.ogg,.mp4,.mov,.webm'

const MessageInput: FC<MessageInputProps> = ({ onSend, onSendImage, onSendFile, onSendVideo, replyTo, onCancelReply, editing, onCancelEdit, onSubmitEdit, disabled, disabledReason, showEmoji, toggleEmoji, emojiInsert }) => {
  const [body, setBody] = useState('')
  const inputRef = useRef<HTMLInputElement>(null)
  const prevInsertId = useRef(0)
  const fileInputRef = useRef<HTMLInputElement>(null)

  // Fullscreen image preview (pick → preview + caption → send)
  const [mediaFile, setMediaFile] = useState<File | null>(null)
  const [mediaPreviewUrl, setMediaPreviewUrl] = useState<string | null>(null)
  const [mediaKind, setMediaKind] = useState<'image' | 'video'>('image')
  const [videoMeta, setVideoMeta] = useState<VideoMeta | null>(null)
  const [preparingVideo, setPreparingVideo] = useState(false)
  const videoInputRef = useRef<HTMLInputElement>(null)
  const [caption, setCaption] = useState('')
  const [sendingMedia, setSendingMedia] = useState(false)
  const [showCaptionEmoji, setShowCaptionEmoji] = useState(false)
  const captionInputRef = useRef<HTMLInputElement>(null)

  // Attach menu (image / file) + pending document (shown as a card above the input)
  const [menuOpen, setMenuOpen] = useState(false)
  const [pendingFile, setPendingFile] = useState<File | null>(null)
  const [sendingFile, setSendingFile] = useState(false)
  const docInputRef = useRef<HTMLInputElement>(null)
  const menuRef = useRef<HTMLDivElement>(null)
  const attachBtnRef = useRef<HTMLButtonElement>(null)
  const addAttachRef = useRef<HTMLButtonElement>(null)
  const fileTooLarge = !!pendingFile && pendingFile.size > MAX_FILE_BYTES
  const videoTooLarge = mediaKind === 'video' && !!mediaFile && mediaFile.size > MAX_VIDEO_BYTES

  // ── Edit mode ──
  const hasExistingAttachment = (editing?.attachments.length ?? 0) > 0
  const editChanged = !!editing && (!!pendingFile || body.trim() !== (editing.body ?? '').trim())
  const canSubmitEdit = !!editing && editChanged && !fileTooLarge && (!!body.trim() || hasExistingAttachment || !!pendingFile)
  const showReply = !!replyTo && !editing
  const replyThumb = getReplyThumb(replyTo)
  const draftRef = useRef('')
  const prevEditingId = useRef<string | undefined>(undefined)
  const editingId = editing?.id

  // Entering edit mode prefills the input with the message text (and stashes the
  // draft that was being typed); leaving it restores that draft.
  useEffect(() => {
    const prev = prevEditingId.current
    if (editingId && editingId !== prev) {
      if (!prev) draftRef.current = body
      setBody(editing?.body ?? '')
      setPendingFile(null)
      setMenuOpen(false)
      requestAnimationFrame(() => inputRef.current?.focus())
    } else if (!editingId && prev) {
      setBody(draftRef.current)
      draftRef.current = ''
      setPendingFile(null)
    }
    prevEditingId.current = editingId
  }, [editingId])

  // Close the attach menu on outside click / Escape
  useEffect(() => {
    if (!menuOpen) return
    const onDown = (e: MouseEvent | TouchEvent) => {
      const t = e.target as Node
      if (menuRef.current?.contains(t) || attachBtnRef.current?.contains(t) || addAttachRef.current?.contains(t)) return
      setMenuOpen(false)
    }
    const onKey = (e: globalThis.KeyboardEvent) => { if (e.key === 'Escape') setMenuOpen(false) }
    document.addEventListener('mousedown', onDown)
    document.addEventListener('touchstart', onDown)
    document.addEventListener('keydown', onKey)
    return () => {
      document.removeEventListener('mousedown', onDown)
      document.removeEventListener('touchstart', onDown)
      document.removeEventListener('keydown', onKey)
    }
  }, [menuOpen])

  // Revoke the object URL once it's no longer shown, so we don't leak memory.
  useEffect(() => {
    return () => {
      if (mediaPreviewUrl) URL.revokeObjectURL(mediaPreviewUrl)
    }
  }, [mediaPreviewUrl])

  const handlePickImage = () => {
    setMenuOpen(false)
    fileInputRef.current?.click()
  }

  const handlePickVideo = () => {
    setMenuOpen(false)
    videoInputRef.current?.click()
  }

  const handlePickFile = () => {
    setMenuOpen(false)
    docInputRef.current?.click()
  }

  const handleDocChange = (e: ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    e.target.value = ''
    if (!file) return
    setPendingFile(file)
    requestAnimationFrame(() => inputRef.current?.focus())
  }

  const handleFileChange = (e: ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    e.target.value = '' // allow picking the same file again later
    if (!file) return
    setMediaKind('image')
    setVideoMeta(null)
    setMediaFile(file)
    setMediaPreviewUrl(URL.createObjectURL(file))
    // In edit mode the text being edited becomes the caption of the new photo.
    setCaption(editing ? body : '')
    setShowCaptionEmoji(false)
    requestAnimationFrame(() => captionInputRef.current?.focus())
  }

  const handleVideoChange = (e: ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    e.target.value = ''
    if (!file) return
    setMediaKind('video')
    setVideoMeta(null)
    setMediaFile(file)
    setMediaPreviewUrl(URL.createObjectURL(file))
    setCaption(editing ? body : '')
    setShowCaptionEmoji(false)
    requestAnimationFrame(() => captionInputRef.current?.focus())
    // Read duration + poster frame in the background; sending waits for it.
    if (file.size <= MAX_VIDEO_BYTES) {
      setPreparingVideo(true)
      readVideoMeta(file).then((meta) => {
        setVideoMeta(meta)
        setPreparingVideo(false)
      })
    }
  }

  const closeMediaPreview = () => {
    if (sendingMedia) return
    if (mediaPreviewUrl) URL.revokeObjectURL(mediaPreviewUrl)
    setMediaFile(null)
    setMediaPreviewUrl(null)
    setVideoMeta(null)
    setPreparingVideo(false)
    setCaption('')
    setShowCaptionEmoji(false)
  }

  const handleCaptionEmojiSelect = (unicode: string) => {
    const input = captionInputRef.current
    const start = input?.selectionStart ?? caption.length
    const end = input?.selectionEnd ?? start
    setCaption((prev) => prev.slice(0, start) + unicode + prev.slice(end))
    requestAnimationFrame(() => {
      input?.focus()
      input?.setSelectionRange(start + unicode.length, start + unicode.length)
    })
  }

  const handleSendMedia = () => {
    if (!mediaFile || sendingMedia || videoTooLarge || preparingVideo) return
    const text = caption.trim()
    const meta = videoMeta ?? undefined
    let send: (() => Promise<boolean>) | undefined
    if (editing) {
      send = onSubmitEdit && (() => onSubmitEdit(editing.id, text, { kind: mediaKind, file: mediaFile, meta }))
    } else if (mediaKind === 'video') {
      send = onSendVideo && (() => onSendVideo(mediaFile, text, replyTo?.id, meta))
    } else {
      send = onSendImage && (() => onSendImage(mediaFile, text, replyTo?.id))
    }
    if (!send) return
    setSendingMedia(true)
    send().then((ok) => {
      setSendingMedia(false)
      if (ok) {
        if (mediaPreviewUrl) URL.revokeObjectURL(mediaPreviewUrl)
        setMediaFile(null)
        setMediaPreviewUrl(null)
        setVideoMeta(null)
        setCaption('')
        setShowCaptionEmoji(false)
        if (replyTo) onCancelReply()
      }
    })
  }

  const handleCaptionKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter') {
      e.preventDefault()
      handleSendMedia()
    }
  }

  useEffect(() => {
    //inputRef.current?.focus()
  }, [])

  // Handle emoji insert from parent
  useEffect(() => {
    if (emojiInsert && emojiInsert.id !== prevInsertId.current) {
      prevInsertId.current = emojiInsert.id
      const input = inputRef.current
      const start = input?.selectionStart ?? body.length
      const end = input?.selectionEnd ?? start
      setBody((prev) => prev.slice(0, start) + emojiInsert.unicode + prev.slice(end))
      requestAnimationFrame(() => {
        input?.focus()
        input?.setSelectionRange(start + emojiInsert.unicode.length, start + emojiInsert.unicode.length)
      })
    }
  }, [emojiInsert])

  const handleSend = () => {
    const text = body.trim()
    if (disabled) return

    // Edit mode: save the edited text, optionally with a new photo/file attached.
    if (editing) {
      if (!onSubmitEdit || sendingFile || !canSubmitEdit) return
      setSendingFile(true)
      onSubmitEdit(editing.id, text, pendingFile ? { kind: 'file', file: pendingFile } : undefined).then((ok) => {
        setSendingFile(false)
        if (ok) setPendingFile(null)
      })
      return
    }

    // A document is attached: send it, with the typed text as its caption.
    if (pendingFile) {
      if (!onSendFile || sendingFile || fileTooLarge) return
      setSendingFile(true)
      onSendFile(pendingFile, text, replyTo?.id).then((ok) => {
        setSendingFile(false)
        if (ok) {
          setPendingFile(null)
          setBody('')
          if (replyTo) onCancelReply()
        }
      })
      return
    }

    if (!text) return
    onSend(text, replyTo?.id).then((ok) => {
      if (ok) {
        setBody('')
        if (replyTo) onCancelReply()
      }
      //inputRef.current?.focus()
    })
  }

  const handleKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Escape' && editing) {
      e.preventDefault()
      onCancelEdit?.()
      return
    }
    if (e.key === 'Enter') {
      e.preventDefault()
      handleSend()
    }
  }

  return (
    <>
      {/* Hidden file input for image picking */}
      <input
        ref={fileInputRef}
        type="file"
        accept="image/*"
        className="hidden"
        onChange={handleFileChange}
      />
      {/* Hidden file input for videos */}
      <input
        ref={videoInputRef}
        type="file"
        accept={VIDEO_ACCEPT}
        className="hidden"
        onChange={handleVideoChange}
      />
      {/* Hidden file input for documents */}
      <input
        ref={docInputRef}
        type="file"
        accept={FILE_ACCEPT}
        className="hidden"
        onChange={handleDocChange}
      />

      {/* Fullscreen image preview + caption, before actually sending */}
      {mediaPreviewUrl && (
        <div className="fixed inset-0 z-50 flex flex-col bg-black">
          <button
            onClick={closeMediaPreview}
            disabled={sendingMedia}
            className="absolute left-4 top-4 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-black/50 text-white transition-colors hover:bg-black/70 disabled:opacity-50"
          >
            <X className="h-5 w-5" />
          </button>

          {mediaKind === 'video' ? (
            <div className="flex-1 overflow-hidden">
              {/* Same custom player as the chat viewer; controls sit above the caption pill. */}
              <VideoPlayer
                src={mediaPreviewUrl}
                poster={null}
                durationHint={videoMeta?.duration}
                bottomOffset={76}
              />
            </div>
          ) : (
            <div className="flex flex-1 items-center justify-center overflow-hidden p-4">
              <img
                src={mediaPreviewUrl}
                alt="Preview"
                className="max-h-full max-w-full object-contain"
              />
            </div>
          )}

          {/* Caption input — same UI as the message input, floated absolute over the image */}
          <div className="absolute inset-x-3 bottom-4">
            {videoTooLarge && (
              <p className="mb-2 rounded-xl bg-black/60 px-3 py-2 text-center font-body-sm text-body-sm text-error">
                Video too large (max {formatFileSize(MAX_VIDEO_BYTES)})
              </p>
            )}
            {/* Emoji picker for the caption — anchored just above the pill */}
            {showCaptionEmoji && (
              <div className="mb-2 overflow-hidden rounded-2xl bg-surface-container shadow-lg">
                <EmojiPicker onSelect={handleCaptionEmojiSelect} onClose={() => setShowCaptionEmoji(false)} />
              </div>
            )}

            <div className="flex items-center h-[50px] bg-surface-container-highest px-2 rounded-[50px]">
              <div className="flex flex-1 items-center rounded-full px-4 py-2">
                <input
                  ref={captionInputRef}
                  type="text"
                  value={caption}
                  onChange={(e) => setCaption(e.target.value)}
                  onKeyDown={handleCaptionKeyDown}
                  placeholder="Add a caption..."
                  disabled={sendingMedia}
                  className="w-full border-none bg-transparent p-0 font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant focus:ring-0 h-[40px] disabled:opacity-50"
                />
              </div>

              {/* Emoji picker trigger — same as the message input's */}
              <button
                onClick={() => setShowCaptionEmoji((p) => !p)}
                disabled={sendingMedia}
                className={`mr-2 flex items-center justify-center p-2 transition-colors rounded-full hover:bg-surface-container-higher active:scale-95 disabled:opacity-50 ${
                  showCaptionEmoji ? 'bg-primary-container text-on-primary-container' : 'text-on-surface-variant'
                }`}
              >
                <Smile className="h-5 w-5" />
              </button>

              <button
                onClick={handleSendMedia}
                disabled={sendingMedia || videoTooLarge || preparingVideo}
                className="flex items-center justify-center rounded-full bg-primary p-2 transition-transform active:scale-95 disabled:opacity-50"
              >
                {sendingMedia || preparingVideo ? (
                  <Loader2 className="h-3.5 w-3.5 animate-spin text-on-primary" />
                ) : (
                  <Send className="h-3.5 w-3.5 text-on-primary" fill="currentColor" />
                )}
              </button>
            </div>
          </div>
        </div>
      )}






      {disabled ? (
        <p className="font-body-sm text-body-sm text-on-surface-variant">{disabledReason ?? 'You cannot send messages right now.'}</p>

      ) : (
        <div className="relative w-full">
          {/* Attach menu — opens above the input */}
          {menuOpen && (
            <div
              ref={menuRef}
              className="absolute bottom-full left-0 z-10 mb-2 w-48 overflow-hidden rounded-2xl bg-surface-container-highest py-1 shadow-lg"
            >
              <button
                onClick={handlePickImage}
                className="flex w-full items-center gap-3 px-4 py-2.5 text-left font-body-md text-body-md text-on-surface transition-colors hover:bg-surface-container-higher"
              >
                <ImageIcon className="h-4 w-4 text-primary" />
                Upload gambar
              </button>
              <button
                onClick={handlePickVideo}
                className="flex w-full items-center gap-3 px-4 py-2.5 text-left font-body-md text-body-md text-on-surface transition-colors hover:bg-surface-container-higher"
              >
                <VideoIcon className="h-4 w-4 text-primary" />
                Upload video
              </button>
              <button
                onClick={handlePickFile}
                className="flex w-full items-center gap-3 px-4 py-2.5 text-left font-body-md text-body-md text-on-surface transition-colors hover:bg-surface-container-higher"
              >
                <FileText className="h-4 w-4 text-primary" />
                Upload file
              </button>
            </div>
          )}

          {/* Edit mode: "Add attachment" on top — photo works like image upload, file like file upload */}
          {editing && (
            <>
              <button
                ref={addAttachRef}
                type="button"
                onClick={() => setMenuOpen((p) => !p)}
                className="flex w-full items-center gap-2 rounded-t-3xl border-b border-white/5 bg-surface-container-highest px-4 py-2.5 text-left font-label-md text-label-md text-primary transition-colors hover:bg-surface-container-higher"
              >
                <Paperclip className="h-4 w-4 shrink-0" />
                {hasExistingAttachment ? 'Replace attachment' : 'Add attachment'}
              </button>

              {/* Edit preview — same look as the reply preview */}
              <div className="flex items-start gap-2 bg-surface-container-highest px-4 pb-2.5 pt-3">
                <Edit3 className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                <div className="min-w-0 flex-1">
                  <p className="truncate font-label-md text-label-md text-primary">Edit message</p>
                  <p className="truncate font-body-sm text-body-sm text-on-surface-variant">
                    {editing.body || (hasExistingAttachment ? 'Attachment' : '')}
                  </p>
                </div>
                <button
                  onClick={onCancelEdit}
                  disabled={sendingFile}
                  className="shrink-0 text-on-surface-variant transition-colors hover:text-on-surface disabled:opacity-50"
                >
                  <X className="h-4 w-4" />
                </button>
              </div>
            </>
          )}

          {/* Reply preview — sits on top of the input pill and grows the whole
              thing upward (the input row itself never moves), like Telegram */}
          {showReply && replyTo && (
            <div className={`flex ${replyThumb ? 'items-center' : 'items-start'} gap-2 rounded-t-3xl bg-surface-container-highest px-4 pb-2.5 pt-3`}>
              <Reply className={`${replyThumb ? '' : 'mt-0.5'} h-4 w-4 shrink-0 text-primary`} />
              {/* Photo / video replies: 40×40 thumbnail (a video's poster frame), no caption */}
              {replyThumb && (
                <div className="relative h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-black/30">
                  {replyThumb.url && <img src={replyThumb.url} alt="" className="h-full w-full object-cover" draggable={false} />}
                  {replyThumb.isVideo && (
                    <span className="absolute inset-0 flex items-center justify-center">
                      <Play className="h-4 w-4 text-white/90" fill="currentColor" />
                    </span>
                  )}
                </div>
              )}
              <div className="min-w-0 flex-1">
                <p className="truncate font-label-md text-label-md text-primary">
                  Reply to {replyTo.user.display_name}
                </p>
                {!replyThumb && (
                  <p className="truncate font-body-sm text-body-sm text-on-surface-variant">
                    {replyTo.body || replyTo.metadata?.file?.name}
                  </p>
                )}
              </div>
              <button onClick={onCancelReply} className="shrink-0 text-on-surface-variant transition-colors hover:text-on-surface">
                <X className="h-4 w-4" />
              </button>
            </div>
          )}

          {/* File preview — same look as the reply preview, stacked right above the input */}
          {pendingFile && (
            <div className={`flex items-start gap-2 bg-surface-container-highest px-4 pb-2.5 pt-3 ${showReply || editing ? '' : 'rounded-t-3xl'}`}>
              <FileText className="mt-0.5 h-4 w-4 shrink-0 text-primary" />
              <div className="min-w-0 flex-1">
                <p className="truncate font-label-md text-label-md text-primary">{pendingFile.name}</p>
                <p className={`truncate font-body-sm text-body-sm ${fileTooLarge ? 'text-error' : 'text-on-surface-variant'}`}>
                  {fileTooLarge
                    ? `File too large (max ${formatFileSize(MAX_FILE_BYTES)})`
                    : formatFileSize(pendingFile.size)}
                </p>
              </div>
              <button
                onClick={() => setPendingFile(null)}
                disabled={sendingFile}
                className="shrink-0 text-on-surface-variant transition-colors hover:text-on-surface disabled:opacity-50"
              >
                <X className="h-4 w-4" />
              </button>
            </div>
          )}

          <div
            className={`relative flex h-[50px] w-full items-center bg-surface-container-highest px-2 ${
              showReply || editing || pendingFile ? 'rounded-b-3xl rounded-t-none' : 'rounded-[50px]'
            }`}
          >
            {/* Attach button */}
            <button
              ref={attachBtnRef}
              onClick={() => setMenuOpen((p) => !p)}
              className={`flex items-center justify-center p-2 transition-colors rounded-full hover:bg-surface-container-higher active:scale-95 ${
                menuOpen ? 'bg-primary-container text-on-primary-container' : 'text-on-surface-variant'
              }`}
            >
              <Paperclip className="h-4 w-4" />
            </button>

            {/* Input field */}
            <div className="flex flex-1 items-center rounded-full  px-4 py-2">
              <input
                ref={inputRef}
                type="text"
                value={body}
                onChange={(e) => setBody(e.target.value)}
                onKeyDown={handleKeyDown}
                placeholder={editing ? 'Edit message...' : pendingFile ? 'Add a caption...' : 'Type a message...'}
                className="w-full border-none bg-transparent p-0 font-body-md text-body-md text-on-surface placeholder:text-on-surface-variant focus:ring-0 h-[40px]"
                onFocus={(e) => {
                  //(e.target.parentElement as HTMLElement)?.classList.add('ring-1', 'ring-outline')
                }}
                onBlur={(e) => {
                  (e.target.parentElement as HTMLElement)?.classList.remove('ring-1', 'ring-outline')
                }}
              />
            </div>

            {/* Emoji picker trigger */}
            <button
              onClick={toggleEmoji}
              className={`mr-2 flex items-center justify-center p-2 transition-colors rounded-full hover:bg-surface-container-higher active:scale-95 ${
                showEmoji ? 'bg-primary-container text-on-primary-container' : 'text-on-surface-variant'
              }`}
            >
              <Smile className="h-5 w-5" />
            </button>

            {/* Send button */}
            <button
              onClick={handleSend}
              disabled={editing ? sendingFile || !canSubmitEdit : pendingFile ? sendingFile || fileTooLarge : !body.trim()}
              className="flex items-center justify-center rounded-full bg-primary p-2 transition-transform active:scale-95 disabled:opacity-50"
            >
              {sendingFile ? (
                <Loader2 className="h-3.5 w-3.5 animate-spin text-on-primary" />
              ) : editing ? (
                <Check className="h-3.5 w-3.5 text-on-primary" strokeWidth={3} />
              ) : (
                <Send className="h-3.5 w-3.5 text-on-primary" fill="currentColor" />
              )}
            </button>
          </div>
        </div>
      )}
    </>
  )
}

export default MessageInput
