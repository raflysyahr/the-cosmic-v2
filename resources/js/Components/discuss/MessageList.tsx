import { useLayoutEffect, useRef, type FC } from 'react'
import MessageItem from './MessageItem'
import type { Message } from './MessageItem'

interface MessageListProps {
  messages: Message[]
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
  pinnedMessageIds?: Set<string>
  userRole?: 'member' | 'moderator' | 'admin' | null
  reactionAnimations: Record<string, 'pop' | 'bump'>
  onReactionAnimationEnd: (msgId: string, emoteId: string) => void
  loading: boolean
}

function formatDate(iso: string): string {
  const d = new Date(iso)
  const today = new Date()
  const yesterday = new Date(today)
  yesterday.setDate(yesterday.getDate() - 1)

  if (d.toDateString() === today.toDateString()) return 'Today'
  if (d.toDateString() === yesterday.toDateString()) return 'Yesterday'
  return d.toLocaleDateString('en-US', { day: 'numeric', month: 'long', year: 'numeric' })
}

function getDateKey(iso: string): string {
  return iso.slice(0, 10)
}

const Skeleton: FC = () => (
  <div className="flex gap-2 py-2">
    <div className="h-8 w-8 animate-pulse rounded-full bg-surface-container-high" />
    <div className="flex-1 space-y-1">
      <div className="h-4 w-24 animate-pulse rounded bg-surface-container-high" />
      <div className="h-3 w-full animate-pulse rounded bg-surface-container-high" />
    </div>
  </div>
)

const MessageList: FC<MessageListProps> = ({ messages, currentUserId, emotes, onReply, onDelete, onStartEdit, onReact, onPin, onMark, onReport, onRetry, pinnedMessageIds, userRole, reactionAnimations, onReactionAnimationEnd, loading }) => {
  const bottomRef = useRef<HTMLDivElement>(null)

  // Buka room: lompat ke pesan terbaru sebelum browser menggambar (tanpa animasi scroll dari
  // atas). Pesan baru setelahnya baru di-scroll smooth.
  const initialScrollDone = useRef(false)
  useLayoutEffect(() => {
    if (loading) return
    bottomRef.current?.scrollIntoView({ behavior: initialScrollDone.current ? 'smooth' : 'auto' })
    if (messages.length > 0) initialScrollDone.current = true
  }, [messages.length, loading])

  if (loading) {
    return (
      <div className="flex flex-col gap-1 py-4">
        {Array.from({ length: 5 }).map((_, i) => (
          <Skeleton key={i} />
        ))}
      </div>
    )
  }

  if (messages.length === 0) {
    return (
      <div className="flex flex-1 items-center justify-center">
        <p className="text-sm text-on-surface-variant">No messages yet. Start the conversation!</p>
      </div>
    )
  }

  let lastDateKey: string | null = null
  let prevUserId: string | null = null
  let prevTimestamp: number | null = null

  return (
    <div className="flex flex-col gap-stack-sm py-4">
      {messages.map((msg) => {
        const dateKey = getDateKey(msg.created_at)
        const showDate = dateKey !== lastDateKey
        lastDateKey = dateKey

        const sameUser = msg.user?.id && msg.user.id === prevUserId
        const timeDiff = prevTimestamp ? new Date(msg.created_at).getTime() - prevTimestamp : Infinity
        const isGrouped = !!sameUser && timeDiff < 5 * 60 * 1000
        const showAvatar = !isGrouped

        prevUserId = msg.user?.id ?? null
        prevTimestamp = new Date(msg.created_at).getTime()

        return (
          <div key={msg.id} id={`msg-${msg.id}`}>
            {showDate && (
              <div className="flex items-center justify-center gap-4 my-4">
                <div className="h-[1px] flex-1 bg-outline-variant opacity-30" />
                <span className="font-label-sm text-label-sm text-on-surface-variant shrink-0">
                  {formatDate(msg.created_at)}
                </span>
                <div className="h-[1px] flex-1 bg-outline-variant opacity-30" />
              </div>
            )}
            <MessageItem
              message={msg}
              currentUserId={currentUserId}
              emotes={emotes}
              onReply={onReply}
              onDelete={onDelete}
              onStartEdit={onStartEdit}
              onReact={onReact}
              onPin={onPin}
              onMark={onMark}
              onReport={onReport}
              onRetry={onRetry}
              isPinned={pinnedMessageIds?.has(msg.id)}
              userRole={userRole}
              reactionAnimations={reactionAnimations}
              onReactionAnimationEnd={onReactionAnimationEnd}
              showAvatar={showAvatar}
            />
          </div>
        )
      })}
      <div ref={bottomRef} />
    </div>
  )
}

export default MessageList
