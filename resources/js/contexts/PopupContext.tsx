import { createContext, useContext, useState, useCallback, type ReactNode } from 'react'

export type PopupType = 'notification' | 'warning' | 'info' | 'confirm' | 'input'

export interface PopupConfig {
  type: PopupType
  title: string
  message?: string
  confirmText?: string
  cancelText?: string
  onConfirm?: (value?: string) => void
  onCancel?: () => void
  inputPlaceholder?: string
  inputDefaultValue?: string
  children?: ReactNode
  /**
   * Popup yang tidak bisa ditutup user: tanpa tombol X, klik backdrop diabaikan,
   * tombol aksi tidak menutup popup, dan popup lain tidak bisa menimpanya.
   * Hanya hilang lewat forceClosePopup() atau saat halaman di-reload.
   */
  persistent?: boolean
}

interface PopupContextValue {
  popup: PopupConfig | null
  showPopup: (config: PopupConfig) => void
  closePopup: () => void
  forceClosePopup: () => void
}

const PopupContext = createContext<PopupContextValue | null>(null)

export function PopupProvider({ children }: { children: ReactNode }) {
  const [popup, setPopup] = useState<PopupConfig | null>(null)

  // Popup persistent tidak boleh ditimpa popup lain (mis. pesan error biasa).
  const showPopup = useCallback((config: PopupConfig) => {
    setPopup((prev) => (prev?.persistent && !config.persistent ? prev : config))
  }, [])

  const closePopup = useCallback(() => {
    setPopup((prev) => (prev?.persistent ? prev : null))
  }, [])

  const forceClosePopup = useCallback(() => {
    setPopup(null)
  }, [])

  return (
    <PopupContext.Provider value={{ popup, showPopup, closePopup, forceClosePopup }}>
      {children}
    </PopupContext.Provider>
  )
}

export function usePopup(): PopupContextValue {
  const ctx = useContext(PopupContext)
  if (!ctx) throw new Error('usePopup must be used within PopupProvider')
  return ctx
}
