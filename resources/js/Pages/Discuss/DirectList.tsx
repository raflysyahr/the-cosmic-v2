import { Link } from '@inertiajs/react'
import Layout from '../../Components/layout/Layout'
import ChatListItem from '../../Components/discuss/ChatListItem'
import type { DirectChatData } from '../../Components/discuss/ChatListItem'

interface PageProps {
  directChats: DirectChatData[]
}

export default function DirectList({ directChats }: PageProps) {
  return (
    <Layout>
      <div className="mx-auto max-w-4xl px-2 py-4">
        <h1 className="mb-4 font-headline-sm text-headline-sm tracking-wider text-primary hidden">
          DIRECT MESSAGES
        </h1>

        {/* Tabs */}
        <div className="mb-2 flex gap-1 border-b border-outline-variant/30">
          <span className="border-b-2 border-primary px-4 py-2 font-label-md text-label-md text-primary">
            Chats
          </span>
          <Link
            href="/discuss"
            className="px-4 py-2 font-label-md text-label-md text-on-surface-variant transition-colors hover:text-on-surface"
          >
            Groups
          </Link>
        </div>

        {directChats.length === 0 ? (
          <p className="py-12 text-center font-body-sm text-body-sm text-on-surface-variant">
            No conversations yet.
          </p>
        ) : (
          <div className="flex flex-col divide-y divide-outline-variant/30">
            {directChats.map((room) => (
              <ChatListItem key={room.id} room={room} />
            ))}
          </div>
        )}
      </div>
    </Layout>
  )
}
