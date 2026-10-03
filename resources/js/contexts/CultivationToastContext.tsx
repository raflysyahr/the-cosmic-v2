import { createContext, useContext, useState, useCallback, type ReactNode } from 'react'

export interface CultivationGain {
  id: string
  amount: number
  resourceName: string
  // 'cp' = Contribution Points dari Discuss. Berbagi antrian & tampilan
  // toast yang sama dengan XP Cultivation; bedanya hanya warna + catatan
  // multiplier event (mis. "x2 Weekend Boost").
  kind: 'cultivation' | 'cp'
  note?: string
}

interface CultivationToastContextValue {
  gains: CultivationGain[]
  showGain: (amount: number, resourceName: string) => void
  showCp: (amount: number, note?: string) => void
  dismissGain: (id: string) => void
}

const CultivationToastContext = createContext<CultivationToastContextValue | null>(null)

const AUTO_DISMISS_MS = 3500

/**
 * Antrian toast "+N Resource" (mis. "+10 Essence") yang dipicu dari mana
 * saja lewat useCultivationToast().showGain() — dipasang sekali di
 * app.jsx (global), dirender oleh CultivationToastStack di Layout
 * supaya muncul di semua halaman tanpa perlu setup ulang per-page.
 *
 * Beda dari PopupContext (1 slot, perlu klik OK): ini ANTRIAN (banyak
 * toast bisa numpuk & auto-hilang sendiri) karena +XP bisa terpicu
 * berkali-kali cepat berturut-turut (mis. baca beberapa chapter cepat).
 */
export function CultivationToastProvider({ children }: { children: ReactNode }) {
  const [gains, setGains] = useState<CultivationGain[]>([])

  const dismissGain = useCallback((id: string) => {
    setGains((prev) => prev.filter((g) => g.id !== id))
  }, [])

  const push = useCallback((gain: Omit<CultivationGain, 'id'>) => {
    const id = `${Date.now()}-${Math.random().toString(36).slice(2)}`
    setGains((prev) => [...prev, { id, ...gain }])
    setTimeout(() => dismissGain(id), AUTO_DISMISS_MS)
  }, [dismissGain])

  const showGain = useCallback((amount: number, resourceName: string) => {
    push({ amount, resourceName, kind: 'cultivation' })
  }, [push])

  const showCp = useCallback((amount: number, note?: string) => {
    push({ amount, resourceName: 'CP', kind: 'cp', note })
  }, [push])

  return (
    <CultivationToastContext.Provider value={{ gains, showGain, showCp, dismissGain }}>
      {children}
    </CultivationToastContext.Provider>
  )
}

export function useCultivationToast(): CultivationToastContextValue {
  const ctx = useContext(CultivationToastContext)
  if (!ctx) throw new Error('useCultivationToast must be used within CultivationToastProvider')
  return ctx
}
