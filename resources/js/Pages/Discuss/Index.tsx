import { useState, type ReactNode } from 'react'
import { Link } from '@inertiajs/react'
import Layout from '../../Components/layout/Layout'
import DiscussRoomCard from '../../Components/discuss/DiscussRoomCard'
import type { RoomCardData,DirectChatData } from '../../Components/discuss/DiscussRoomCard'
import ChatListItem from '../../Components/discuss/ChatListItem'
import LayoutDiscuss from '../../Components/layout/LayoutDiscuss'
import { Trophy } from 'lucide-react'

interface PageProps {
  rooms: RoomCardData[],
  directChats:DirectChatData[]
}

export default function DiscussIndex({ rooms,directChats }: PageProps) {

  const [groupTabChat,setGroupTabChat] = useState("direct");
  const [search,setSearch] = useState("")

  return (

      <>
      <div className="mx-auto max-w-4xl px-2 pt-4 flex-1">




        <input
        type="text"
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        placeholder="Search chats..."
        className="w-full rounded-full border border-neutral-800 bg-transparent py-2 pl-10 pr-4 text-sm text-neutral-200 placeholder-neutral-500 outline-none transition-colors focus:border-neutral-600 mb-3"
        />









        {/* Tabs */}
        <div className="mb-2 flex gap-1 border-b border-outline-variant/30">
          <button
            onClick={()=> setGroupTabChat("direct")}
            className={`border-b-2 border-transparent ${groupTabChat === "direct" ? "border-b-primary":"border-b-transparent"} px-4 py-2 font-label-md text-label-md text-on-surface-variant transition-colors hover:text-on-surface`}
          >
            Chats
          </button>
          <button
          onClick={()=> setGroupTabChat("groups")}
          className={`border-b-2 border-transparent ${groupTabChat === "groups" ? "border-b-primary":"border-b-transparent"} px-4 py-2 font-label-md text-label-md text-primary`}>
            Groups
          </button>
        </div>

        <div className="h-[555px] overflow-y-scroll">
        {rooms.length === 0 ? (
          <p className="py-12 text-center font-body-sm text-body-sm text-on-surface-variant">
            No discussion rooms yet.
          </p>
        ) : (
          <div className="flex flex-col divide-y divide-outline-variant/30">
            {
            groupTabChat === "direct" ?

                directChats.map((room) => (
                    <ChatListItem key={room.id} room={room} />
                ))
            :
                rooms.map((room) => (
                  <DiscussRoomCard key={room.id} room={room} />
                ))
            }
          </div>
        )}
        </div>
      </div>
      </>

  )
}

DiscussIndex.layout = (page: ReactNode) => <LayoutDiscuss>{page}</LayoutDiscuss>
