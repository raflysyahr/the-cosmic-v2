import type { FC } from 'react'
import { BadgeCheck, Lightbulb } from 'lucide-react'

export interface MessageMarks {
  helpful_count: number
  helpful_user_ids: string[]
  is_best_answer: boolean
}

interface MarkBadgesProps {
  marks?: MessageMarks
  currentUserId: string
}

/**
 * Lencana kecil di bawah bubble: "Best Answer" dan jumlah "Helpful".
 * Tidak render apa pun kalau pesan belum punya tanda.
 */
const MarkBadges: FC<MarkBadgesProps> = ({ marks, currentUserId }) => {
  if (!marks || (!marks.is_best_answer && marks.helpful_count === 0)) return null

  const helpfulByMe = marks.helpful_user_ids.includes(currentUserId)

  return (
    <div className="mt-1 flex flex-wrap items-center gap-1.5">
      {marks.is_best_answer && (
        <span className="inline-flex items-center gap-1 rounded-full bg-amber-400/15 px-2 py-0.5 font-label-sm text-label-sm font-semibold text-amber-300">
          <BadgeCheck className="h-3 w-3" />
          Best Answer
        </span>
      )}
      {marks.helpful_count > 0 && (
        <span
          className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-label-sm text-label-sm ${
            helpfulByMe
              ? 'bg-amber-400/15 text-amber-300'
              : 'bg-surface-container-high text-on-surface-variant'
          }`}
        >
          <Lightbulb className="h-3 w-3" />
          {marks.helpful_count} Helpful
        </span>
      )}
    </div>
  )
}

export default MarkBadges
