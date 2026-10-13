import { Fragment, useMemo, useState, type FC, type MouseEvent, type ReactNode } from 'react'
import EmojiText from '../../lib/emoji-renderer'
import { buildSegments, sanitizeEntities, type EntityType, type Segment } from '../../lib/rich-text'

interface RichTextProps {
  text: string
  /** `message.metadata.entities` — tidak dipercaya, divalidasi ulang di sini. */
  entities?: unknown
  size?: number | string
  className?: string
  /**
   * Mode preview (daftar chat, kutipan reply): hanya spoiler yang dipakai —
   * tertutup dan tidak bisa diketuk — format lain tampil sebagai teks biasa,
   * dan baris baru tidak dipertahankan agar `truncate` tetap satu baris.
   */
  preview?: boolean
}

// Menampilkan teks pesan dengan format (bold, italic, spoiler, link, ...).
// Dirender sebagai elemen React biasa — tidak pernah lewat innerHTML.
const RichText: FC<RichTextProps> = ({ text, entities, size, className = '', preview = false }) => {
  const valid = useMemo(
    () => sanitizeEntities(text, entities).filter((e) => !preview || e.type === 'spoiler'),
    [text, entities, preview],
  )
  const segments = useMemo(() => buildSegments(text, valid), [text, valid])
  const [revealed, setRevealed] = useState<ReadonlySet<number>>(() => new Set())

  if (!text) return null

  const base = preview ? className : `whitespace-pre-wrap break-words ${className}`
  if (valid.length === 0) return <EmojiText text={text} size={size} className={base} />

  const find = (segment: Segment, type: EntityType): number | undefined =>
    segment.active.find((idx) => valid[idx].type === type)

  const renderSegment = (segment: Segment): ReactNode => {
    let node: ReactNode =
      find(segment, 'code') !== undefined ? (
        <code className="rich-code">{segment.text}</code>
      ) : (
        <EmojiText text={segment.text} size={size} />
      )

    if (find(segment, 'strike') !== undefined) node = <s>{node}</s>
    if (find(segment, 'underline') !== undefined) node = <u>{node}</u>
    if (find(segment, 'italic') !== undefined) node = <em>{node}</em>
    if (find(segment, 'bold') !== undefined) node = <strong>{node}</strong>

    const spoiler = find(segment, 'spoiler')
    if (spoiler !== undefined) {
      if (preview) {
        node = <span className="spoiler-text is-static">{node}</span>
        return node
      }
      const shown = revealed.has(spoiler)
      const reveal = (e: MouseEvent) => {
        // Spoiler di dalam link: ketukan pertama hanya membuka spoiler.
        e.preventDefault()
        e.stopPropagation()
        setRevealed((prev) => new Set(prev).add(spoiler))
      }
      node = (
        <span
          className={`spoiler-text${shown ? ' is-revealed' : ''}`}
          onClick={shown ? undefined : reveal}
          role={shown ? undefined : 'button'}
          aria-label={shown ? undefined : 'Spoiler, tap to reveal'}
        >
          {node}
        </span>
      )
    }

    const link = find(segment, 'link')
    if (link !== undefined) {
      node = (
        <a
          href={valid[link].url}
          target="_blank"
          rel="noopener noreferrer nofollow"
          className="rich-link"
          onClick={(e) => e.stopPropagation()}
        >
          {node}
        </a>
      )
    }

    return node
  }

  return (
    <span className={base}>
      {segments.map((segment, i) => (
        <Fragment key={i}>{renderSegment(segment)}</Fragment>
      ))}
    </span>
  )
}

export default RichText
