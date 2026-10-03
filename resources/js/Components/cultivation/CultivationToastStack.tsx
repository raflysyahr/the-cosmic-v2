import type { FC } from 'react'
import { Award, Sparkles } from 'lucide-react'
import { useCultivationToast } from '../../contexts/CultivationToastContext'

/**
 * Stack toast "+N Resource" / "+N CP" di pojok kiri bawah layar — background
 * transparan (bukan solid), teks hijau, auto-hilang sendiri (lihat
 * CultivationToastContext). Dirender sekali di Layout.tsx supaya
 * muncul di semua halaman.
 */
const CultivationToastStack: FC<{ className?: string }> = ({ className = 'bottom-4 left-4' }) => {
  const { gains } = useCultivationToast()


  if (gains.length === 0) return null

  return (
    <div className={`fixed ${className} z-[9999999] flex flex-col gap-2 pointer-events-none`}>
      {gains.map((gain) => {
        const isCp = gain.kind === 'cp'
        const tone = isCp ? 'text-amber-300' : 'text-green-400'
        const Icon = isCp ? Award : Sparkles
        return (
          <div
            key={gain.id}
            className="animate-cultivation-toast-in flex items-center gap-1.5 rounded-lg bg-black/30 px-3 py-1.5 backdrop-blur-sm"
          >
            <Icon className={`h-3.5 w-3.5 ${tone}`} />
 a           <span className={`font-label-md text-label-md font-semibold ${tone}`}>
              +{gain.amount} {gain.resourceName}
            </span>
            {gain.note && (
              <span className={`font-label-md text-label-md opacity-80 ${tone}`}>{gain.note}</span>
            )}
          </div>
        )
      })}
    </div>
  )
}

export default CultivationToastStack
