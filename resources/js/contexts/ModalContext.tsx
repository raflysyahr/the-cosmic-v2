import { createContext, useContext, useState, useCallback, type ReactNode } from 'react'

export type ModalSize = 'sm' | 'md' | 'lg' | 'xl' | 'full'

export interface ModalConfig {
  content: ReactNode
  size?: ModalSize
  /** Klik overlay / tombol X / tombol Escape menutup modal. Default true. */
  dismissible?: boolean
  /** Sembunyikan tombol X di pojok kanan atas. Default false (tombol X tampil). */
  hideCloseButton?: boolean
  /**
   * Lepas card dari chrome default (rounded-full, border, bg gelap, py-9).
   * Pakai ini kalau `content` sudah bawa background/border/frame sendiri
   * (mis. modal ber-tema gambar seperti Crest) supaya tidak ada styling
   * bawaan yang bentrok/menyisakan celah di sekitar frame custom.
   * Default false — perilaku modal lain tidak berubah.
   */
  bare?: boolean
  onClose?: () => void
}

interface ModalContextValue {
  modal: ModalConfig | null
  showModal: (config: ModalConfig) => void
  closeModal: () => void
}

const ModalContext = createContext<ModalContextValue | null>(null)

/**
 * Modal generik yang bisa diisi ELEMENT APA PUN lewat `content` — beda
 * dari PopupContext (struktur fixed: ikon+judul+pesan+tombol confirm/
 * cancel), ini murni FRAME KOSONG (overlay + card + optional tombol X).
 * Isinya sepenuhnya bebas: form, gambar, daftar, komponen custom, dst.
 *
 * Dipasang sekali di app.jsx (global) — dipanggil dari halaman manapun
 * lewat useModal().showModal({ content: <YourJSXHere /> }).
 */
export function ModalProvider({ children }: { children: ReactNode }) {
  const [modal, setModal] = useState<ModalConfig | null>(null)

  const showModal = useCallback((config: ModalConfig) => {
    setModal(config)
  }, [])

  const closeModal = useCallback(() => {
    setModal((current) => {
      current?.onClose?.()
      return null
    })
  }, [])

  return (
    <ModalContext.Provider value={{ modal, showModal, closeModal }}>
      {children}
    </ModalContext.Provider>
  )
}

export function useModal(): ModalContextValue {
  const ctx = useContext(ModalContext)
  if (!ctx) throw new Error('useModal must be used within ModalProvider')
  return ctx
}
