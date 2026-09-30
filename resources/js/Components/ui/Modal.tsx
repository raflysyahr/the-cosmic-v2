import { useState, useEffect } from 'react'
import { X } from 'lucide-react'
import { useModal, type ModalConfig, type ModalSize } from '../../contexts/ModalContext'

const sizeClass: Record<ModalSize, string> = {
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-lg',
  xl: 'max-w-2xl',
  full: 'max-w-[95vw] h-[90vh]',
}

function ModalRenderer({ config, onClose }: { config: ModalConfig; onClose: () => void }) {
  const [visible, setVisible] = useState(false)
  const dismissible = config.dismissible ?? true

  useEffect(() => {
    const t = requestAnimationFrame(() => setVisible(true))
    return () => cancelAnimationFrame(t)
  }, [])

  const handleClose = () => {
    if (!dismissible) return
    setVisible(false)
    setTimeout(onClose, 200)
  }

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') handleClose()
    }
    document.addEventListener('keydown', onKey)
    return () => document.removeEventListener('keydown', onKey)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [dismissible])

  return (
    <div
      className={`fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm transition-opacity duration-200 ${visible ? 'opacity-100' : 'opacity-0'}`}
      onClick={(e) => {
        if (e.target === e.currentTarget) handleClose()
      }}
    >
      <div
        className={`relative mx-4 w-[90%] ${sizeClass[config.size ?? 'md']} shadow-2xl transition-all duration-200 ${visible ? 'scale-100 opacity-100' : 'scale-95 opacity-0'} ${
          config.bare
            ? 'overflow-hidden rounded-2xl'
            : `rounded-full border border-[#2A2A2A] bg-[#141212] py-9 ${config.size === 'full' ? 'overflow-auto' : ''}`
        }`}
      >
        {!config.hideCloseButton && (
          <button
            onClick={handleClose}
            className="absolute right-3 top-3 z-30 text-[#555] transition-colors hover:text-white"
          >
            <X className="h-4 w-4" />
          </button>
        )}

        {config.content}
      </div>
    </div>
  )
}

/**
 * Dirender sekali di Layout.tsx (atau langsung di halaman yang tidak
 * pakai <Layout>, mis. Reader.tsx / Room.tsx — lihat catatan yang sama
 * untuk CultivationToastStack). Tidak render apa pun kalau tidak ada
 * modal aktif.
 */
export default function ModalHost() {
  const { modal, closeModal } = useModal()
  if (!modal) return null
  return <ModalRenderer config={modal} onClose={closeModal} />
}
