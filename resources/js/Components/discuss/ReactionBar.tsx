import { type FC } from 'react'

export interface GroupedReaction {
  emoteId: string
  emoteCode: string
  imageUrl: string | null
  unicode: string | null
  count: number
  userIds: string[]
}

interface ReactionBarProps {
  reactions: GroupedReaction[]
  onToggle: (emoteId: string) => void
  currentUserId: string
  reactionAnimations: Record<string, 'pop' | 'bump'>
  messageId: string
  onAnimationEnd: (msgId: string, emoteId: string) => void
}

const ReactionBar: FC<ReactionBarProps> = ({
  reactions, onToggle, currentUserId,
  reactionAnimations, messageId, onAnimationEnd,
}) => {
  if (reactions.length === 0) return null

  return (
    <div className="flex flex-wrap gap-1">
      {reactions.map((r) => {
        const isReacted = (r.userIds ?? []).includes(currentUserId)
        const animType = reactionAnimations[`${messageId}:${r.emoteId}`]
        const animClass = animType === 'pop'
          ? 'animate-reaction-pop'
          : animType === 'bump'
          ? 'animate-reaction-bump'
          : ''

        return (
          <button
            key={r.emoteId}
            onClick={() => onToggle(r.emoteId)}
            onAnimationEnd={() => animType && onAnimationEnd(messageId, r.emoteId)}
            className={`flex items-center gap-1 px-2 py-0.5 text-xs transition-colors ${animClass} ${
              isReacted
                ? 'border-white/40 text-on-surface'
                : 'border-outline-variant text-on-surface-variant hover:border-white/20'
            }`}
          >
            {r.unicode ? (
              <span className="text-sm leading-none">{r.unicode}</span>
            ) : r.imageUrl ? (
              <img src={r.imageUrl} alt={r.emoteCode} className="h-3.5 w-3.5 object-contain" />
            ) : (
              <span className="text-[10px]">{r.emoteCode}</span>
            )}
            <span>{r.count}</span>
          </button>
        )
      })}
    </div>
  )
}

export default ReactionBar
