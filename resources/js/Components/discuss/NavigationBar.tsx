import { useEffect, useState, type ComponentType } from 'react'
import { Link, usePage } from '@inertiajs/react'
import { Megaphone, MessageCircle, Trophy, User , BookOpen} from 'lucide-react'
import client from '../../api/client'

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
    icon: Megaphone,
    url: '/discuss/story',
    isActive: (path) => path === '/discuss/story',
  },
  {
    id:'comic',
    label:'Comic',
    icon:BookOpen,
    url:'/',
    isActive:(path) => path === '/'
  },
  {
    id: 'leaderboard',
    label: 'Leaderboard',
    icon: Trophy,
    url: '/discuss/leaderboard',
    isActive: (path) => path === '/discuss/leaderboard',
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

  // Jumlah pemberitahuan Story yang belum dibaca. Diambil ulang setiap
  // pindah halaman (murah: satu COUNT) dan dikosongkan seketika saat halaman
  // Story menandai sudah dibaca ('story:seen').
  const [unread, setUnread] = useState(0)

  useEffect(() => {
    let cancelled = false
    client.get('/discuss/story/unread')
      .then((res) => { if (!cancelled) setUnread(Number(res.data?.unread) || 0) })
      .catch(() => { /* badge hanya tambahan */ })
    return () => { cancelled = true }
  }, [path])

  useEffect(() => {
    const clear = () => setUnread(0)
    window.addEventListener('story:seen', clear)
    return () => window.removeEventListener('story:seen', clear)
  }, [])

  return (
    <nav className="shrink-0 border-t border-neutral-900 bg-black/95 px-3 pt-2.5 pb-[max(0.625rem,env(safe-area-inset-bottom))] backdrop-blur-md">
      <div className="flex items-center justify-between">
        {ITEMS.map((item) => {
          const Icon = item.icon
          const isActive = item.isActive(path)

          const content = (
            <>
              <span className="relative">
                <Icon
                  size={18}
                  strokeWidth={isActive ? 2 : 1.8}
                  className={isActive ? 'text-white' : 'text-neutral-500 transition-colors group-hover:text-neutral-300'}
                />
                {item.id === 'story' && unread > 0 && (
                  <span className="absolute -right-2 -top-1.5 flex h-3.5 min-w-3.5 items-center justify-center rounded-[999px] bg-white px-1 text-[9px] font-bold leading-none text-black">
                    {unread > 9 ? '9+' : unread}
                  </span>
                )}
              </span>
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
