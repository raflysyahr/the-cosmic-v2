/** Tipe & helper bersama untuk halaman Story dan panel admin-nya. */

export interface StoryMedia {
  /** Id item (stabil) — dipakai saat edit untuk memilih item lama yang dipertahankan. */
  id: string
  type: 'image' | 'video'
  url: string
  /** Thumbnail ringan untuk feed (foto: JPEG ≤1080px; video: poster). Foto GIF memakai file asli. */
  thumbnail: string | null
  duration: number | null
  width: number | null
  height: number | null
}

/** Jumlah maksimal foto/video per post (sinkron dengan AnnouncementRequest::MAX_MEDIA). */
export const STORY_MAX_MEDIA = 10

export interface StoryReactionCount {
  emoji: string
  count: number
}

export interface StoryPost {
  id: string
  title: string
  body: string
  link_url: string | null
  is_pinned: boolean
  published_at: string | null
  is_published?: boolean
  is_new?: boolean
  /** Carousel, berurutan. Kosong = post teks. */
  media: StoryMedia[]
  reactions: StoryReactionCount[]
  my_reaction: string | null
  reaction_total: number
}

/** Harus sama dengan AnnouncementReaction::PALETTE di backend. */
export const REACTION_PALETTE = ['❤️', '👍', '😂', '😮', '😢', '🔥'] as const

/**
 * Hitung tampilan reaksi setelah user menekan `emoji` (optimistic update).
 * Aturannya sama dengan server: satu reaksi per user; emoji yang sama = dicabut,
 * emoji lain = mengganti.
 */
export function applyReaction(post: StoryPost, emoji: string): StoryPost {
  const counts = new Map(post.reactions.map((r) => [r.emoji, r.count]))
  const previous = post.my_reaction

  if (previous) counts.set(previous, Math.max(0, (counts.get(previous) ?? 1) - 1))

  const next = previous === emoji ? null : emoji
  if (next) counts.set(next, (counts.get(next) ?? 0) + 1)

  const reactions = REACTION_PALETTE.filter((e) => (counts.get(e) ?? 0) > 0).map((e) => ({
    emoji: e,
    count: counts.get(e) as number,
  }))

  return {
    ...post,
    reactions,
    my_reaction: next,
    reaction_total: reactions.reduce((sum, r) => sum + r.count, 0),
  }
}

export const formatStoryDate = (iso: string | null): string =>
  iso ? new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' }) : ''

export const formatStoryDateTime = (iso: string | null): string =>
  iso
    ? new Date(iso).toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })
    : ''
