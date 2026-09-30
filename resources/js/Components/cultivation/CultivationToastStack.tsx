import type { FC } from 'react'
import { Sparkles } from 'lucide-react'
import { useCultivationToast } from '../../contexts/CultivationToastContext'

/**
 * Stack toast "+N Resource" di pojok kiri bawah layar — background
 * transparan (bukan solid), teks hijau, auto-hilang sendiri (lihat
 * CultivationToastContext). Dirender sekali di Layout.tsx supaya
 * muncul di semua halaman.
 */
const CultivationToastStack: FC = () => {
  const { gains } = useCultivationToast()

  if (gains.length === 0) return null

  return (
    <div className="fixed bottom-4 left-4 z-[60] flex flex-col gap-2 pointer-events-none">
      {gains.map((gain) => (
        <div
          key={gain.id}
          className="animate-cultivation-toast-in flex items-center gap-1.5 rounded-lg bg-black/30 px-3 py-1.5 backdrop-blur-sm"
        >
          <Sparkles className="h-3.5 w-3.5 text-green-400" />
          <span className="font-label-md text-label-md font-semibold text-green-400">
            +{gain.amount} {gain.resourceName}
          </span>
        </div>
      ))}
    </div>
  )
}

export default CultivationToastStack
