import { useMemo, type FC, type ReactNode } from 'react'
import * as FluentEmoji from 'react-fluentui-emoji/lib/flat'
import emojiIndex from './emoji-index.json'

const glyphToComponent = new Map<string, string>()
const componentCache = new Map<string, ReturnType<typeof loadComponent>>()

function loadComponent(name: string) {
  return (FluentEmoji as Record<string, FC<{ size?: number | string; className?: string }>>)[name]
}

function normalize(str: string): string {
  return str.replace(/\uFE0F/g, '')
}

for (const entry of emojiIndex as Array<{ c: string; g: string }>) {
  glyphToComponent.set(normalize(entry.g), entry.c)
}

function getComponent(glyph: string) {
  const key = normalize(glyph)
  const name = glyphToComponent.get(key)
  if (!name) return null
  let comp = componentCache.get(name)
  if (comp === undefined) {
    comp = loadComponent(name) ?? null
    componentCache.set(name, comp)
  }
  return comp
}

const emojiRegex = /(\p{Extended_Pictographic}(?:\uFE0F?\u200D\p{Extended_Pictographic})*|\p{RI}{2}|\d\uFE0F\u20E3|\p{Emoji_Presentation})/gu

interface Segment {
  type: 'text' | 'emoji'
  value: string
}

function parseEmoji(text: string): Segment[] {
  const parts: Segment[] = []
  let lastIndex = 0
  let match: RegExpExecArray | null

  emojiRegex.lastIndex = 0
  while ((match = emojiRegex.exec(text)) !== null) {
    if (match.index > lastIndex) {
      parts.push({ type: 'text', value: text.slice(lastIndex, match.index) })
    }
    parts.push({ type: 'emoji', value: match[0] })
    lastIndex = match.index + match[0].length
  }
  if (lastIndex < text.length) {
    parts.push({ type: 'text', value: text.slice(lastIndex) })
  }
  return parts
}

interface EmojiTextProps {
  text: string
  size?: number | string
  className?: string
}

const EmojiText: FC<EmojiTextProps> = ({ text, size = '1.2em', className = '' }) => {
  if (!text) return null

  const parts = useMemo(() => parseEmoji(text), [text])

  return (
    <span className={className}>
      {parts.map((part, i) => {
        if (part.type === 'text') {
          return <span key={i}>{part.value}</span>
        }
        const Comp = getComponent(part.value)
        if (Comp) {
          return <Comp key={i} size={size} className="inline-block align-middle" />
        }
        return <span key={i}>{part.value}</span>
      })}
    </span>
  )
}

export default EmojiText
