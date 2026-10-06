import { useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { Link, router, usePage } from '@inertiajs/react'
import {
  CalendarDays,
  Check,
  Copy,
  ExternalLink,
  Globe,
  Link as LinkIcon,
  MapPin,
  MessageCircle,
  MessageSquare,
  Mic,
  MoreVertical,
  Play,
  QrCode,
  Share2,
  Trophy,
  Users,
  X,
} from 'lucide-react'
import LayoutDiscuss from '../../Components/layout/LayoutDiscuss'
import ModalHost from '../../Components/ui/Modal'
import { useShellChrome } from '../../Components/layout/ShellChrome'
import { useModal } from '../../contexts/ModalContext'
import { apiFetch } from '../../api/fetch'
import { formatDuration } from '../../lib/media'

/* ───────────────────────── types ───────────────────────── */

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

interface MediaItem {
  id: string
  type: 'image' | 'video'
  url: string
  thumbnail: string | null
  duration: number | null
  createdAt: string | null
  roomName?: string | null
}

interface LinkItem {
  id: string
  url: string
  host: string
  createdAt: string | null
  roomName?: string | null
}

interface VoiceItem {
  id: string
  url: string
  name: string
  size: number | null
  createdAt: string | null
  roomName?: string | null
}

/** Konten dipisah: dari group vs dari chat pribadi. */
interface Scoped<T> {
  group: T[]
  private: T[]
}

type ScopeId = 'group' | 'private'

const SCOPE_LABEL: Record<ScopeId, string> = { group: 'Groups', private: 'Private chat' }

const emptyScoped = <T,>(): Scoped<T> => ({ group: [], private: [] })

interface GroupItem {
  id: string
  slug: string
  name: string
  coverUrl: string | null
  memberCount: number
}

interface PageProps {
  profile: PublicProfile
  isSelf: boolean
  media?: Scoped<MediaItem>
  links?: Scoped<LinkItem>
  voices?: Scoped<VoiceItem>
  groups?: GroupItem[]
}

type TabId = 'media' | 'links' | 'voice' | 'groups'

const TABS: { id: TabId; label: string }[] = [
  { id: 'media', label: 'Media' },
  { id: 'links', label: 'Links' },
  { id: 'voice', label: 'Voice' },
  { id: 'groups', label: 'Groups' },
]

/* ───────────────────────── helpers ───────────────────────── */

// Catatan: tailwind.config.js menimpa rounded-full (= 0.75rem) dan rounded-lg/xl,
// jadi untuk bentuk lingkaran/pil di halaman ini selalu pakai nilai arbitrary.

const initialsOf = (name: string): string => {
  const words = name.trim().split(/\s+/).filter(Boolean)
  if (words.length === 0) return '?'
  if (words.length === 1) return words[0].slice(0, 2).toUpperCase()
  return (words[0][0] + words[1][0]).toUpperCase()
}

const fmtNumber = (n: number) => n.toLocaleString('en-US')

const fmtDate = (iso: string | null): string => {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

const fmtBytes = (bytes: number | null): string => {
  if (!bytes) return ''
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

const profileUrlOf = (username: string) => `${window.location.origin}/u/${username}`

async function copyText(text: string): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(text)
    return true
  } catch {
    // Fallback untuk konteks non-HTTPS / WebView lama.
    const ta = document.createElement('textarea')
    ta.value = text
    ta.style.position = 'fixed'
    ta.style.opacity = '0'
    document.body.appendChild(ta)
    ta.select()
    const ok = document.execCommand('copy')
    document.body.removeChild(ta)
    return ok
  }
}

/* ───────────────────────── header menu (⋮) ───────────────────────── */

function ProfileMenu({ username }: { username: string }) {
  const [open, setOpen] = useState(false)
  const [copied, setCopied] = useState(false)
  const canShare = typeof navigator !== 'undefined' && typeof navigator.share === 'function'

  const copy = async () => {
    const ok = await copyText(profileUrlOf(username))
    if (ok) {
      setCopied(true)
      setTimeout(() => {
        setCopied(false)
        setOpen(false)
      }, 900)
    }
  }

  const share = async () => {
    setOpen(false)
    try {
      await navigator.share({ title: `@${username}`, url: profileUrlOf(username) })
    } catch {
      /* dibatalkan user */
    }
  }

  return (
    <>
      <button
        type="button"
        aria-label="More options"
        aria-expanded={open}
        onClick={() => setOpen((v) => !v)}
        className="flex h-10 w-10 items-center justify-center rounded-[999px] text-neutral-300 transition-colors hover:bg-neutral-900 hover:text-white"
      >
        <MoreVertical className="h-5 w-5" />
      </button>

      {open && (
        <>
          <button
            type="button"
            aria-label="Close menu"
            className="fixed inset-0 z-40 cursor-default"
            onClick={() => setOpen(false)}
          />
          <div className="absolute right-1 top-11 z-50 w-52 overflow-hidden rounded-[14px] border border-[#2a2a2a] bg-[#161616] py-1 shadow-2xl">
            <button
              type="button"
              onClick={copy}
              className="flex w-full items-center gap-3 px-4 py-3 text-left text-sm text-white transition-colors hover:bg-[#222]"
            >
              {copied ? <Check className="h-4 w-4 text-green-400" /> : <Copy className="h-4 w-4 text-neutral-400" />}
              {copied ? 'Link copied' : 'Copy profile link'}
            </button>
            {canShare && (
              <button
                type="button"
                onClick={share}
                className="flex w-full items-center gap-3 px-4 py-3 text-left text-sm text-white transition-colors hover:bg-[#222]"
              >
                <Share2 className="h-4 w-4 text-neutral-400" />
                Share profile
              </button>
            )}
          </div>
        </>
      )}
    </>
  )
}

/* ───────────────────────── QR modal ───────────────────────── */

function QrCard({ url, displayName, username }: { url: string; displayName: string; username: string }) {
  const [src, setSrc] = useState<string | null>(null)
  const [failed, setFailed] = useState(false)

  useEffect(() => {
    let cancelled = false
    // Dimuat lazy supaya library QR tidak ikut bundle awal.
    import('qrcode')
      .then(({ default: QRCode }) =>
        QRCode.toDataURL(url, {
          width: 480,
          margin: 1,
          color: { dark: '#000000', light: '#ffffff' },
        }),
      )
      .then((dataUrl) => {
        if (!cancelled) setSrc(dataUrl)
      })
      .catch(() => {
        if (!cancelled) setFailed(true)
      })
    return () => {
      cancelled = true
    }
  }, [url])

  return (
    <div className="flex flex-col items-center gap-4 rounded-[20px] border border-[#2a2a2a] bg-[#141414] px-6 pb-6 pt-8">
      <div className="flex h-56 w-56 items-center justify-center overflow-hidden rounded-[14px] bg-white p-2">
        {src ? (
          <img src={src} alt={`QR code for @${username}`} className="h-full w-full" />
        ) : (
          <span className="text-xs text-neutral-500">{failed ? 'QR unavailable' : 'Generating…'}</span>
        )}
      </div>
      <div className="text-center">
        <p className="text-base font-semibold text-white">{displayName}</p>
        <p className="text-sm text-neutral-500">@{username}</p>
      </div>
      <button
        type="button"
        onClick={() => void copyText(url)}
        className="flex items-center gap-2 rounded-[999px] border border-[#2a2a2a] px-4 py-2 text-xs font-semibold text-neutral-300 transition-colors hover:bg-[#1f1f1f]"
      >
        <Copy className="h-3.5 w-3.5" /> Copy link
      </button>
    </div>
  )
}

/* ───────────────────────── avatar ───────────────────────── */

function ProfileAvatar({ src, name }: { src: string | null; name: string }) {
  return (
    <div className="rounded-[999px] ring-4 ring-[#2c2c2c] ring-offset-[5px] ring-offset-[#0b0b0b]">
      <div className="flex h-[100px] w-[100px] items-center justify-center overflow-hidden rounded-[999px] bg-[#e8e8e8]">
        {src ? (
          <img src={src} alt="" className="h-full w-full object-cover" />
        ) : (
          <span className="select-none font-serif text-[64px] font-medium leading-none text-[#1b1b1b]">
            {initialsOf(name)}
          </span>
        )}
      </div>
    </div>
  )
}

/* ───────────────────────── tab contents ───────────────────────── */

function Empty({ icon: Icon, text }: { icon: typeof Users; text: string }) {
  return (
    <div className="flex flex-col items-center gap-3 py-14 text-neutral-600">
      <Icon className="h-8 w-8" />
      <p className="text-sm">{text}</p>
    </div>
  )
}

function MediaGrid({ items, onOpen }: { items: MediaItem[]; onOpen: (m: MediaItem) => void }) {
  if (items.length === 0) return <Empty icon={Play} text="No media shared yet" />

  return (
    <div className="grid grid-cols-3 gap-2">
      {items.map((m) => (
        <button
          key={m.id}
          type="button"
          onClick={() => onOpen(m)}
          className="relative aspect-square overflow-hidden rounded-[10px] bg-black transition-opacity active:opacity-80"
        >
          {m.thumbnail && (
            <img src={m.thumbnail} alt="" loading="lazy" className="h-full w-full object-cover" />
          )}
          {m.type === 'video' && (
            <span className="absolute bottom-1.5 left-1.5 flex items-center gap-1 rounded-[8px] bg-black/55 px-2 py-1 text-[13px] font-medium text-white backdrop-blur-sm">
              <Play className="h-3.5 w-3.5 fill-white" />
              {formatDuration(m.duration)}
            </span>
          )}
        </button>
      ))}
    </div>
  )
}

function LinkList({ items }: { items: LinkItem[] }) {
  if (items.length === 0) return <Empty icon={LinkIcon} text="No links shared yet" />

  return (
    <ul className="flex flex-col gap-2">
      {items.map((l) => (
        <li key={l.id}>
          <a
            href={l.url}
            target="_blank"
            rel="noopener noreferrer"
            className="flex items-center gap-3 rounded-[14px] border border-[#222] bg-[#0f0f0f] p-3 transition-colors hover:bg-[#151515]"
          >
            <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-[10px] bg-[#1c1c1c] text-neutral-400">
              <Globe className="h-5 w-5" />
            </span>
            <span className="min-w-0 flex-1">
              <span className="block truncate text-sm font-semibold text-white">{l.host}</span>
              <span className="block truncate text-xs text-neutral-500">
                {l.roomName ? `${l.roomName} · ` : ''}
                {l.url}
              </span>
            </span>
            <ExternalLink className="h-4 w-4 shrink-0 text-neutral-600" />
          </a>
        </li>
      ))}
    </ul>
  )
}

function VoiceList({ items }: { items: VoiceItem[] }) {
  if (items.length === 0) return <Empty icon={Mic} text="No voice messages yet" />

  return (
    <ul className="flex flex-col gap-2">
      {items.map((v) => (
        <li key={v.id} className="rounded-[14px] border border-[#222] bg-[#0f0f0f] p-3">
          <div className="mb-2 flex items-center gap-3">
            <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-[999px] bg-[#1c1c1c] text-neutral-400">
              <Mic className="h-4 w-4" />
            </span>
            <div className="min-w-0 flex-1">
              <p className="truncate text-sm font-semibold text-white">{v.name}</p>
              <p className="text-xs text-neutral-500">
                {[v.roomName, fmtDate(v.createdAt), fmtBytes(v.size)].filter(Boolean).join(' · ')}
              </p>
            </div>
          </div>
          <audio controls preload="none" src={v.url} className="h-9 w-full" />
        </li>
      ))}
    </ul>
  )
}

function GroupList({ items }: { items: GroupItem[] }) {
  if (items.length === 0) return <Empty icon={Users} text="Not in any public group" />

  return (
    <ul className="flex flex-col gap-2">
      {items.map((g) => (
        <li key={g.id}>
          <Link
            href={`/discuss/${g.slug}`}
            className="flex items-center gap-3 rounded-[14px] border border-[#222] bg-[#0f0f0f] p-3 transition-colors hover:bg-[#151515]"
          >
            <span className="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-[999px] bg-[#1c1c1c] text-base font-bold text-white">
              {g.coverUrl ? (
                <img src={g.coverUrl} alt="" loading="lazy" className="h-full w-full object-cover" />
              ) : (
                g.name.charAt(0).toUpperCase()
              )}
            </span>
            <span className="min-w-0 flex-1">
              <span className="block truncate text-sm font-semibold text-white">{g.name}</span>
              <span className="block text-xs text-neutral-500">
                {fmtNumber(g.memberCount)} {g.memberCount === 1 ? 'member' : 'members'}
              </span>
            </span>
          </Link>
        </li>
      ))}
    </ul>
  )
}

/* ───────────────────────── scope filter (Groups / Private chat) ───────────────────────── */

function ScopeFilter({
  scope,
  counts,
  onChange,
}: {
  scope: ScopeId
  counts: Record<ScopeId, number>
  onChange: (s: ScopeId) => void
}) {
  const both = counts.group > 0 && counts.private > 0

  // Hanya satu sumber yang punya isi: cukup caption, tanpa toggle.
  if (!both) {
    return (
      <p className="mb-3 px-1 text-xs font-semibold uppercase tracking-wider text-neutral-500">
        {SCOPE_LABEL[scope]}
      </p>
    )
  }

  return (
    <div className="mb-3 flex gap-2">
      {(['group', 'private'] as const).map((id) => {
        const active = scope === id
        return (
          <button
            key={id}
            type="button"
            onClick={() => onChange(id)}
            className={`flex items-center gap-2 rounded-[999px] border px-3.5 py-1.5 text-[13px] font-medium transition-colors ${
              active
                ? 'border-[#3a3a3a] bg-[#252525] text-white'
                : 'border-[#262626] text-neutral-400 hover:text-neutral-200'
            }`}
          >
            {SCOPE_LABEL[id]}
            <span className={active ? 'text-neutral-300' : 'text-neutral-600'}>{counts[id]}</span>
          </button>
        )
      })}
    </div>
  )
}

/* ───────────────────────── fullscreen media viewer ───────────────────────── */

function MediaViewer({ item, onClose }: { item: MediaItem; onClose: () => void }) {
  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
  }, [onClose])

  return (
    <div
      className="fixed inset-0 z-[110] flex items-center justify-center bg-black"
      onClick={(e) => {
        if (e.target === e.currentTarget) onClose()
      }}
    >
      <button
        type="button"
        aria-label="Close"
        onClick={onClose}
        className="absolute right-3 top-[max(0.75rem,env(safe-area-inset-top))] z-10 flex h-10 w-10 items-center justify-center rounded-[999px] bg-black/60 text-white"
      >
        <X className="h-5 w-5" />
      </button>
      {item.type === 'video' ? (
        <video
          src={item.url}
          poster={item.thumbnail ?? undefined}
          controls
          autoPlay
          playsInline
          className="max-h-full max-w-full"
        />
      ) : (
        <img src={item.url} alt="" className="max-h-full max-w-full object-contain" />
      )}
    </div>
  )
}

/* ───────────────────────── page ───────────────────────── */

export default function PublicProfileShow() {
  const {
    profile,
    isSelf,
    media = emptyScoped<MediaItem>(),
    links = emptyScoped<LinkItem>(),
    voices = emptyScoped<VoiceItem>(),
    groups = [],
  } = usePage<PageProps>().props
  const { showModal } = useModal()

  const [messaging, setMessaging] = useState(false)
  const [error, setError] = useState('')
  const [tab, setTab] = useState<TabId>('media')
  const [scope, setScope] = useState<ScopeId>('group')
  const [viewer, setViewer] = useState<MediaItem | null>(null)

  // Tombol tab hanya muncul kalau tab tersebut punya isi.
  const scopedByTab: Record<Exclude<TabId, 'groups'>, Scoped<unknown>> = {
    media,
    links,
    voice: voices,
  }
  const hasContent = (id: TabId): boolean =>
    id === 'groups'
      ? groups.length > 0
      : scopedByTab[id].group.length > 0 || scopedByTab[id].private.length > 0

  const availableTabs = TABS.filter((t) => hasContent(t.id))
  // Tab terpilih bisa jadi kosong (mis. default 'media'): jatuh ke tab pertama yang ada.
  const activeTab: TabId | null = availableTabs.find((t) => t.id === tab)?.id ?? availableTabs[0]?.id ?? null

  // Scope efektif: kalau scope terpilih kosong di tab ini, pakai scope lainnya.
  const activeScoped = activeTab && activeTab !== 'groups' ? scopedByTab[activeTab] : null
  const counts: Record<ScopeId, number> = {
    group: activeScoped?.group.length ?? 0,
    private: activeScoped?.private.length ?? 0,
  }
  const effectiveScope: ScopeId =
    counts[scope] > 0 ? scope : counts[scope === 'group' ? 'private' : 'group'] > 0 ? (scope === 'group' ? 'private' : 'group') : scope

  // Menu ⋮ di header shell. Elemen di-memo agar tidak memicu efek ulang.
  const menu = useMemo(() => <ProfileMenu username={profile.username} />, [profile.username])
  useShellChrome({ right: menu })

  const closeViewer = useCallback(() => setViewer(null), [])

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

  const openQr = () =>
    showModal({
      size: 'sm',
      bare: true,
      content: (
        <QrCard
          url={profileUrlOf(profile.username)}
          displayName={profile.displayName}
          username={profile.username}
        />
      ),
    })

  const statCards = [
    { label: 'Messages', value: profile.stats.messages, icon: MessageCircle },
    { label: 'Rooms', value: profile.stats.rooms, icon: Users },
    { label: 'Total XP', value: profile.stats.xp, icon: Trophy },
  ]

  return (
    <div className="min-h-full bg-[#0b0b0b] px-4 pb-10 pt-8">
      {/* Avatar + nama */}
      <div className="flex flex-col items-center">
        <ProfileAvatar src={profile.avatarUrl} name={profile.displayName} />

        <h1 className="mt-5 max-w-full truncate text-center text-lg font-bold leading-tight text-white">
          {profile.displayName}
        </h1>


        {!isSelf && (
          <button
            type="button"
            onClick={handleMessage}
            disabled={messaging}
            className="mt-4 flex items-center gap-2 rounded-[999px] bg-white px-3 py-2 text-sm font-bold text-black transition-colors hover:bg-[#ddd] disabled:opacity-50"
          >
            <MessageSquare className="h-4 w-4" />
            {messaging ? 'Opening…' : 'Message'}
          </button>
        )}
        {error && <p className="mt-2 text-xs text-red-400">{error}</p>}
      </div>

      {/* Statistik */}
      <div className="mt-7 grid grid-cols-3 gap-3">
        {statCards.map((s) => (
          <div key={s.label} className="min-w-0 rounded-[18px] border border-[#262626] bg-[#0f0f0f] px-3.5 py-3.5">
            <div className="flex items-center gap-2 text-neutral-500">
              <s.icon className="h-3 w-3 shrink-0" strokeWidth={1.6} />
              <span className="truncate text-xs font-semibold uppercase tracking-wider">{s.label}</span>
            </div>
            <p className="mt-1.5 truncate text-lg font-bold leading-none text-white">{fmtNumber(s.value)}</p>
          </div>
        ))}
      </div>

      {/* Info */}
      <section className="mt-5 rounded-[20px] border border-[#262626] bg-[#0f0f0f] px-5 py-5">
        <div>
          <p className="text-base text-neutral-500">Bio</p>
          <p className="mt-1 whitespace-pre-line break-words text-md leading-snug text-neutral-200">
            {profile.bio?.trim() ? profile.bio : '—'}
          </p>
        </div>

        <div className="mt-6 flex items-end justify-between gap-3 border-b border-[#262626] pb-5">
          <div className="min-w-0">
            <p className="text-base text-neutral-500">Username</p>
            <p className="mt-1 truncate text-sm text-white">@{profile.username}</p>
          </div>
          <button
            type="button"
            aria-label="Show QR code"
            onClick={openQr}
            className="flex h-11 w-11 shrink-0 items-center justify-center rounded-[12px] text-white transition-colors hover:bg-[#1c1c1c]"
          >
            <QrCode className="h-5 w-5" strokeWidth={1.6} />
          </button>
        </div>

        {(profile.location || profile.websiteUrl) && (
          <div className="flex flex-col gap-2.5 border-b border-[#262626] py-4 text-sm text-neutral-400">
            {profile.location && (
              <span className="flex items-center gap-3">
                <MapPin className="h-5 w-5 shrink-0 text-neutral-500" strokeWidth={1.6} />
                <span className="min-w-0 truncate">{profile.location}</span>
              </span>
            )}
            {profile.websiteUrl && (
              <a
                href={profile.websiteUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center gap-3 transition-colors hover:text-white"
              >
                <LinkIcon className="h-5 w-5 shrink-0 text-neutral-500" strokeWidth={1.6} />
                <span className="min-w-0 truncate">{profile.websiteUrl.replace(/^https?:\/\//i, '')}</span>
              </a>
            )}
          </div>
        )}

        {profile.memberSince && (
          <div className="flex items-center gap-3 pt-4 text-neutral-500">
            <CalendarDays className="h-4 w-4 shrink-0" strokeWidth={1.6} />
            <span className="text-sm">Member since {profile.memberSince}</span>
          </div>
        )}
      </section>

      {/* Tabs — hanya yang punya isi */}
      {availableTabs.length > 0 ? (
        <>
          <div
            role="tablist"
            aria-label="Profile content"
            style={{ gridTemplateColumns: `repeat(${availableTabs.length}, minmax(0, 1fr))` }}
            className="mt-5 grid gap-1 rounded-[999px] border border-[#262626] bg-[#0f0f0f] p-1.5"
          >
            {availableTabs.map((t) => {
              const active = activeTab === t.id
              return (
                <button
                  key={t.id}
                  type="button"
                  role="tab"
                  aria-selected={active}
                  onClick={() => setTab(t.id)}
                  className={`rounded-[999px] px-2 py-3 text-[15px] font-medium transition-colors ${
                    active ? 'bg-[#252525] text-white' : 'text-neutral-400 hover:text-neutral-200'
                  }`}
                >
                  {t.label}
                </button>
              )
            })}
          </div>

          <div role="tabpanel" className="mt-4">
            {activeScoped && <ScopeFilter scope={effectiveScope} counts={counts} onChange={setScope} />}
            {activeTab === 'media' && <MediaGrid items={media[effectiveScope]} onOpen={setViewer} />}
            {activeTab === 'links' && <LinkList items={links[effectiveScope]} />}
            {activeTab === 'voice' && <VoiceList items={voices[effectiveScope]} />}
            {activeTab === 'groups' && <GroupList items={groups} />}
          </div>
        </>
      ) : (
        <Empty icon={Users} text="Nothing shared yet" />
      )}

      {viewer && <MediaViewer item={viewer} onClose={closeViewer} />}
      <ModalHost />
    </div>
  )
}

PublicProfileShow.layout = (page: ReactNode) => <LayoutDiscuss>{page}</LayoutDiscuss>
