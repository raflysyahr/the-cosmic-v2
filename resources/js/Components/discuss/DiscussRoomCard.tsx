import { type FC } from 'react'
import { Link } from '@inertiajs/react'
import EmojiText from '../../lib/emoji-renderer'
import { Users } from 'lucide-react'

interface LastMessageUser {
  id: string
  display_name: string
}

interface LastMessage {
  body: string | null
  created_at: string
  user: LastMessageUser | null
}

interface RoomCardData {
  id: string
  slug: string
  name: string
  cover_url: string | null
  type: string
  context_type: string | null
  context_id: string | null
  member_count: number
  last_message: LastMessage | null
  created_at: string
}

interface DiscussRoomCardProps {
  room: RoomCardData
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

const DiscussRoomCard: FC<DiscussRoomCardProps> = ({ room }) => {
  return (
    <Link
      href={`/discuss/${room.slug}`}
      className="flex items-start gap-3 px-gutter-md py-3 transition-colors hover:bg-surface-container"
    >
      {/* Cover */}
      <div className="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden bg-surface-container-highest rounded-lg">
        {room.cover_url ? (
          <img src={room.cover_url} alt="" className="h-full w-full object-cover" />
        ) : (
          <Users className="h-3.5 w-3.5 text-on-surface-variant" />
        )}
      </div>

      {/* Right side */}
      <div className="min-w-0 flex-1">
        {/* Row 1: title + time */}
        <div className="flex items-baseline justify-between gap-2">
          <h3 className="truncate font-label-md text-label-md text-on-surface">{room.name}</h3>
          {room.last_message && (
            <span className="shrink-0 font-label-sm text-label-sm text-on-surface-variant">

              {formatTimeAgo(room.last_message.created_at)}
            </span>
          )}
        </div>

        {/* Row 2: last message */}
        {room.last_message ? (
          <p className="mt-0.5 truncate font-body-sm text-body-sm text-on-surface-variant">
            <span className="text-on-surface-variant">{room.last_message.user?.display_name ?? 'Unknown'}</span>
            : <EmojiText text={room.last_message.body || ''} />
          </p>
        ) : (
          <p className="mt-0.5 truncate font-body-sm text-body-sm text-on-surface-variant italic">No messages yet</p>
        )}
      </div>
    </Link>
  )
}

export type { RoomCardData, LastMessage }
export default DiscussRoomCard
