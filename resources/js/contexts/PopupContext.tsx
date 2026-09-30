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
}

interface PopupContextValue {
  popup: PopupConfig | null
  showPopup: (config: PopupConfig) => void
  closePopup: () => void
}

const PopupContext = createContext<PopupContextValue | null>(null)

export function PopupProvider({ children }: { children: ReactNode }) {
  const [popup, setPopup] = useState<PopupConfig | null>(null)

  const showPopup = useCallback((config: PopupConfig) => {
    setPopup(config)
  }, [])

  const closePopup = useCallback(() => {
    setPopup(null)
  }, [])

  return (
    <PopupContext.Provider value={{ popup, showPopup, closePopup }}>
      {children}
    </PopupContext.Provider>
  )
}

export function usePopup(): PopupContextValue {
  const ctx = useContext(PopupContext)
  if (!ctx) throw new Error('usePopup must be used within PopupProvider')
  return ctx
}
