const STORAGE_KEY = 'cosmic_reading_history_rich'
const MAX_ENTRIES = 50

export interface HistoryEntry {
  slug: string
  title: string
  coverImage: string
  chapterIndex: number
  chapterTitle: string
  chapterUrl: string
  timestamp: number
}

function load(): HistoryEntry[] {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? JSON.parse(raw) : []
  } catch {
    return []
  }
}

function save(entries: HistoryEntry[]) {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(entries))
}

export function getRichHistory(): HistoryEntry[] {
  return load()
}

export function saveRichHistory(entry: Omit<HistoryEntry, 'timestamp'>) {
  let entries = load()
  const existingIdx = entries.findIndex((e) => e.slug === entry.slug)

  const newEntry: HistoryEntry = { ...entry, timestamp: Date.now() }

  if (existingIdx !== -1) {
    entries[existingIdx] = newEntry
  } else {
    entries.unshift(newEntry)
  }

  if (entries.length > MAX_ENTRIES) {
    entries = entries.slice(0, MAX_ENTRIES)
  }

  save(entries)
}

export function clearRichHistory() {
  localStorage.removeItem(STORAGE_KEY)
}
