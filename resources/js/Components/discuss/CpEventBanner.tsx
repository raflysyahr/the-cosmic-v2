import { useEffect, useState, type FC } from 'react'
import { Zap, X } from 'lucide-react'
import client from '../../api/client'

interface ActiveEvent {
  id: string
  name: string
  multiplier: number
  sources: string[] | null
  ends_at: string
}

function formatRemaining(endsAt: string): string {
  const ms = new Date(endsAt).getTime() - Date.now()
  if (ms <= 0) return 'ending now'
  const hours = Math.floor(ms / 3_600_000)
  if (hours >= 24) return `${Math.floor(hours / 24)}d left`
  if (hours >= 1) return `${hours}h left`
  return `${Math.max(1, Math.floor(ms / 60_000))}m left`
}

/**
 * Banner tipis "Double CP" saat ada event multiplier berjalan. Ditutup per
 * sesi (state saja, tidak disimpan) — muncul lagi di kunjungan berikutnya
 * selama event masih berjalan.
 */
const CpEventBanner: FC = () => {
  const [events, setEvents] = useState<ActiveEvent[]>([])
  const [dismissed, setDismissed] = useState(false)

  useEffect(() => {
    let cancelled = false
    client.get('/cp/active-events')
      .then((res) => { if (!cancelled) setEvents(res.data?.events ?? []) })
      .catch(() => { /* banner hanya tambahan; gagal = tidak tampil */ })
    return () => { cancelled = true }
  }, [])

  if (dismissed || events.length === 0) return null

  const best = events.reduce((a, b) => (b.multiplier > a.multiplier ? b : a))

  return (
    <div className="flex items-center gap-2 bg-amber-400/15 px-4 py-1.5 font-label-md text-label-md text-amber-300">
      <Zap className="h-3.5 w-3.5 shrink-0" />
      <span className="min-w-0 flex-1 truncate">
        x{best.multiplier} CP · {best.name} · {formatRemaining(best.ends_at)}
      </span>
      <button onClick={() => setDismissed(true)} aria-label="Dismiss" className="shrink-0 opacity-70 hover:opacity-100">
        <X className="h-3.5 w-3.5" />
      </button>
    </div>
  )
}

export default CpEventBanner
