import { useCallback, useMemo, useState, type ReactNode } from 'react'
import { Link } from '@inertiajs/react'
import {
  Ban,
  CalendarDays,
  Crown,
  Flag,
  Link as LinkIcon,
  MessageCircle,
  MicOff,
  Shield,
  Trophy,
  UserMinus,
  Users,
} from 'lucide-react'
import LayoutDiscuss from '../../Components/layout/LayoutDiscuss'
import {
  Empty,
  LinkList,
  MediaGrid,
  MediaViewer,
  ProfileAvatar,
  StatGrid,
  TabPills,
  VoiceList,
  type LinkItem,
  type MediaItem,
  type VoiceItem,
} from '../../Components/profile/SharedContent'
import { apiFetch } from '../../api/fetch'
import { extractErrorMessage } from '../../utils/discuss'
import { usePopup } from '../../contexts/PopupContext'

/* ───────────────────────── types ───────────────────────── */

interface RoomData {
  id: string
  slug: string
  name: string
  description?: string | null
  cover_url: string | null
  context_type: string | null
  type: string
  created_at?: string | null
}

interface DirectRecipient {
  id: string
  display_name: string
  avatar_url: string | null
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

interface RoomStats {
  members: number
  messages: number
  xp: number
}

interface PageProps {
  room: RoomData
  directRecipient: DirectRecipient | null
  members: Member[]
  currentUserId: string
  stats?: RoomStats
  media?: MediaItem[]
  links?: LinkItem[]
  voices?: VoiceItem[]
}

type TabId = 'members' | 'media' | 'links' | 'voice'

const TABS: { id: TabId; label: string }[] = [
  { id: 'members', label: 'Members' },
  { id: 'media', label: 'Media' },
  { id: 'links', label: 'Links' },
  { id: 'voice', label: 'Voice' },
]

/* ───────────────────────── helpers ───────────────────────── */

const roleOrder = { admin: 0, moderator: 1, member: 2 }
const roleLabel = { admin: 'Admin', moderator: 'Moderators', member: 'Members' } as const

const ROOM_TYPE_LABEL: Record<string, string> = {
  public: 'Public group',
  private: 'Private group',
  invite_only: 'Invite-only group',
}

function isCurrentlyMuted(mutedUntil: string | null): boolean {
  if (!mutedUntil) return false
  return new Date(mutedUntil) > new Date()
}

const fmtMonthYear = (iso?: string | null): string => {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  return d.toLocaleDateString('en-US', { month: 'short', year: 'numeric' })
}

/* ───────────────────────── page ───────────────────────── */

export default function DiscussAbout(props: PageProps) {
  const { room, directRecipient, currentUserId } = props
  const media = props.media ?? []
  const links = props.links ?? []
  const voices = props.voices ?? []

  const [members, setMembers] = useState<Member[]>(props.members ?? [])
  const [tab, setTab] = useState<TabId>('members')
  const [viewer, setViewer] = useState<MediaItem | null>(null)
  const [generatingInvite, setGeneratingInvite] = useState(false)
  const { showPopup } = usePopup()

  const isDirectChat = room.context_type === 'direct'

  const headerTitle = isDirectChat && directRecipient ? directRecipient.display_name : room.name
  const headerAvatar = isDirectChat ? directRecipient?.avatar_url ?? null : room.cover_url

  const stats: RoomStats = props.stats ?? { members: members.length, messages: 0, xp: 0 }

  const currentMember = useMemo(
    () => members.find((m) => m.userId === currentUserId),
    [members, currentUserId],
  )
  const canModerate = Boolean(currentMember && (currentMember.role === 'admin' || currentMember.role === 'moderator'))
  const canInvite = canModerate && !isDirectChat && room.type === 'invite_only'

  const refreshMembers = useCallback(() => {
    apiFetch(`/api/rooms/${room.slug}/members`)
      .then((r) => (r.ok ? r.json() : Promise.reject(new Error('fetch members failed'))))
      .then((m: Member[]) => setMembers(m))
      .catch(() => {})
  }, [room.slug])

  const handleGenerateInvite = useCallback(() => {
    setGeneratingInvite(true)
    apiFetch(`/api/rooms/${room.slug}/invite`, { method: 'POST' })
      .then(async (r) => {
        if (!r.ok) {
          const message = await extractErrorMessage(r, 'Could not generate an invite link.')
          throw new Error(message)
        }
        return r.json()
      })
      .then(async (data: { invite_url: string }) => {
        try {
          await navigator.clipboard.writeText(data.invite_url)
          showPopup({
            type: 'notification',
            title: 'Invite link copied',
            message: 'The link has been copied to your clipboard. Anyone with this link can join this room.',
          })
        } catch {
          showPopup({
            type: 'notification',
            title: 'Invite link generated',
            message: data.invite_url,
          })
        }
      })
      .catch((err: Error) => {
        showPopup({ type: 'warning', title: 'Could not generate link', message: err.message })
      })
      .finally(() => setGeneratingInvite(false))
  }, [room.slug, showPopup])

  const performModeration = useCallback(
    (
      userId: string,
      action: 'kick' | 'mute' | 'ban',
      successTitle: string,
      successMessage: string,
      body?: Record<string, unknown>,
    ) => {
      apiFetch(`/api/rooms/${room.slug}/members/${userId}/${action}`, {
        method: 'POST',
        ...(body ? { headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : {}),
      })
        .then(async (r) => {
          if (!r.ok) {
            const message = await extractErrorMessage(r, `Could not ${action} this member.`)
            throw new Error(message)
          }
          showPopup({ type: 'notification', title: successTitle, message: successMessage })
          refreshMembers()
        })
        .catch((err: Error) => {
          showPopup({ type: 'warning', title: 'Action failed', message: err.message })
        })
    },
    [room.slug, showPopup, refreshMembers],
  )

  const handleKick = useCallback(
    (userId: string) => {
      const name = members.find((m) => m.userId === userId)?.displayName ?? 'this member'

      showPopup({
        type: 'confirm',
        title: 'Kick member?',
        message: `${name} will be removed from the room. They can rejoin later if the room is public.`,
        confirmText: 'Kick',
        onConfirm: () => performModeration(userId, 'kick', 'Member kicked', `${name} has been removed from the room.`),
      })
    },
    [members, showPopup, performModeration],
  )

  const handleMute = useCallback(
    (userId: string) => {
      const name = members.find((m) => m.userId === userId)?.displayName ?? 'this member'

      showPopup({
        type: 'input',
        title: `Mute ${name}`,
        message: 'How many minutes should this member be muted for?',
        inputPlaceholder: 'e.g. 30',
        inputDefaultValue: '30',
        confirmText: 'Mute',
        onConfirm: (value) => {
          const minutes = parseInt(value ?? '', 10)
          if (!minutes || minutes < 1) {
            showPopup({ type: 'warning', title: 'Invalid duration', message: 'Please enter a whole number of minutes.' })
            return
          }
          performModeration(
            userId,
            'mute',
            'Member muted',
            `${name} has been muted for ${minutes} minute${minutes === 1 ? '' : 's'}.`,
            { minutes },
          )
        },
      })
    },
    [members, showPopup, performModeration],
  )

  const handleBan = useCallback(
    (userId: string) => {
      const name = members.find((m) => m.userId === userId)?.displayName ?? 'this member'

      showPopup({
        type: 'confirm',
        title: 'Ban member?',
        message: `${name} will be permanently banned from this room and won't be able to rejoin.`,
        confirmText: 'Ban',
        onConfirm: () => performModeration(userId, 'ban', 'Member banned', `${name} has been banned from the room.`),
      })
    },
    [members, showPopup, performModeration],
  )

  const sortedMembers = useMemo(
    () => [...members].sort((a, b) => roleOrder[a.role] - roleOrder[b.role]),
    [members],
  )

  // Tombol tab hanya muncul kalau tab tersebut punya isi.
  const hasContent: Record<TabId, boolean> = {
    members: members.length > 0,
    media: media.length > 0,
    links: links.length > 0,
    voice: voices.length > 0,
  }
  const availableTabs = TABS.filter((t) => hasContent[t.id])
  const activeTab: TabId | null = availableTabs.find((t) => t.id === tab)?.id ?? availableTabs[0]?.id ?? null

  const statCards = [
    { label: 'Members', value: stats.members, icon: Users },
    { label: 'Messages', value: stats.messages, icon: MessageCircle },
    { label: 'Total XP', value: stats.xp, icon: Trophy },
  ]

  const typeLabel = isDirectChat ? 'Private chat' : ROOM_TYPE_LABEL[room.type] ?? 'Group'
  const createdLabel = fmtMonthYear(room.created_at)

  let lastRole: string | null = null

  return (
    <div className="min-h-full bg-[#0b0b0b] px-4 pb-10 pt-8">
      {/* Avatar + nama */}
      <div className="flex flex-col items-center">
        <ProfileAvatar src={headerAvatar} name={headerTitle} />

        <h1 className="mt-5 max-w-[200px] text-center text-lg font-bold leading-tight text-white">
          {headerTitle}
        </h1>
        <p className="mt-1 max-w-full truncate text-center text-md text-neutral-500">
          {isDirectChat ? 'Private chat' : `${stats.members} member${stats.members === 1 ? '' : 's'}`}
        </p>

        {canInvite && (
          <button
            type="button"
            onClick={handleGenerateInvite}
            disabled={generatingInvite}
            className="mt-4 flex items-center gap-2 rounded-[999px] bg-white px-6 py-2.5 text-sm font-bold text-black transition-colors hover:bg-[#ddd] disabled:opacity-50"
          >
            <LinkIcon className="h-4 w-4" />
            {generatingInvite ? 'Generating…' : 'Copy Invite Link'}
          </button>
        )}
      </div>

      {/* Statistik */}
      <StatGrid items={statCards} />

      {/* Info */}
      <section className="mt-5 rounded-[20px] border border-[#262626] bg-[#0f0f0f] px-5 py-5">
        {!isDirectChat && (
          <div>
            <p className="text-md text-neutral-500">Description</p>
            <p className="mt-1 whitespace-pre-line break-words text-base leading-snug text-neutral-200">
              {room.description?.trim() ? room.description : '—'}
            </p>
          </div>
        )}

        <div className={`${isDirectChat ? '' : 'mt-6 '}min-w-0`}>
          <p className="text-md text-neutral-500">Type</p>
          <p className="mt-1 truncate text-sm text-white">{typeLabel}</p>
        </div>

        {createdLabel && (
          <div className="mt-5 flex items-center gap-3 border-t border-[#262626] pt-4 text-neutral-500">
            <CalendarDays className="h-4 w-4 shrink-0" strokeWidth={1.6} />
            <span className="text-sm">Created {createdLabel}</span>
          </div>
        )}

        {canModerate && !isDirectChat && (
          <Link
            href={`/discuss/${room.slug}/reports`}
            className="mt-4 flex items-center gap-3 border-t border-[#262626] pt-4 text-sm text-neutral-300 transition-colors hover:text-white"
          >
            <Flag className="h-4 w-4 shrink-0 text-neutral-500" strokeWidth={1.6} />
            Reports
          </Link>
        )}
      </section>

      {/* Tabs — hanya yang punya isi */}
      {availableTabs.length > 0 ? (
        <>
          <TabPills tabs={availableTabs} active={activeTab} onChange={setTab} label="Room content" />

          <div role="tabpanel" className="mt-4">
            {activeTab === 'members' && (
              <div className="flex flex-col gap-2">
                {sortedMembers.map((m) => {
                  const showLabel = m.role !== lastRole
                  lastRole = m.role
                  const isSelf = m.userId === currentUserId
                  const muted = isCurrentlyMuted(m.mutedUntil)
                  const avatarFallback = m.displayName?.charAt(0).toUpperCase() || '?'

                  const rowContent = (
                    <div className="flex min-w-0 flex-1 items-center gap-3">
                      <div className="relative shrink-0">
                        <div className="flex h-11 w-11 items-center justify-center overflow-hidden rounded-[999px] bg-[#1c1c1c]">
                          {m.avatarUrl ? (
                            <img src={m.avatarUrl} alt="" loading="lazy" className="h-full w-full object-cover" />
                          ) : (
                            <span className="text-sm font-semibold text-neutral-300">{avatarFallback}</span>
                          )}
                        </div>
                        <span
                          className={`absolute -bottom-0.5 -right-0.5 h-3 w-3 rounded-[999px] border-2 border-[#0f0f0f] ${
                            m.isOnline ? 'bg-green-500' : 'bg-neutral-600'
                          }`}
                        />
                      </div>
                      <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-1.5">
                          <span className="truncate text-sm font-semibold text-white">{m.displayName}</span>
                          {m.role === 'admin' && <Crown size={13} className="shrink-0 text-yellow-500" />}
                          {m.role === 'moderator' && <Shield size={13} className="shrink-0 text-blue-500" />}
                          {muted && (
                            <span title="Muted" className="shrink-0">
                              <MicOff size={13} className="text-red-400" />
                            </span>
                          )}
                        </div>
                        {m.rank && (
                          <span
                            className="mt-0.5 inline-block rounded-sm px-1 text-[10px] font-medium leading-none"
                            style={{ backgroundColor: m.rank.color + '20', color: m.rank.color }}
                          >
                            {m.rank.name}
                          </span>
                        )}
                      </div>
                    </div>
                  )

                  return (
                    <div key={m.userId} className="flex flex-col gap-2">
                      {showLabel && (
                        <p className="px-1 pt-2 text-xs font-semibold uppercase tracking-wider text-neutral-500">
                          {roleLabel[m.role]}
                        </p>
                      )}
                      <div className="flex items-center gap-2 rounded-[14px] border border-[#222] bg-[#0f0f0f] p-3 transition-colors hover:bg-[#151515]">
                        {m.username ? (
                          <Link href={`/u/${m.username}`} className="min-w-0 flex-1">
                            {rowContent}
                          </Link>
                        ) : (
                          <div className="min-w-0 flex-1">{rowContent}</div>
                        )}

                        {canModerate && !isDirectChat && !isSelf && (
                          <div className="flex shrink-0 gap-0.5">
                            <button
                              type="button"
                              onClick={() => handleKick(m.userId)}
                              className="rounded-[999px] p-2 text-neutral-500 transition-colors hover:bg-[#1c1c1c] hover:text-red-400"
                              title="Kick"
                            >
                              <UserMinus size={16} />
                            </button>
                            <button
                              type="button"
                              onClick={() => handleMute(m.userId)}
                              className="rounded-[999px] p-2 text-neutral-500 transition-colors hover:bg-[#1c1c1c] hover:text-yellow-500"
                              title="Mute"
                            >
                              <MicOff size={16} />
                            </button>
                            <button
                              type="button"
                              onClick={() => handleBan(m.userId)}
                              className="rounded-[999px] p-2 text-neutral-500 transition-colors hover:bg-[#1c1c1c] hover:text-red-400"
                              title="Ban"
                            >
                              <Ban size={16} />
                            </button>
                          </div>
                        )}
                      </div>
                    </div>
                  )
                })}
              </div>
            )}
            {activeTab === 'media' && <MediaGrid items={media} onOpen={setViewer} />}
            {activeTab === 'links' && <LinkList items={links} />}
            {activeTab === 'voice' && <VoiceList items={voices} />}
          </div>
        </>
      ) : (
        <Empty icon={Users} text="Nothing here yet" />
      )}

      {viewer && <MediaViewer item={viewer} onClose={() => setViewer(null)} />}
    </div>
  )
}

DiscussAbout.layout = (page: ReactNode) => <LayoutDiscuss>{page}</LayoutDiscuss>
