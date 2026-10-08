import { useRef, useState, type FC, type FormEvent } from 'react'
import { Camera } from 'lucide-react'
import client from '../../api/client'
import { useAuth } from '../../contexts/AuthContext'
import { PanelShell, primaryButton, subtleButton } from './ProfileParts'

export interface ProfileData {
  bio: string | null
  website_url: string | null
  location: string | null
}

interface ProfileEditPanelProps {
  initial: ProfileData
  onBack: () => void
  onSaved: (profile: ProfileData) => void
}

const inputClass =
  'w-full rounded-[12px] border border-outline-variant/60 bg-surface-container-low px-3 py-2.5 text-sm text-white placeholder:text-on-surface-variant/50 focus:border-primary/40 focus:outline-none'

const Field: FC<{ label: string; children: React.ReactNode }> = ({ label, children }) => (
  <label className="block">
    <span className="mb-1 block text-xs font-medium text-on-surface-variant">{label}</span>
    {children}
  </label>
)

const ProfileEditPanel: FC<ProfileEditPanelProps> = ({ initial, onBack, onSaved }) => {
  const { user, setUser } = useAuth()

  const [displayName, setDisplayName] = useState(user?.displayName ?? '')
  const [username, setUsername] = useState(user?.username ?? '')
  const [bio, setBio] = useState(initial.bio ?? '')
  const [website, setWebsite] = useState(initial.website_url ?? '')
  const [location, setLocation] = useState(initial.location ?? '')
  const [avatarFile, setAvatarFile] = useState<File | null>(null)
  const [avatarPreview, setAvatarPreview] = useState<string | null>(null)
  const fileRef = useRef<HTMLInputElement>(null)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [saved, setSaved] = useState(false)

  const shownAvatar = avatarPreview ?? user?.avatarUrl ?? null

  const onFile = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0]
    if (!file) return
    if (avatarPreview) URL.revokeObjectURL(avatarPreview)
    setAvatarFile(file)
    setAvatarPreview(URL.createObjectURL(file))
  }

  const submit = async (e: FormEvent) => {
    e.preventDefault()
    setSaving(true)
    setError('')
    setSaved(false)
    try {
      const fd = new FormData()
      fd.append('display_name', displayName)
      fd.append('username', username)
      // Selalu dikirim (juga saat kosong) supaya field bisa dikosongkan;
      // string kosong diubah Laravel menjadi null dan field-nya nullable.
      fd.append('bio', bio)
      fd.append('website_url', website)
      fd.append('location', location)
      if (avatarFile) fd.append('avatar', avatarFile)

      const res = await client.post('/user/profile', fd)
      // Perbarui user di AuthContext supaya nama/avatar langsung berubah di
      // seluruh UI tanpa reload.
      if (res.data?.user) setUser(res.data.user)
      onSaved({ bio: bio || null, website_url: website || null, location: location || null })
      setAvatarFile(null)
      setSaved(true)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to save profile')
    } finally {
      setSaving(false)
    }
  }

  return (
    <PanelShell>
      <form onSubmit={submit} className="flex flex-col gap-4">
        <div className="flex items-center gap-4">
          <label className="group relative h-20 w-20 shrink-0 cursor-pointer overflow-hidden rounded-[999px] bg-surface-container-highest p-[3px] ring-1 ring-outline-variant">
            <span className="flex h-full w-full items-center justify-center overflow-hidden rounded-[999px] bg-surface-container-low">
              {shownAvatar ? (
                <img src={shownAvatar} alt="" className="h-full w-full object-cover" />
              ) : (
                <span className="text-2xl font-bold text-white">{displayName.charAt(0).toUpperCase()}</span>
              )}
            </span>
            <span className="absolute inset-0 flex items-center justify-center rounded-[999px] bg-black/50 opacity-0 transition-opacity group-hover:opacity-100">
              <Camera className="h-5 w-5 text-white" />
            </span>
            <input
              ref={fileRef}
              type="file"
              accept="image/png,image/jpeg,image/gif,image/webp"
              onChange={onFile}
              className="hidden"
            />
          </label>
          <p className="text-xs text-on-surface-variant">Tap the photo to change it. PNG, JPG, GIF or WebP, up to 2 MB.</p>
        </div>

        <Field label="Display name">
          <input className={inputClass} value={displayName} onChange={(e) => setDisplayName(e.target.value)} />
        </Field>
        <Field label="Username">
          <input className={inputClass} value={username} onChange={(e) => setUsername(e.target.value)} />
        </Field>
        <Field label="Bio">
          <textarea className={inputClass} rows={3} maxLength={500} value={bio} onChange={(e) => setBio(e.target.value)} />
        </Field>
        <Field label="Website">
          <input className={inputClass} placeholder="https://..." value={website} onChange={(e) => setWebsite(e.target.value)} />
        </Field>
        <Field label="Location">
          <input className={inputClass} value={location} onChange={(e) => setLocation(e.target.value)} />
        </Field>

        {error && <p className="text-sm text-red-400">{error}</p>}
        {saved && <p className="text-sm text-green-400">Profile saved.</p>}

        <div className="flex gap-3">
          <button type="submit" disabled={saving} className={`${primaryButton} flex-1 rounded-[15px]`}>
            {saving ? 'Saving...' : 'Save'}
          </button>
          <button type="button" onClick={onBack} className={`${subtleButton} flex-1 rounded-[15px]`}>
            Done
          </button>
        </div>
      </form>
    </PanelShell>
  )
}

export default ProfileEditPanel
