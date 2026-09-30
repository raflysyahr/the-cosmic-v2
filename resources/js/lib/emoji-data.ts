import rawIndex from './emoji-index.json'

export interface EmojiEntry {
  component: string
  glyph: string
  unicode: string
  group: string
  keywords: string[]
  name: string
}

const groups = [
  'Smileys & Emotion',
  'People & Body',
  'Animals & Nature',
  'Food & Drink',
  'Travel & Places',
  'Activities',
  'Objects',
  'Symbols',
  'Flags',
] as const

export type EmojiGroup = (typeof groups)[number]

export const GROUP_LABELS: Record<EmojiGroup, { icon: string; label: string }> = {
  'Smileys & Emotion': { icon: 'mood', label: 'Smileys' },
  'People & Body': { icon: 'accessibility', label: 'People' },
  'Animals & Nature': { icon: 'pets', label: 'Animals' },
  'Food & Drink': { icon: 'restaurant', label: 'Food' },
  'Travel & Places': { icon: 'flight', label: 'Travel' },
  'Activities': { icon: 'sports_tennis', label: 'Activities' },
  'Objects': { icon: 'lightbulb', label: 'Objects' },
  'Symbols': { icon: 'favorite', label: 'Symbols' },
  'Flags': { icon: 'flag', label: 'Flags' },
}

export const emojiList: EmojiEntry[] = (rawIndex as any[]).map((e) => ({
  component: e.c,
  glyph: e.g,
  unicode: e.u,
  group: e.gr,
  keywords: e.k,
  name: e.n,
}))

export function getEmojiByGroup(group: EmojiGroup): EmojiEntry[] {
  return emojiList.filter((e) => e.group === group)
}

export function searchEmoji(query: string): EmojiEntry[] {
  const q = query.toLowerCase()
  return emojiList.filter(
    (e) =>
      e.name.toLowerCase().includes(q) ||
      e.keywords.some((k) => k.toLowerCase().includes(q)),
  )
}
