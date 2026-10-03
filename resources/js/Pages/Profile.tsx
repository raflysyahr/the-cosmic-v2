import { useEffect, useState, type ReactNode } from 'react'
import { router, usePage } from '@inertiajs/react'
import { CalendarDays, Database, Download, FileText, Mail, ShieldCheck, User as UserIcon } from 'lucide-react'
import LayoutDiscuss from '../Components/layout/LayoutDiscuss'
import { useShellChrome } from '../Components/layout/ShellChrome'
import ProfileHeader, { type CultivationStatus } from '../Components/profile/ProfileHeader'
import ProfileEditPanel, { type ProfileData } from '../Components/profile/ProfileEditPanel'
import ProfileAccountPanel from '../Components/profile/ProfileAccountPanel'
import ProfileStoragePanel from '../Components/profile/ProfileStoragePanel'
import { Card, InfoRow, MenuRow } from '../Components/profile/ProfileParts'
import client from '../api/client'
import { useAuth } from '../contexts/AuthContext'
import { usePopup } from '../contexts/PopupContext'
import { usePwaInstall } from '../lib/pwa'

interface PageProps {
  profile: ProfileData | null
  joined: string | null
  emailVerified: boolean
}

type Section = 'home' | 'edit' | 'account' | 'storage'

function Frame({ children }: { children: ReactNode }) {
  return <div className="pb-8">{children}</div>
}

const SECTION_TITLES: Record<Exclude<Section, 'home'>, string> = {
  edit: 'Edit profile',
  account: 'Account',
  storage: 'Storage',
}

const ROLE_LABELS: Record<string, string> = {
  reader: 'Reader',
  moderator: 'Moderator',
  creator: 'Creator',
  admin: 'Admin',
}

/**
 * Profil sendiri, tampil di layout Discuss (navigasi bawah, banner, toast).
 * Menggantikan halaman profil lama. Profil publik user lain tetap di
 * Pages/Profile/Show.tsx.
 */
export default function Profile() {
  const { profile: initialProfile, joined, emailVerified } = usePage<PageProps>().props
  const { user } = useAuth()
  const { showPopup } = usePopup()
  const pwa = usePwaInstall()

  const [section, setSection] = useState<Section>('home')
  const [profile, setProfile] = useState<ProfileData>(
    initialProfile ?? { bio: null, website_url: null, location: null },
  )
  const [cultivation, setCultivation] = useState<CultivationStatus | null>(null)
  const [cultivationFailed, setCultivationFailed] = useState(false)

  useEffect(() => {
    if (!user) router.visit('/login', { replace: true })
  }, [user])

  useEffect(() => {
    if (!user) return
    let cancelled = false
    client.get('/cultivation')
      .then((res) => { if (!cancelled) setCultivation(res.data?.data ?? null);console.log(res) })
      .catch(() => { if (!cancelled) setCultivationFailed(true) })
    return () => { cancelled = true }
  }, [user?.id])

  // Di sub-section: navbar bawah hilang, header berubah jadi tombol back + judul.
  // Harus dipanggil sebelum early return di bawah (aturan hooks).
  useShellChrome(
    section === 'home'
      ? null
      : { hideNav: true, title: SECTION_TITLES[section], onBack: () => setSection('home') },
  )

  if (!user) return null

  const roleLabel = ROLE_LABELS[user.role] ?? user.role
  const back = () => setSection('home')

  if (section === 'edit') {
    return (
      <Frame>
        <ProfileEditPanel initial={profile} onBack={back} onSaved={setProfile} />
      </Frame>
    )
  }

  if (section === 'account') {
    return (
      <Frame>
        <ProfileAccountPanel emailVerified={emailVerified} roleLabel={roleLabel} />
      </Frame>
    )
  }

  if (section === 'storage') {
    return (
      <Frame>
        <ProfileStoragePanel />
      </Frame>
    )
  }

  return (
    <Frame>
        <ProfileHeader
          displayName={user.displayName}
          username={user.username}
          avatarUrl={user.avatarUrl}
          roleLabel={roleLabel}
          cultivation={cultivation}
          cultivationFailed={cultivationFailed}
        />

        <Card>
          <InfoRow icon={Mail} label="Email">{user.email}</InfoRow>
          <InfoRow icon={FileText} label="Bio">
            {profile.bio ? profile.bio : <span className="text-on-surface-variant/60">No bio yet</span>}
          </InfoRow>
          <InfoRow icon={CalendarDays} label="Joined">{joined ?? '-'}</InfoRow>
        </Card>

        <Card>
          <MenuRow
            icon={UserIcon}
            title="Profile"
            subtitle="Edit your profile information"
            onClick={() => setSection('edit')}
          />
          <MenuRow
            icon={ShieldCheck}
            title="Account"
            subtitle="Security & privacy settings"
            onClick={() => setSection('account')}
          />
          <MenuRow
            icon={Database}
            title="Storage"
            subtitle="Manage your files and cache"
            onClick={() => setSection('storage')}
          />
          {(pwa.canPrompt || pwa.needsManualInstall) && (
            <MenuRow
              icon={Download}
              title="Install app"
              subtitle="Add The Cosmic to your home screen"
              onClick={() => {
                if (pwa.canPrompt) {
                  void pwa.promptInstall()
                  return
                }
                // iOS tidak punya prompt native.
                showPopup({
                  type: 'info',
                  title: 'Install The Cosmic',
                  message: 'Tap the Share button in Safari, then choose "Add to Home Screen".',
                })
              }}
            />
          )}
        </Card>
    </Frame>
  )
}

Profile.layout = (page: ReactNode) => <LayoutDiscuss>{page}</LayoutDiscuss>
