// Teks keterangan untuk toast "+N CP" (lihat CultivationToastContext.showCp).
// Dipakai Layout.tsx dan LayoutDiscuss.tsx supaya labelnya satu sumber.

export interface ContributionAwardedPayload {
  amount: number
  source?: string
  multiplier?: number
  event_name?: string | null
  // Keterangan tambahan dari server, mis. nama achievement.
  detail?: string | null
}

// Hanya sumber yang datang "tiba-tiba" (bukan akibat aksi user sendiri) yang
// perlu label, supaya user paham dari mana poinnya.
const SOURCE_LABELS: Record<string, string> = {
  helpful: 'Helpful',
  best_answer: 'Best Answer',
  daily_bonus: 'Daily bonus',
  streak: 'Streak bonus',
  report_valid: 'Valid report',
}

export function cpToastNote(e: ContributionAwardedPayload): string | undefined {
  const parts: string[] = []

  const label = e.source === 'achievement'
    ? `Achievement${e.detail ? `: ${e.detail}` : ''}`
    : e.source ? SOURCE_LABELS[e.source] : undefined
  if (label) parts.push(label)

  if (e.multiplier && e.multiplier > 1) {
    parts.push(`x${e.multiplier}${e.event_name ? ` ${e.event_name}` : ''}`)
  }

  return parts.length > 0 ? parts.join(' · ') : undefined
}
