import { useState, useCallback, useMemo } from 'react'
import { router, Link } from '@inertiajs/react'
import { Shield, Crown, UserMinus, MicOff, Ban, Users, Image as ImageIcon, Link as LinkIcon, Flag } from 'lucide-react'
import { apiFetch } from '../../api/fetch'
import { extractErrorMessage } from '../../utils/discuss'
import {  useEffect, type FC } from 'react'
import { usePopup } from '../../contexts/PopupContext'
import Popup from '../../Components/ui/Popup'
import { ArrowLeft, User} from 'lucide-react'

interface RoomData {
  id: string
  slug: string
  name: string
  cover_url: string | null
  context_type: string | null
  type: string
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

interface PageProps {
  room: RoomData
  directRecipient: DirectRecipient | null
  members: Member[]
  currentUserId: string
}



const roleOrder = { admin: 0, moderator: 1, member: 2 }
const roleLabel = { admin: 'Admin', moderator: 'Moderator', member: 'Member' }

function isCurrentlyMuted(mutedUntil: string | null): boolean {
  if (!mutedUntil) return false
  return new Date(mutedUntil) > new Date()
}

export default function DiscussAbout(props: PageProps) {
  const [members, setMembers] = useState<Member[]>(props.members ?? [])
  const [activeTab, setActiveTab] = useState<'member' | 'media'>('member')
  const [generatingInvite, setGeneratingInvite] = useState(false)
  const { popup, showPopup, closePopup } = usePopup()

  const room = props.room
  const directRecipient = props.directRecipient
  const currentUserId = props.currentUserId
  const isDirectChat = room.context_type === 'direct'

  const headerTitle = isDirectChat && directRecipient ? directRecipient.display_name : room.name
  const headerAvatar = isDirectChat ? directRecipient?.avatar_url ?? null : room.cover_url

  const currentMember = useMemo(
    () => members.find((m) => m.userId === currentUserId),
    [members, currentUserId],
  )
  const canModerate = currentMember && (currentMember.role === 'admin' || currentMember.role === 'moderator')
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

  const performModeration = useCallback((
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
  }, [room.slug, showPopup, refreshMembers])

  const handleKick = useCallback((userId: string) => {
    const target = members.find((m) => m.userId === userId)
    const name = target?.displayName ?? 'this member'

    showPopup({
      type: 'confirm',
      title: 'Kick member?',
      message: `${name} will be removed from the room. They can rejoin later if the room is public.`,
      confirmText: 'Kick',
      onConfirm: () => performModeration(userId, 'kick', 'Member kicked', `${name} has been removed from the room.`),
    })
  }, [members, showPopup, performModeration])

  const handleMute = useCallback((userId: string) => {
    const target = members.find((m) => m.userId === userId)
    const name = target?.displayName ?? 'this member'

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
        performModeration(userId, 'mute', 'Member muted', `${name} has been muted for ${minutes} minute${minutes === 1 ? '' : 's'}.`, { minutes })
      },
    })
  }, [members, showPopup, performModeration])

  const handleBan = useCallback((userId: string) => {
    const target = members.find((m) => m.userId === userId)
    const name = target?.displayName ?? 'this member'

    showPopup({
      type: 'confirm',
      title: 'Ban member?',
      message: `${name} will be permanently banned from this room and won't be able to rejoin.`,
      confirmText: 'Ban',
      onConfirm: () => performModeration(userId, 'ban', 'Member banned', `${name} has been banned from the room.`),
    })
  }, [members, showPopup, performModeration])

  const sortedMembers = useMemo(
    () => [...members].sort((a, b) => roleOrder[a.role] - roleOrder[b.role]),
    [members],
  )

  let lastRole: string | null = null

  return (
    <div className="mx-auto flex min-h-screen max-w-md flex-col bg-surface shadow-2xl">
      {/* Top AppBar */}
      <header className="sticky top-0 z-50 flex h-14 w-full items-center gap-3  bg-surface px-gutter-md">
        <button
          onClick={() => router.visit(`/discuss/${room.slug}`)}
          className="active:opacity-70 transition-opacity p-1 -ml-1"
        >
          <ArrowLeft className="text-primary w-4 h-4" />
        </button>

      </header>

      {/* Room identity */}
      <div className="flex flex-col items-center gap-2 px-gutter-md pb-4 pt-8">
        <div className="flex h-24 w-24 items-center justify-center overflow-hidden rounded-[50%] bg-surface-container-high mb-3">
          {headerAvatar ? (
            <img src={headerAvatar} alt="" className="h-full w-full object-cover" />
          ) : (
            isDirectChat ? <User className="text-on-surface-variant text-[40px]" /> : <Users className="text-on-surface-variant text-[40px]" />
          )}
        </div>
        <h2 className="font-headline-sm text-headline-sm text-on-surface text-center">{headerTitle}</h2>
        {!isDirectChat && (
          <span className="font-body-sm text-body-sm text-on-surface-variant">
            {members.length} member{members.length === 1 ? '' : 's'}
          </span>
        )}
        {canInvite && (
          <button
            onClick={handleGenerateInvite}
            disabled={generatingInvite}
            className="mt-1 flex items-center gap-1.5 rounded-full border border-outline-variant px-3 py-1.5 font-label-sm text-label-sm text-primary transition-opacity active:opacity-70 disabled:opacity-50"
          >
            <LinkIcon size={14} />
            {generatingInvite ? 'Generating…' : 'Copy Invite Link'}
          </button>
        )}
      </div>

      {/* Tabs — directly below avatar and name */}
      <div className="flex border-b border-outline-variant">
        <button
          onClick={() => setActiveTab('member')}
          className={`flex flex-1 items-center justify-center gap-1.5 py-3 font-label-md text-label-md transition-colors ${
            activeTab === 'member'
              ? 'border-b-2 border-primary text-primary'
              : 'text-on-surface-variant'
          }`}
        >
          <Users size={16} />
          Member
        </button>
        <button
          onClick={() => setActiveTab('media')}
          className={`flex flex-1 items-center justify-center gap-1.5 py-3 font-label-md text-label-md transition-colors ${
            activeTab === 'media'
              ? 'border-b-2 border-primary text-primary'
              : 'text-on-surface-variant'
          }`}
        >
          <ImageIcon size={16} />
          Media
        </button>
      </div>

      {canModerate && !isDirectChat && (
        <Link
          href={`/discuss/${room.slug}/reports`}
          className="flex items-center gap-2 border-b border-outline-variant/30 px-4 py-3 font-label-md text-label-md text-on-surface-variant transition-colors hover:text-on-surface"
        >
          <Flag size={16} />
          Reports
        </Link>
      )}

      {/* Tab content */}
      <div className="flex-1 overflow-y-auto">
        {activeTab === 'member' ? (
          <div className="flex flex-col">
            {sortedMembers.map((m) => {
              const showLabel = m.role !== lastRole
              lastRole = m.role
              const isSelf = m.userId === currentUserId
              const muted = isCurrentlyMuted(m.mutedUntil)
              const avatarFallback = m.displayName?.charAt(0).toUpperCase() || '?'

              const rowContent = (
                <div className="flex min-w-0 flex-1 items-center gap-3">
                  <div className="relative shrink-0">
                    <div className="flex h-10 w-10 items-center justify-center overflow-hidden rounded-[50%] bg-surface-container-high">
                      {m.avatarUrl ? (
                        <img src={m.avatarUrl} alt="" className="h-full w-full object-cover" />
                      ) : (
                        <span className="font-label-sm text-label-sm text-on-surface-variant">{avatarFallback}</span>
                      )}
                    </div>
                    <div
                      className={`absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-surface ${
                        m.isOnline ? 'bg-primary' : 'bg-outline-variant'
                      }`}
                    />
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-1.5">
                      <span className="truncate font-label-md text-label-md text-on-surface">{m.displayName}</span>
                      {m.role === 'admin' && <Crown size={13} className="shrink-0 text-yellow-500" />}
                      {m.role === 'moderator' && <Shield size={13} className="shrink-0 text-blue-500" />}
                      {muted && (
                        <span title="Muted" className="shrink-0">
                          <MicOff size={13} className="text-error" />
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
                <div key={m.userId}>
                  {showLabel && (
                    <div className="px-gutter-md pt-3 pb-1">
                      <span className="font-label-sm text-label-sm uppercase tracking-wider text-on-surface-variant">
                        {roleLabel[m.role]}
                      </span>
                    </div>
                  )}
                  <div className="flex items-center gap-2 px-gutter-md py-2 transition-colors hover:bg-surface-container-low">
                    {m.username ? (
                      <Link href={`/u/${m.username}`} className="min-w-0 flex-1">
                        {rowContent}
                      </Link>
                    ) : (
                      <div className="min-w-0 flex-1">{rowContent}</div>
                    )}

                    {canModerate && !isSelf && (
                      <div className="flex shrink-0 gap-1">
                        <button
                          onClick={() => handleKick(m.userId)}
                          className="rounded-full p-1.5 text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-error"
                          title="Kick"
                        >
                          <UserMinus size={16} />
                        </button>
                        <button
                          onClick={() => handleMute(m.userId)}
                          className="rounded-full p-1.5 text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-yellow-500"
                          title="Mute"
                        >
                          <MicOff size={16} />
                        </button>
                        <button
                          onClick={() => handleBan(m.userId)}
                          className="rounded-full p-1.5 text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-error"
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
        ) : (
          <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
            <ImageIcon size={32} className="text-on-surface-variant" />
            <p className="font-body-md text-body-md text-on-surface-variant">Media gallery coming soon</p>
          </div>
        )}
      </div>

      {/* Popup layer — this page doesn't use <Layout>, so it must render its own. */}
      {popup && <Popup config={popup} onClose={closePopup} />}
    </div>
  )
}
