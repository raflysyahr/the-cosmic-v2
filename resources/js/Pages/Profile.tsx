import { useState, useRef, useEffect } from 'react'
import { router, usePage } from '@inertiajs/react'
import { ArrowLeft, Save, Bookmark, MessageSquare, Users, Trophy, Camera } from 'lucide-react'
import Layout from '../Components/layout/Layout'
import Avatar from '../Components/ui/Avatar'
import client from '../api/client'
import { useBookmarks } from '../hooks/useBookmarks'
import { useAuth } from '../contexts/AuthContext'

interface ProfileData {
    bio: string | null
    website_url: string | null
    location: string | null
}

interface Stats {
    messages: number
    rooms: number
    xp: number
    member_since: string
}

interface PageProps {
    profile: ProfileData | null
    stats: Stats
}

export default function Profile() {
    const { profile: initialProfile, stats } = usePage<PageProps>().props
    const { user } = useAuth()
    const userId = user?.id
    const { items: bookmarkItems } = useBookmarks(userId)

    useEffect(() => {
        if (!user) router.visit('/login', { replace: true })
    }, [user])
    if (!user) return null

    const [displayName, setDisplayName] = useState(user.displayName)
    const [username, setUsername] = useState(user.username)
    const [bio, setBio] = useState(initialProfile?.bio ?? '')
    const [website, setWebsite] = useState(initialProfile?.website_url ?? '')
    const [location, setLocation] = useState(initialProfile?.location ?? '')
    const [avatarFile, setAvatarFile] = useState<File | null>(null)
    const [avatarPreview, setAvatarPreview] = useState<string | null>(null)
    const fileRef = useRef<HTMLInputElement>(null)
    const [saving, setSaving] = useState(false)
    const [error, setError] = useState('')
    const [success, setSuccess] = useState(false)

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0]
        if (file) {
            setAvatarFile(file)
            setAvatarPreview(URL.createObjectURL(file))
        }
    }

    const handleSave = async (e: React.FormEvent) => {
        e.preventDefault()
        setSaving(true)
        setError('')
        setSuccess(false)
        try {
            const fd = new FormData()
            fd.append('display_name', displayName)
            fd.append('username', username)
            if (bio) fd.append('bio', bio)
            if (website) fd.append('website_url', website)
            if (location) fd.append('location', location)
            if (avatarFile) fd.append('avatar', avatarFile)

            await client.post('/user/profile', fd)
            setSuccess(true)
            setTimeout(() => setSuccess(false), 3000)
        } catch (err) {
            if (err instanceof Error) setError(err.message)
            else setError('Failed to save profile')
        } finally {
            setSaving(false)
        }
    }

    // Bookmarks live entirely in localStorage (see useBookmarks()), not on
    // the server, so this count comes from the client hook rather than
    // `stats` from the backend.
    const statCards = [
        { label: 'Bookmarks', value: bookmarkItems.length, icon: Bookmark },
        { label: 'Messages', value: stats.messages, icon: MessageSquare },
        { label: 'Rooms', value: stats.rooms, icon: Users },
        { label: 'Total XP', value: stats.xp, icon: Trophy },
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

                <div className="mb-8 flex flex-col items-center gap-3">
                    <Avatar
                        src={avatarPreview || user.avatarUrl}
                        alt={user.displayName}
                        size={80}
                        border
                        asLabel
                        className="cursor-pointer"
                    >
                        <div className="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 transition-opacity hover:opacity-100">
                            <Camera className="h-5 w-5 text-white" />
                        </div>
                        <input
                            ref={fileRef}
                            type="file"
                            accept="image/png,image/jpg,image/gif,image/webp"
                            onChange={handleFileChange}
                            className="hidden"
                        />
                    </Avatar>
                    <div>
                        <h1 className="text-center text-lg font-bold text-white">{displayName || user.displayName}</h1>
                        <p className="text-center text-xs text-[#555]">@{username}</p>
                    </div>
                </div>

                <div className="mb-8 grid grid-cols-3 gap-3">
                    {statCards.map((s,i) => (
                        <div key={s.label} className={` bg-[#111] px-4 py-3 flex items-center flex-col ${i === (statCards.length-1) && "col-span-3"}`}>
                            <div className="flex items-center gap-2">
                                <s.icon className="h-4 w-4 text-[#555]" />

                            </div>
                            <p className="mt-1 text-lg font-bold text-white">{s.value}</p>
                        </div>
                    ))}
                </div>

                <p className="mb-8 text-center text-[10px] text-[#555]">
                    Member since {stats.member_since} · {user.role}
                </p>

                <form onSubmit={handleSave} className="flex flex-col gap-4">
                    <Field label="Display Name" value={displayName} onChange={setDisplayName} />
                    <Field label="Username" value={username} onChange={setUsername} />
                    <Field label="Bio" value={bio} onChange={setBio} multiline />
                    <Field label="Website" value={website} onChange={setWebsite} placeholder="https://..." />
                    <Field label="Location" value={location} onChange={setLocation} />

                    {error && <p className="text-xs text-red-400">{error}</p>}
                    {success && <p className="text-xs text-green-400">Profile saved!</p>}

                    <div className="mt-2 flex gap-3">
                        <button
                            type="submit"
                            disabled={saving}
                            className="flex flex-1 items-center justify-center gap-2 bg-white py-2 text-xs font-bold text-black transition-colors hover:bg-[#ccc] disabled:opacity-50"
                        >
                            <Save className="h-3 w-3" />
                            {saving ? 'Saving\u2026' : 'Save'}
                        </button>
                        <button
                            type="button"
                            onClick={() => router.visit('/')}
                            className="flex-1 border border-[#2A2A2A] py-2 text-xs text-[#555] transition-colors hover:bg-[#1A1A1A] hover:text-white"
                        >
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </Layout>
    )
}

function Field({
    label, value, onChange, placeholder, multiline,
}: {
    label: string
    value: string
    onChange: (v: string) => void
    placeholder?: string
    multiline?: boolean
}) {
    const cls = "w-full border border-[#2A2A2A] bg-[#111] px-3 py-2 text-xs text-white placeholder:text-[#555] transition-colors focus:border-white/30 focus:outline-none"
    return (
        <div>
            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-[#555]">{label}</label>
            {multiline ? (
                <textarea value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} rows={3} className={cls} />
            ) : (
                <input type="text" value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder} className={cls} />
            )}
        </div>
    )
}
