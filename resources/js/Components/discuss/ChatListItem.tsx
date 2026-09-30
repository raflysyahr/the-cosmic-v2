import { type FC } from 'react'
import { Link } from '@inertiajs/react'
import EmojiText from '../../lib/emoji-renderer'
import { User } from 'lucide-react'

interface LastMessageUser {
  id: string
  display_name: string
}

interface LastMessage {
  body: string | null
  created_at: string
  user: LastMessageUser | null
}

interface OtherUser {
  id: string
  display_name: string
  avatar_url: string | null
}

interface DirectChatData {
  id: string
  slug: string
  context_type: string | null
  other_user: OtherUser | null
  last_message: LastMessage | null
  created_at: string
}

interface ChatListItemProps {
  room: DirectChatData
}

function formatTimeAgo(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime()
  const mins = Math.floor(diff / 60000)
  if (mins < 1) return 'now'
  if (mins < 60) return `${mins}m`
  const hours = Math.floor(mins / 60)
  if (hours < 24) return `${hours}h`
  const days = Math.floor(hours / 24)
  if (days < 7) return `${days}d`
  return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

const ChatListItem: FC<ChatListItemProps> = ({ room }) => {
  const name = room.other_user?.display_name ?? 'Unknown user'
  const avatar = room.other_user?.avatar_url

  return (
    <Link
      href={`/discuss/${room.slug}`}
      className="flex items-start gap-3 px-gutter-md py-3 transition-colors hover:bg-surface-container"
    >
      {/* Avatar */}
      <div className="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-surface-container-highest">
        {avatar ? (
          <img src={avatar} alt="" className="h-full w-full object-cover" />
        ) : (
          <User className="h-3.5 w-3.5 text-on-surface-variant" />
        )}
      </div>

      {/* Right side */}
      <div className="min-w-0 flex-1">
        {/* Row 1: name + time */}
        <div className="flex items-baseline justify-between gap-2">
          <h3 className="truncate font-label-md text-label-md text-on-surface">{name}</h3>
          {room.last_message && (
            <span className="shrink-0 font-label-sm text-label-sm text-on-surface-variant">
              {formatTimeAgo(room.last_message.created_at)}
            </span>
          )}
        </div>

        {/* Row 2: last message */}
        {room.last_message ? (
          <p className="mt-0.5 truncate font-body-sm text-body-sm text-on-surface-variant">
            <EmojiText text={room.last_message.body || ''} />
          </p>
        ) : (
          <p className="mt-0.5 truncate font-body-sm text-body-sm text-on-surface-variant italic">No messages yet</p>
        )}
      </div>
    </Link>
  )
}

export type { DirectChatData, OtherUser }
export default ChatListItem
