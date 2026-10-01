import type { ComponentType } from 'react'
import { Link, usePage } from '@inertiajs/react'
import { CircleFadingPlus, MessageCircle, Trophy, User } from 'lucide-react'

interface NavItem {
  id: string
  label: string
  icon: ComponentType<{ size?: number; strokeWidth?: number; className?: string }>
  // null = halamannya belum ada; item tampil tapi tidak bisa ditekan.
  url: string | null
  isActive: (path: string) => boolean
}

const ITEMS: NavItem[] = [
  {
    id: 'chats',
    label: 'Chats',
    icon: MessageCircle,
    url: '/discuss',
    isActive: (path) => path === '/discuss' || path.startsWith('/direct'),
  },
  {
    id: 'story',
    label: 'Story',
    icon: CircleFadingPlus,
    url: null,
    isActive: () => false,
  },
  {
    id: 'leaderboard',
    label: 'Leaderboard',
    icon: Trophy,
    url: '/discuss/leaderboard',
    isActive: (path) => path.startsWith('/discuss/leaderboard'),
  },
  {
    id: 'profile',
    label: 'Profile',
    icon: User,
    url: '/profile',
    isActive: (path) => path === '/profile',
  },
]

/**
 * Navigasi bawah. Memakai <Link> Inertia (pindah halaman lewat XHR, tanpa
 * reload penuh) dan menentukan tab aktif dari URL saat ini — bukan dari
 * state lokal, jadi tetap benar setelah refresh, tombol back, atau saat
 * halaman dibuka dari tautan lain.
 */
export default function NavigationBar() {
  const { url } = usePage()
  // url dari Inertia memuat query string; buang itu dan trailing slash.
  const path = (url.split('?')[0].replace(/\/+$/, '') || '/')

  return (
    <nav className="shrink-0 z-40 w-full max-w-md border-t border-neutral-900 bg-black/95 px-3 py-2.5 backdrop-blur-md">
      <div className="flex items-center justify-between">
        {ITEMS.map((item) => {
          const Icon = item.icon
          const isActive = item.isActive(path)

          const content = (
            <>
              <Icon
                size={18}
                strokeWidth={isActive ? 2 : 1.8}
                className={isActive ? 'text-white' : 'text-neutral-500 transition-colors group-hover:text-neutral-300'}
              />
              <span className={`mt-1 text-[11px] font-medium ${isActive ? 'text-white' : 'text-neutral-500'}`}>
                {item.label}
              </span>
              {isActive && <span className="mt-1.5 h-[2px] w-[2px] rounded-[2px] bg-white" />}
            </>
          )

          if (item.url === null) {
            return (
              <button
                key={item.id}
                type="button"
                disabled
                title="Coming soon"
                className="relative flex flex-col items-center px-4 pb-2 opacity-40"
              >
                {content}
              </button>
            )
          }

          return (
            <Link
              key={item.id}
              href={item.url}
              aria-current={isActive ? 'page' : undefined}
              className="group relative flex flex-col items-center px-4 pb-2"
            >
              {content}
            </Link>
          )
        })}
      </div>
    </nav>
  )
}
