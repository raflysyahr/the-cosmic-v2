import { useEffect, useLayoutEffect, useRef, useState, type FC } from 'react'
import { Reply, Edit3, Pin, PinOff, Trash2, Save, Lightbulb, BadgeCheck, Flag } from 'lucide-react'

export interface MessageActionMenuProps {
  x: number
  y: number
  align: 'left' | 'right'
  canEdit: boolean
  canPin: boolean
  isPinned: boolean
  // File messages that have already been downloaded — offers a quick "Save"
  // (re-saves from the local cache, no re-download) from the long-press menu.
  canSave?: boolean
  // Helpful: member lain (bukan penulis). Best Answer: hanya reply, oleh
  // penanya atau moderator/admin — aturan sebenarnya dijaga server.
  canHelpful?: boolean
  isHelpful?: boolean
  canBestAnswer?: boolean
  isBestAnswer?: boolean
  canReport?: boolean
  emotes: { id: string; code: string; image_url: string | null; unicode: string | null }[]
  onReply: () => void
  onEdit: () => void
  onDelete: () => void
  onPin: () => void
  onSave?: () => void
  onHelpful?: () => void
  onBestAnswer?: () => void
  onReport?: () => void
  onReact: (emoteId: string) => void
  onClose: () => void
}

// Lebar tetap menu (samakan dengan class w-56 di bawah = 14rem). Dipakai
// sebagai estimasi posisi awal SEBELUM menu sempat diukur — supaya tidak
// ada kedipan di posisi salah pada frame pertama.
const MENU_WIDTH_ESTIMATE = 224
const VIEWPORT_MARGIN = 8

/**
 * Popup melayang ala Telegram: muncul di atas/bawah pesan yang di-tap
 * (long-press di mobile, right-click di desktop), berisi strip emoji
 * quick-react di baris atas dan daftar aksi (reply/edit/pin/delete) di
 * bawahnya. Menutup diri sendiri saat klik di luar, tekan Escape, atau
 * setelah salah satu aksi dipilih.
 */
const MessageActionMenu: FC<MessageActionMenuProps> = ({
  x, y, align, canEdit, canPin, isPinned, canSave, canHelpful, isHelpful, canBestAnswer, isBestAnswer, canReport, emotes, onReply, onEdit, onDelete, onPin, onSave, onHelpful, onBestAnswer, onReport, onReact, onClose,
}) => {
  const ref = useRef<HTMLDivElement>(null)

  // Estimasi posisi awal (sebelum menu ke-render & terukur) — masih pakai
  // logika align lama supaya tetap masuk akal di frame pertama.
  const [pos, setPos] = useState(() => ({
    top: y,
    left: align === 'left' ? x : x - MENU_WIDTH_ESTIMATE,
  }))

  // Setelah menu ke-render, ukur DIMENSI ASLINYA (bukan tebakan) lalu clamp
  // ke dalam viewport dengan margin — ini yang memperbaiki menu kepotong di
  // luar layar. useLayoutEffect (bukan useEffect) supaya koreksi ini terjadi
  // SEBELUM browser sempat paint, jadi tidak ada kedipan posisi.
  useLayoutEffect(() => {
    const el = ref.current
    if (!el) return
    const { width, height } = el.getBoundingClientRect()

    let top = y
    let left = align === 'left' ? x : x - width

    // Kalau menu dibuka dekat tepi bawah layar dan tidak muat ke bawah,
    // buka ke ATAS dari titik tap sebagai gantinya.
    if (top + height > window.innerHeight - VIEWPORT_MARGIN) {
      top = y - height
    }

    // Clamp terakhir di keempat sisi — jaring pengaman kalau kasus di atas
    // masih belum cukup (mis. layar sangat pendek, atau tap terjadi persis
    // di pojok).
    top = Math.max(VIEWPORT_MARGIN, Math.min(top, window.innerHeight - height - VIEWPORT_MARGIN))
    left = Math.max(VIEWPORT_MARGIN, Math.min(left, window.innerWidth - width - VIEWPORT_MARGIN))

    setPos({ top, left })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [x, y, align])

  useEffect(() => {
    const handleClick = (e: MouseEvent | TouchEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) onClose()
    }
    const handleKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose()
    }
    // Delay 1 tick supaya event yang MEMICU menu ini (long-press/right-click)
    // tidak langsung ke-capture sebagai "klik di luar" dan menutup menu
    // sebelum sempat dilihat.
    const t = setTimeout(() => {
      document.addEventListener('mousedown', handleClick)
      document.addEventListener('touchstart', handleClick)
    }, 0)
    document.addEventListener('keydown', handleKey)
    return () => {
      clearTimeout(t)
      document.removeEventListener('mousedown', handleClick)
      document.removeEventListener('touchstart', handleClick)
      document.removeEventListener('keydown', handleKey)
    }
  }, [onClose])

  const quickEmotes = emotes.slice(0, 6)

  return (
    <>
      {/* Overlay tipis — cuma untuk area tap-to-close di mobile, tidak menggelapkan layar */}
      <div className="fixed inset-0 z-40" />
      <div
        ref={ref}
        className="fixed z-50 w-56 animate-popup-menu rounded-xl border border-outline-variant bg-surface-container-high shadow-xl"
        style={{
          top: pos.top,
          left: pos.left,
        }}
      >
        {/* Quick react strip */}
        {quickEmotes.length > 0 && (
          <div className="flex items-center gap-1 border-b border-outline-variant px-2 py-2">
            {quickEmotes.map((e) => (
              <button
                key={e.id}
                onClick={() => { onReact(e.id); onClose() }}
                className="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full transition-transform hover:scale-110 active:scale-95"
                title={e.code}
              >
                {e.unicode ? (
                  <span className="text-lg leading-none">{e.unicode}</span>
                ) : e.image_url ? (
                  <img src={e.image_url} alt={e.code} className="h-full w-full object-contain" />
                ) : (
                  <span className="text-[10px]">{e.code}</span>
                )}
              </button>
            ))}
          </div>
        )}

        {/* Action list */}
        <div className="flex flex-col py-1">
          <button
            onClick={() => { onReply(); onClose() }}
            className="flex items-center gap-3 px-4 py-2.5 text-left font-body-sm text-body-sm text-on-surface transition-colors hover:bg-surface-container-highest"
          >
            <Reply className="h-4 w-4 text-on-surface-variant" />
            Reply
          </button>

          {canHelpful && onHelpful && (
            <button
              onClick={() => { onHelpful(); onClose() }}
              className="flex items-center gap-3 px-4 py-2.5 text-left font-body-sm text-body-sm text-on-surface transition-colors hover:bg-surface-container-highest"
            >
              <Lightbulb className="h-4 w-4 text-on-surface-variant" />
              {isHelpful ? 'Remove Helpful' : 'Mark Helpful'}
            </button>
          )}

          {canBestAnswer && onBestAnswer && (
            <button
              onClick={() => { onBestAnswer(); onClose() }}
              className="flex items-center gap-3 px-4 py-2.5 text-left font-body-sm text-body-sm text-on-surface transition-colors hover:bg-surface-container-highest"
            >
              <BadgeCheck className="h-4 w-4 text-on-surface-variant" />
              {isBestAnswer ? 'Remove Best Answer' : 'Best Answer'}
            </button>
          )}

          {canReport && onReport && (
            <button
              onClick={() => { onReport(); onClose() }}
              className="flex items-center gap-3 px-4 py-2.5 text-left font-body-sm text-body-sm text-on-surface transition-colors hover:bg-surface-container-highest"
            >
              <Flag className="h-4 w-4 text-on-surface-variant" />
              Report
            </button>
          )}

          {canSave && onSave && (
            <button
              onClick={() => { onSave(); onClose() }}
              className="flex items-center gap-3 px-4 py-2.5 text-left font-body-sm text-body-sm text-on-surface transition-colors hover:bg-surface-container-highest"
            >
              <Save className="h-4 w-4 text-on-surface-variant" />
              Save
            </button>
          )}

          {canEdit && (
            <button
              onClick={() => { onEdit(); onClose() }}
              className="flex items-center gap-3 px-4 py-2.5 text-left font-body-sm text-body-sm text-on-surface transition-colors hover:bg-surface-container-highest"
            >
              <Edit3 className="h-4 w-4 text-on-surface-variant" />
              Edit
            </button>
          )}

          {canPin && (
            <button
              onClick={() => { onPin(); onClose() }}
              className="flex items-center gap-3 px-4 py-2.5 text-left font-body-sm text-body-sm text-on-surface transition-colors hover:bg-surface-container-highest"
            >
              {isPinned ? <PinOff className="h-4 w-4 text-on-surface-variant" /> : <Pin className="h-4 w-4 text-on-surface-variant" />}
              {isPinned ? 'Unpin' : 'Pin'}
            </button>
          )}

          <button
            onClick={() => { onDelete(); onClose() }}
            className="flex items-center gap-3 px-4 py-2.5 text-left font-body-sm text-body-sm text-error transition-colors hover:bg-surface-container-highest"
          >
            <Trash2 className="h-4 w-4" />
            Delete
          </button>
        </div>
      </div>
    </>
  )
}

export default MessageActionMenu
