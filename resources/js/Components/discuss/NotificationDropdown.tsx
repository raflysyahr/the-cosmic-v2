import { useState, useEffect, useRef, useCallback, type FC } from 'react'
import { Bell } from 'lucide-react'
import { apiFetch } from '../../api/fetch'

interface NotificationItem {
  id: string
  type: string
  actor?: { display_name: string; avatar_url: string | null }
  payload: Record<string, unknown>
  is_read: boolean
  created_at: string
}

const NotificationDropdown: FC = () => {
  const [open, setOpen] = useState(false)
  const [unread, setUnread] = useState(0)
  const [items, setItems] = useState<NotificationItem[]>([])
  const ref = useRef<HTMLDivElement>(null)

  const fetchUnread = useCallback(() => {
    apiFetch('/api/notifications')
      .then((r) => { if (!r.ok) throw new Error('fetch failed'); return r.json() })
      .then((data: NotificationItem[]) => {
        setItems(data.slice(0, 5))
        setUnread(data.filter((n) => !n.is_read).length)
      })
      .catch(() => {})
  }, [])

  useEffect(() => {
    fetchUnread()
    const interval = setInterval(fetchUnread, 30000)
    return () => clearInterval(interval)
  }, [fetchUnread])

  useEffect(() => {
    if (!open) return
    const handler = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) {
        setOpen(false)
      }
    }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [open])

  const markAllRead = () => {
    apiFetch('/api/notifications/read-all', { method: 'POST' })
      .then((r) => { if (!r.ok) throw new Error('mark read failed') })
      .then(() => {
        setUnread(0)
        setItems((prev) => prev.map((n) => ({ ...n, is_read: true })))
      })
      .catch(() => {})
  }

  const formatTime = (iso: string) => {
    const d = new Date(iso)
    const now = new Date()
    const diff = Math.floor((now.getTime() - d.getTime()) / 1000)
    if (diff < 60) return 'just now'
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
  }

  return (
    <div ref={ref} className="relative">
      <button
        onClick={() => setOpen((p) => !p)}
        className="relative transition-colors hover:text-white"
      >
        <Bell size={18} className="text-[#555]" />
        {unread > 0 && (
          <span className="absolute -right-1 -top-1 flex h-2 w-2 rounded-full bg-white" />
        )}
      </button>

      {open && (
        <div className="absolute right-0 top-full z-50 mt-2 w-72 border border-[#2A2A2A] bg-[#111] shadow-lg">
          {items.length === 0 ? (
            <p className="px-4 py-6 text-center text-xs text-[#555]">No notifications</p>
          ) : (
            <>
              {items.map((n) => (
                <div
                  key={n.id}
                  className={`border-b border-[#2A2A2A] px-4 py-3 text-xs transition-colors hover:bg-[#1A1A1A] ${
                    n.is_read ? 'text-[#555]' : 'text-[#777]'
                  }`}
                >
                  <div className="flex items-start gap-2">
                    {n.actor?.avatar_url && (
                      <img src={n.actor.avatar_url} alt="" className="mt-0.5 h-5 w-5 rounded-full object-cover" />
                    )}
                    <div className="min-w-0 flex-1">
                      <p className="truncate">
                        {n.actor?.display_name && (
                          <span className="font-medium text-white">{n.actor.display_name} </span>
                        )}
                        {n.type.replace(/_/g, ' ')}
                      </p>
                      <p className="mt-0.5 text-[10px] text-[#555]">{formatTime(n.created_at)}</p>
                    </div>
                  </div>
                </div>
              ))}
              <button
                onClick={markAllRead}
                className="block w-full px-4 py-2 text-left text-[10px] text-[#555] transition-colors hover:text-white"
              >
                Mark all read
              </button>
            </>
          )}
        </div>
      )}
    </div>
  )
}

export default NotificationDropdown
