import type { FC, ReactNode } from 'react'
import { ChevronRight } from 'lucide-react'

type IconType = FC<{ className?: string }>

export const Card: FC<{ children: ReactNode }> = ({ children }) => (
  <section className="mx-4 mt-5 overflow-hidden rounded-[20px] border border-outline-variant/30 bg-surface-container-low">
    {children}
  </section>
)

interface InfoRowProps {
  icon: IconType
  label: string
  children: ReactNode
}

/** Baris informasi: ikon dalam kotak, label kecil, nilai di bawahnya. */
export const InfoRow: FC<InfoRowProps> = ({ icon: Icon, label, children }) => (
  <div className="flex items-center gap-4 border-b border-outline-variant/30 px-4 py-4 last:border-b-0">
    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-[20px] bg-surface-container-high text-on-surface-variant">
      <Icon className="h-5 w-5" />
    </div>
    <div className="min-w-0 flex-1">
      <p className="text-xs text-on-surface-variant">{label}</p>
      <div className="mt-0.5 break-words text-[15px] leading-snug text-white">{children}</div>
    </div>
  </div>
)

interface MenuRowProps {
  icon: IconType
  title: string
  subtitle: string
  onClick: () => void
}

/** Baris menu yang bisa ditekan, dengan chevron di kanan. */
export const MenuRow: FC<MenuRowProps> = ({ icon: Icon, title, subtitle, onClick }) => (
  <button
    type="button"
    onClick={onClick}
    className="flex w-full items-center gap-4 border-b border-outline-variant/30 px-4 py-4 text-left transition-colors last:border-b-0 hover:bg-surface-container"
  >
    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-[20px] bg-surface-container-high text-on-surface-variant">
      <Icon className="h-5 w-5" />
    </div>
    <div className="min-w-0 flex-1">
      <p className="text-[17px] font-semibold text-white">{title}</p>
      <p className="text-sm text-on-surface-variant">{subtitle}</p>
    </div>
    <ChevronRight className="h-5 w-5 shrink-0 text-on-surface-variant" />
  </button>
)

/**
 * Kerangka isi sub-halaman (Edit / Account / Storage). Tombol back dan judul
 * ada di header shell (lihat useShellChrome di Pages/Profile.tsx).
 */
export const PanelShell: FC<{ children: ReactNode }> = ({ children }) => (
  <div className="px-4 pb-8 pt-4">{children}</div>
)

export const primaryButton =
  'rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-on-primary transition-colors hover:bg-primary-container disabled:opacity-50'

export const subtleButton =
  'rounded-lg border border-outline-variant bg-transparent px-4 py-2.5 text-sm font-semibold text-on-surface-variant transition-colors hover:bg-surface-container-high hover:text-on-surface disabled:opacity-50'
