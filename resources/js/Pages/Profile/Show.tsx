import { useState } from 'react'
import { router, usePage } from '@inertiajs/react'
import { ArrowLeft, MessageSquare, Users, Trophy, Link as LinkIcon, MapPin } from 'lucide-react'
import Layout from '../../Components/layout/Layout'
import Avatar from '../../Components/ui/Avatar'
import { apiFetch } from '../../api/fetch'

interface PublicProfileStats {
  messages: number
  rooms: number
  xp: number
}

interface PublicProfile {
  id: string
  username: string
  displayName: string
  avatarUrl: string | null
  bio: string | null
  websiteUrl: string | null
  location: string | null
  memberSince: string | null
  stats: PublicProfileStats
}

interface PageProps {
  profile: PublicProfile
  isSelf: boolean
}

export default function PublicProfileShow() {
  const { profile, isSelf } = usePage<PageProps>().props
  const [messaging, setMessaging] = useState(false)
  const [error, setError] = useState('')

  const handleMessage = () => {
    setMessaging(true)
    setError('')
    apiFetch(`/direct/${profile.username}`, { method: 'POST' })
      .then((r) => {
        // The endpoint issues a redirect to the room page on success.
        if (r.redirected) {
          router.visit(r.url)
          return
        }
        if (!r.ok) throw new Error('Could not start a conversation.')
        router.visit('/direct')
      })
      .catch((err: Error) => setError(err.message || 'Could not start a conversation.'))
      .finally(() => setMessaging(false))
  }

  const statCards = [
    { label: 'Messages', value: profile.stats.messages, icon: MessageSquare },
    { label: 'Rooms', value: profile.stats.rooms, icon: Users },
    { label: 'Total XP', value: profile.stats.xp, icon: Trophy },
  ]

  return (
    <Layout>
      <div className="mx-auto max-w-lg px-4 py-8">
        <button
          onClick={() => router.visit('/')}
          className="mb-6 flex items-center gap-1 text-xs text-[#555] transition-colors hover:text-white"
        >
          <ArrowLeft className="h-3 w-3" /> Back
        </button>

        <div className="mb-6 flex flex-col items-center gap-3">
          <Avatar src={profile.avatarUrl} alt={profile.displayName} size={120} border />
          <div>
            <h1 className="text-center text-lg font-bold text-white">{profile.displayName}</h1>
            <p className="text-center text-xs text-[#555]">@{profile.username}</p>
          </div>

          {profile.bio && (
            <p className="max-w-sm text-center text-sm text-[#aaa]">{profile.bio}</p>
          )}

          <div className="flex flex-wrap items-center justify-center gap-4 text-xs text-[#555]">
            {profile.location && (
              <span className="flex items-center gap-1">
                <MapPin className="h-3 w-3" /> {profile.location}
              </span>
            )}
            {profile.websiteUrl && (
              <a
                href={profile.websiteUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center gap-1 transition-colors hover:text-white"
              >
                <LinkIcon className="h-3 w-3" /> Website
              </a>
            )}
          </div>

          {!isSelf && (
            <button
              onClick={handleMessage}
              disabled={messaging}
              className="mt-2 flex items-center gap-2 bg-white px-5 py-2 text-xs font-bold text-black transition-colors hover:bg-[#ccc] disabled:opacity-50"
            >
              <MessageSquare className="h-3 w-3" />
              {messaging ? 'Opening…' : 'Message'}
            </button>
          )}
          {error && <p className="text-xs text-red-400">{error}</p>}
        </div>

        <div className="mb-8 grid grid-cols-3 gap-3">
          {statCards.map((s) => (
            <div key={s.label} className="border border-[#2A2A2A] bg-[#111] px-4 py-3">
              <div className="flex items-center gap-2">
                <s.icon className="h-3 w-3 text-[#555]" />
                <span className="text-[10px] font-semibold uppercase tracking-wider text-[#555]">{s.label}</span>
              </div>
              <p className="mt-1 text-lg font-bold text-white">{s.value}</p>
            </div>
          ))}
        </div>

        {profile.memberSince && (
          <p className="text-center text-[10px] text-[#555]">
            Member since {profile.memberSince}
          </p>
        )}
      </div>
    </Layout>
  )
}
