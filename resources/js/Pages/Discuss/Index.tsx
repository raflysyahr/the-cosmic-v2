import { useState, type ReactNode } from 'react'
import { Link } from '@inertiajs/react'
import DiscussRoomCard from '../../Components/discuss/DiscussRoomCard'
import type { RoomCardData,DirectChatData } from '../../Components/discuss/DiscussRoomCard'
import ChatListItem from '../../Components/discuss/ChatListItem'
import LayoutDiscuss from '../../Components/layout/LayoutDiscuss'
import { Trophy,Search } from 'lucide-react'

interface PageProps {
  rooms: RoomCardData[],
  directChats:DirectChatData[]
}

export default function DiscussIndex({ rooms,directChats }: PageProps) {

  const [groupTabChat,setGroupTabChat] = useState("direct");
  const [search,setSearch] = useState("")

  return (

      <>
      <div className="px-2 pb-4">
        <div className="sticky  top-0 z-10 bg-surface pt-4">




        <div className="flex items-center rounded-full border border-neutral-800 justify-center px-3 w-[300px] ml-5 mb-2 ">
        <Search className="w-4 h-4" />
        <input
        type="text"
        value={search}
        onChange={(e) => setSearch(e.target.value)}
        placeholder="Search chats..."
className="
    w-full

    border-0
    bg-transparent
    py-2
    pl-0
    pr-4
    text-sm
    text-neutral-200
    placeholder-neutral-500
    appearance-none
    outline-none
    shadow-none
    focus:outline-none
    focus:ring-0
    focus:border-0
    focus:shadow-none
  "

  style={{
    WebkitAppearance: 'none',
    appearance: 'none',
    WebkitTapHighlightColor: 'transparent',
  }}
        />
        </div>









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
        </div>

        <div>
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
