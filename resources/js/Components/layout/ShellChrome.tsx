import { createContext, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'

/**
 * Pengaturan "chrome" (header + navigasi bawah) milik shell Discuss.
 *
 * Secara default shell menentukan semuanya dari URL (lihat shellRoutes.ts).
 * Halaman hanya perlu memanggil useShellChrome() bila butuh menimpa default,
 * misalnya judul dinamis, atau sub-halaman yang bukan route (Profile → Edit).
 */
export interface ShellChrome {
  /** Paksa navigasi bawah disembunyikan (tampil mode "detail" dengan tombol back). */
  hideNav?: boolean
  /** Judul di header mode detail. */
  title?: string
  /** Aksi tombol back. Default: naik ke halaman induk (lihat shellRoutes.ts). */
  onBack?: () => void
  /**
   * Slot kanan di header mode detail (mis. menu titik tiga). Elemen harus
   * stabil antar render (useMemo) dan memegang state-nya sendiri.
   */
  right?: ReactNode
}

interface ShellChromeContextValue {
  chrome: ShellChrome
  setChrome: (chrome: ShellChrome) => void
}

const ShellChromeContext = createContext<ShellChromeContextValue>({
  chrome: {},
  setChrome: () => {},
})

export function ShellChromeProvider({ children }: { children: ReactNode }) {
  const [chrome, setChrome] = useState<ShellChrome>({})
  const value = useMemo(() => ({ chrome, setChrome }), [chrome])
  return <ShellChromeContext.Provider value={value}>{children}</ShellChromeContext.Provider>
}

export function useShellChromeState(): ShellChrome {
  return useContext(ShellChromeContext).chrome
}

/**
 * Menimpa chrome selama komponen pemanggil terpasang. Kirim `null` untuk
 * "tidak ada penimpaan" (hook tetap harus dipanggil tiap render).
 */
export function useShellChrome(override: ShellChrome | null) {
  const { setChrome } = useContext(ShellChromeContext)

  // onBack biasanya closure baru tiap render; simpan di ref supaya efek di
  // bawah tidak jalan ulang terus-menerus.
  const onBackRef = useRef(override?.onBack)
  onBackRef.current = override?.onBack

  const active = override !== null
  const hideNav = override?.hideNav
  const title = override?.title
  const hasOnBack = Boolean(override?.onBack)
  const right = override?.right

  useEffect(() => {
    if (!active) return
    setChrome({
      hideNav,
      title,
      onBack: hasOnBack ? () => onBackRef.current?.() : undefined,
      right,
    })
    return () => setChrome({})
  }, [active, hideNav, title, hasOnBack, right, setChrome])
}
