import { useEffect, type FC } from 'react'
import {
  ExternalLink,
  Globe,
  Link as LinkIcon,
  Mic,
  Play,
  Users,
  X,
} from 'lucide-react'
import { formatDuration } from '../../lib/media'

/**
 * Potongan UI yang dipakai bersama oleh Pages/Profile/Show.tsx dan
 * Pages/Discuss/About.tsx supaya tampilan keduanya selalu sama.
 *
 * Catatan: tailwind.config.js menimpa rounded-full (= 0.75rem) dan rounded-lg/xl,
 * jadi bentuk lingkaran/pil selalu pakai nilai arbitrary (rounded-[999px]).
 */

/* ───────────────────────── types ───────────────────────── */

export interface MediaItem {
  id: string
  type: 'image' | 'video'
  url: string
  thumbnail: string | null
  duration: number | null
  createdAt: string | null
  /** Asal konten: nama group (profil publik). */
  roomName?: string | null
  /** Asal konten: nama pengirim (halaman About room). */
  senderName?: string | null
}

export interface LinkItem {
  id: string
  url: string
  host: string
  createdAt: string | null
  /** Asal konten: nama group (profil publik). */
  roomName?: string | null
  /** Asal konten: nama pengirim (halaman About room). */
  senderName?: string | null
}

export interface VoiceItem {
  id: string
  url: string
  name: string
  size: number | null
  createdAt: string | null
  /** Asal konten: nama group (profil publik). */
  roomName?: string | null
  /** Asal konten: nama pengirim (halaman About room). */
  senderName?: string | null
}

/* ───────────────────────── helpers ───────────────────────── */

export const initialsOf = (name: string): string => {
  const words = name.trim().split(/\s+/).filter(Boolean)
  if (words.length === 0) return '?'
  if (words.length === 1) return words[0].slice(0, 2).toUpperCase()
  return (words[0][0] + words[1][0]).toUpperCase()
}

export const fmtNumber = (n: number) => n.toLocaleString('en-US')

export const fmtDate = (iso: string | null): string => {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

export const fmtBytes = (bytes: number | null): string => {
  if (!bytes) return ''
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}


/* ───────────────────────── avatar ───────────────────────── */

export function ProfileAvatar({ src, name }: { src: string | null; name: string }) {
  return (
    <div className="rounded-[999px] ring-4 ring-[#2c2c2c] ring-offset-[5px] ring-offset-[#0b0b0b]">
      <div className="flex h-[80px] w-[80px] items-center justify-center overflow-hidden rounded-[999px] bg-[#e8e8e8]">
        {src ? (
          <img src={src} alt="" className="h-full w-full object-cover" />
        ) : (
          <span className="select-none font-serif text-xl font-medium leading-none text-[#1b1b1b]">
            {initialsOf(name)}
          </span>
        )}
      </div>
    </div>
  )
}


/* ───────────────────────── tab contents ───────────────────────── */

export function Empty({ icon: Icon, text }: { icon: typeof Users; text: string }) {
  return (
    <div className="flex flex-col items-center gap-3 py-14 text-neutral-600">
      <Icon className="h-8 w-8" />
      <p className="text-sm">{text}</p>
    </div>
  )
}

export function MediaGrid({ items, onOpen }: { items: MediaItem[]; onOpen: (m: MediaItem) => void }) {
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

export function LinkList({ items }: { items: LinkItem[] }) {
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
                {(l.roomName ?? l.senderName) ? `${l.roomName ?? l.senderName} · ` : ''}
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

export function VoiceList({ items }: { items: VoiceItem[] }) {
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
                {[v.roomName ?? v.senderName, fmtDate(v.createdAt), fmtBytes(v.size)].filter(Boolean).join(' · ')}
              </p>
            </div>
          </div>
          <audio controls preload="none" src={v.url} className="h-9 w-full" />
        </li>
      ))}
    </ul>
  )
}


/* ───────────────────────── fullscreen media viewer ───────────────────────── */

export function MediaViewer({ item, onClose }: { item: MediaItem; onClose: () => void }) {
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


/* ───────────────────────── stat cards & tab pills ───────────────────────── */

export interface StatCardItem {
  label: string
  value: number
  icon: FC<{ className?: string; strokeWidth?: number }>
}

export function StatGrid({ items }: { items: StatCardItem[] }) {
  return (
    <div className="mt-7 grid grid-cols-3 gap-3">
      {items.map((s) => (
        <div key={s.label} className="min-w-0 rounded-[18px] bg-surface-container-low px-3 py-3 ">
          <div className="flex items-center gap-2 text-neutral-500">
            <s.icon className="h-3 w-3 shrink-0" strokeWidth={1.4} />
            <span className="truncate text-[8px] font-semibold uppercase tracking-wider">{s.label}</span>
          </div>
          <p className="mt-1.5 truncate text-md font-bold leading-none text-white">{fmtNumber(s.value)}</p>
        </div>
      ))}
    </div>
  )
}

export function TabPills<T extends string>({
  tabs,
  active,
  onChange,
  label,
}: {
  tabs: { id: T; label: string }[]
  active: T | null
  onChange: (id: T) => void
  label: string
}) {
  return (
    <div
      role="tablist"
      aria-label={label}
      style={{ gridTemplateColumns: `repeat(${tabs.length}, minmax(0, 1fr))` }}
      className="mt-5 grid gap-1 rounded-[999px] border border-[#262626] bg-[#0f0f0f] p-1.5"
    >
      {tabs.map((t) => {
        const isActive = active === t.id
        return (
          <button
            key={t.id}
            type="button"
            role="tab"
            aria-selected={isActive}
            onClick={() => onChange(t.id)}
            className={`rounded-[999px] py-2 px-2 text-sm font-medium transition-colors ${
              isActive ? 'bg-[#252525] text-white' : 'text-neutral-400 hover:text-neutral-200'
            }`}
          >
            {t.label}
          </button>
        )
      })}
    </div>
  )
}
