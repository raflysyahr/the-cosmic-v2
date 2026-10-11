// Format teks pesan ala Telegram.
//
// `body` pesan tetap teks polos; formatnya disimpan terpisah sebagai daftar
// "entity" ({ type, offset, length, url? }) di `message.metadata.entities`.
// Offset/length dihitung dalam UTF-16 code unit — sama dengan indeks string JS,
// jadi cukup `text.slice(offset, offset + length)`. Backend memvalidasi ulang
// (MessageEntityService), tapi renderer tetap tidak percaya data dari server.
//
// File ini murni (tanpa React/DOM) supaya mudah diuji.

export type EntityType = 'bold' | 'italic' | 'underline' | 'strike' | 'code' | 'spoiler' | 'link'

export interface TextEntity {
  type: EntityType
  offset: number
  length: number
  url?: string
}

/** Urutan bungkus dari terluar ke terdalam saat dirender; juga urutan tie-break. */
export const ENTITY_ORDER: EntityType[] = ['link', 'spoiler', 'bold', 'italic', 'underline', 'strike', 'code']

const SAFE_PROTOCOLS = ['http:', 'https:', 'mailto:']
const MAX_URL_LENGTH = 2048
export const MAX_ENTITIES = 100

/** Bentuk minimal JSON ProseMirror/Tiptap yang kita butuhkan. */
export interface DocNode {
  type: string
  text?: string
  attrs?: Record<string, unknown>
  marks?: { type: string; attrs?: Record<string, unknown> }[]
  content?: DocNode[]
}

function isEntityType(value: unknown): value is EntityType {
  return typeof value === 'string' && (ENTITY_ORDER as string[]).includes(value)
}

/** Hanya http(s) dan mailto. Mengembalikan URL bersih atau null. */
export function safeUrl(raw: unknown): string | null {
  if (typeof raw !== 'string') return null
  const url = raw.trim()
  if (!url || url.length > MAX_URL_LENGTH || /[\u0000-\u0020\u007f]/.test(url)) return null
  try {
    const parsed = new URL(url)
    if (!SAFE_PROTOCOLS.includes(parsed.protocol)) return null
    if (parsed.protocol !== 'mailto:' && !parsed.hostname) return null
    return url
  } catch {
    return null
  }
}

/** Menambahkan https:// bila pengguna mengetik "example.com". */
export function normalizeLinkInput(raw: string): string | null {
  const value = raw.trim()
  if (!value) return null
  if (/^[a-z][a-z0-9+.-]*:/i.test(value)) return safeUrl(value)
  return safeUrl(`https://${value}`)
}

function entityKey(e: TextEntity): string {
  return e.type + '\u0001' + (e.url ?? '')
}

function compareEntities(a: TextEntity, b: TextEntity): number {
  return a.offset - b.offset || b.length - a.length || ENTITY_ORDER.indexOf(a.type) - ENTITY_ORDER.indexOf(b.type)
}

/** Urut + gabungkan entity sejenis yang bersentuhan/tumpang tindih. Selalu objek baru. */
export function canonicalize(list: TextEntity[]): TextEntity[] {
  const sorted = list
    .map((e): TextEntity => (e.url !== undefined ? { type: e.type, offset: e.offset, length: e.length, url: e.url } : { type: e.type, offset: e.offset, length: e.length }))
    .sort(compareEntities)
  const merged: TextEntity[] = []
  const lastByKey = new Map<string, TextEntity>()
  for (const e of sorted) {
    const last = lastByKey.get(entityKey(e))
    if (last && last.offset + last.length >= e.offset) {
      last.length = Math.max(last.length, e.offset + e.length - last.offset)
    } else {
      merged.push(e)
      lastByKey.set(entityKey(e), e)
    }
  }
  return merged.sort(compareEntities)
}

export function entitiesEqual(a: TextEntity[], b: TextEntity[]): boolean {
  return JSON.stringify(canonicalize(a)) === JSON.stringify(canonicalize(b))
}

/** Membersihkan data tak tepercaya (mis. dari server) terhadap `text`. */
export function sanitizeEntities(text: string, raw: unknown): TextEntity[] {
  if (!text || !Array.isArray(raw)) return []
  const out: TextEntity[] = []
  for (const item of raw) {
    if (!item || typeof item !== 'object') continue
    const e = item as Record<string, unknown>
    if (!isEntityType(e.type)) continue
    const offset = e.offset
    const length = e.length
    if (typeof offset !== 'number' || typeof length !== 'number') continue
    if (!Number.isInteger(offset) || !Number.isInteger(length)) continue
    if (offset < 0 || length < 1 || offset + length > text.length) continue
    if (e.type === 'link') {
      const url = safeUrl(e.url)
      if (!url) continue
      out.push({ type: 'link', offset, length, url })
    } else {
      out.push({ type: e.type, offset, length })
    }
    if (out.length >= MAX_ENTITIES) break
  }
  return out
}

/** Tiptap doc → teks polos + entity. Paragraf dan hardBreak sama-sama jadi "\n". */
export function serializeDoc(doc: DocNode): { text: string; entities: TextEntity[] } {
  let text = ''
  const entities: TextEntity[] = []
  const lastByKey = new Map<string, TextEntity>()

  const append = (chunk: string, marks: DocNode['marks']) => {
    if (!chunk) return
    const start = text.length
    for (const mark of marks ?? []) {
      if (!isEntityType(mark.type)) continue
      let url: string | undefined
      if (mark.type === 'link') {
        const safe = safeUrl(mark.attrs?.href)
        if (!safe) continue
        url = safe
      }
      const probe: TextEntity = url ? { type: mark.type, offset: start, length: chunk.length, url } : { type: mark.type, offset: start, length: chunk.length }
      const key = entityKey(probe)
      const last = lastByKey.get(key)
      if (last && last.offset + last.length === start) {
        last.length += chunk.length
      } else {
        entities.push(probe)
        lastByKey.set(key, probe)
      }
    }
    text += chunk
  }

  ;(doc.content ?? []).forEach((block, index) => {
    if (index > 0) text += '\n'
    for (const node of block.content ?? []) {
      if (node.type === 'text') append(node.text ?? '', node.marks)
      else if (node.type === 'hardBreak') append('\n', node.marks)
    }
  })

  return { text, entities }
}

/** Buang spasi/baris kosong di tepi (offset ikut digeser) dan entity yang jadi kosong. */
export function trimDraft(text: string, entities: TextEntity[]): { text: string; entities: TextEntity[] } {
  const lead = text.length - text.trimStart().length
  const trimmed = text.trim()
  const out: TextEntity[] = []
  for (const e of entities) {
    const start = Math.max(e.offset - lead, 0)
    const end = Math.min(e.offset + e.length - lead, trimmed.length)
    if (end <= start || !trimmed.slice(start, end).trim()) continue
    out.push({ ...e, offset: start, length: end - start })
  }
  return { text: trimmed, entities: canonicalize(out) }
}

/** Teks polos + entity → Tiptap doc (satu paragraf, baris baru jadi hardBreak). */
export function docFromText(text: string, raw: unknown): DocNode {
  const entities = sanitizeEntities(text, raw)
  const cuts = new Set<number>([0, text.length])
  for (const e of entities) {
    cuts.add(e.offset)
    cuts.add(e.offset + e.length)
  }
  for (let i = 0; i < text.length; i++) {
    if (text[i] === '\n') {
      cuts.add(i)
      cuts.add(i + 1)
    }
  }
  const points = [...cuts].sort((a, b) => a - b)
  const nodes: DocNode[] = []
  for (let i = 0; i < points.length - 1; i++) {
    const from = points[i]
    const to = points[i + 1]
    if (to <= from) continue
    const chunk = text.slice(from, to)
    const marks = entities
      .filter((e) => e.offset <= from && e.offset + e.length >= to)
      .map((e) => (e.type === 'link' ? { type: 'link', attrs: { href: e.url } } : { type: e.type }))
    if (chunk === '\n') nodes.push(marks.length ? { type: 'hardBreak', marks } : { type: 'hardBreak' })
    else nodes.push(marks.length ? { type: 'text', text: chunk, marks } : { type: 'text', text: chunk })
  }
  return { type: 'doc', content: [nodes.length ? { type: 'paragraph', content: nodes } : { type: 'paragraph' }] }
}

export interface Segment {
  text: string
  /** Indeks ke array entity yang menutupi potongan ini. */
  active: number[]
}

/** Potong teks di setiap batas entity; tiap potongan tahu entity apa saja yang aktif. */
export function buildSegments(text: string, entities: TextEntity[]): Segment[] {
  const cuts = new Set<number>([0, text.length])
  for (const e of entities) {
    cuts.add(e.offset)
    cuts.add(e.offset + e.length)
  }
  const points = [...cuts].sort((a, b) => a - b)
  const segments: Segment[] = []
  for (let i = 0; i < points.length - 1; i++) {
    const from = points[i]
    const to = points[i + 1]
    if (to <= from) continue
    const active: number[] = []
    entities.forEach((e, idx) => {
      if (e.offset <= from && e.offset + e.length >= to) active.push(idx)
    })
    segments.push({ text: text.slice(from, to), active })
  }
  return segments
}
