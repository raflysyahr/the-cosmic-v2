import { useEffect, useState, type ReactNode } from 'react'
import { Link } from '@inertiajs/react'
import { Award, Settings, Trophy } from 'lucide-react'
import LayoutDiscuss from '../../Components/layout/LayoutDiscuss'
import Avatar from '../../Components/ui/Avatar'
import client from '../../api/client'

type Period = 'weekly' | 'monthly' | 'all'
type Tab = 'leaderboard' | 'achievements'

interface LeaderboardEntry {
  rank: number
  userId: string
  username: string | null
  displayName: string
  avatarUrl: string | null
  points: number
}

interface LeaderboardData {
  period: Period
  entries: LeaderboardEntry[]
  me: number | null
}

interface Achievement {
  key: string
  name: string
  description: string
  points: number
  threshold: number
  progress: number
  unlocked: boolean
  unlocked_at: string | null
}

const PERIOD_LABELS: Record<Period, string> = { weekly: 'This week', monthly: 'This month', all: 'All time' }

export default function Leaderboard({ isAdmin }: { isAdmin: boolean }) {
  const [tab, setTab] = useState<Tab>('leaderboard')
  const [period, setPeriod] = useState<Period>('weekly')
  const [board, setBoard] = useState<LeaderboardData | null>(null)
  const [achievements, setAchievements] = useState<Achievement[] | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false
    setBoard(null)
    setError(null)
    client.get('/discuss/leaderboard', { params: { period } })
      .then((res) => { if (!cancelled) setBoard(res.data) })
      .catch((err: Error) => { if (!cancelled) setError(err.message) })
    return () => { cancelled = true }
  }, [period])

  useEffect(() => {
    if (tab !== 'achievements' || achievements) return
    let cancelled = false
    client.get('/discuss/achievements')
      .then((res) => { if (!cancelled) setAchievements(res.data?.achievements ?? []) })
      .catch((err: Error) => { if (!cancelled) setError(err.message) })
    return () => { cancelled = true }
  }, [tab, achievements])

  const tabClass = (active: boolean) =>
    `flex flex-1 items-center justify-center gap-1.5 py-3 font-label-md text-label-md transition-colors ${
      active ? 'border-b-2 border-primary text-primary' : 'text-on-surface-variant'
    }`

  return (
    <>
      <div className="mx-auto max-w-2xl px-2 pb-6 pt-4">
        <div className="mb-2 flex items-center justify-between">

          {isAdmin && (
            <Link
              href="/discuss/leaderboard/admin"
              className="relative px-2 bg-surface-container-higher rounded-[999px]  flex items-center justify-center gap-1.5 font-label-md text-label-md text-on-surface-variant hover:text-on-surface"
            >
              <Settings className="h-4 w-4" />
              Settings Cp
            </Link>
          )}
        </div>

        <div className="mb-3 flex border-b border-outline-variant/30">
          <button onClick={() => setTab('leaderboard')} className={tabClass(tab === 'leaderboard')}>
            <Trophy className="h-4 w-4" />
            Leaderboard
          </button>
          <button onClick={() => setTab('achievements')} className={tabClass(tab === 'achievements')}>
            <Award className="h-4 w-4" />
            Achievements
          </button>
        </div>

        {error && <p className="py-6 text-center font-body-sm text-body-sm text-red-400">{error}</p>}

        {tab === 'leaderboard' && (
          <div className="px-3">
            <div className="mb-3 flex gap-2">
              {(Object.keys(PERIOD_LABELS) as Period[]).map((key) => (
                <button
                  key={key}
                  onClick={() => setPeriod(key)}
                  className={`rounded-full px-3 py-1 font-label-md text-label-md transition-colors ${
                    period === key
                      ? 'bg-primary text-on-primary'
                      : 'bg-surface-container-high text-on-surface-variant hover:text-on-surface'
                  }`}
                >
                  {PERIOD_LABELS[key]}
                </button>
              ))}
            </div>

            {board === null && !error && (
              <p className="py-8 text-center font-body-sm text-body-sm text-on-surface-variant">Loading...</p>
            )}

            {board && board.entries.length === 0 && (
              <p className="py-8 text-center font-body-sm text-body-sm text-on-surface-variant">
                No points yet for this period.
              </p>
            )}

            {board && board.entries.length > 0 && (
              <div className="flex flex-col divide-y divide-outline-variant/30">
                {board.entries.map((entry) => (
                  <div key={entry.userId} className="flex items-center gap-3 py-2.5">
                    <span className="w-6 text-center font-label-md text-label-md text-on-surface-variant">
                      {entry.rank}
                    </span>
                    <Avatar src={entry.avatarUrl} alt={entry.displayName} size="sm" />
                    <span className="min-w-0 flex-1 truncate font-body-sm text-body-sm text-on-surface">
                      {entry.displayName}
                    </span>
                    <span className="font-label-md text-label-md font-semibold text-amber-300">
                      {entry.points} CP
                    </span>
                  </div>
                ))}
              </div>
            )}

            {board && board.me !== null && (
              <div className="mt-4 rounded-[999px] bg-surface-container-high px-3 py-2 font-label-md text-label-md text-on-surface-variant w-fit rounded-[99px]">
                Your points: <span className="font-semibold text-amber-300">{board.me} CP</span>
              </div>
            )}
          </div>
        )}

        {tab === 'achievements' && (
          <>
            {achievements === null && !error && (
              <p className="py-8 text-center font-body-sm text-body-sm text-on-surface-variant">Loading...</p>
            )}

            <div className="flex flex-col gap-2 px-3">
              {achievements?.map((item) => (
                <div
                  key={item.key}
                  className={`rounded-full px-3 py-3 ${item.unlocked ? 'bg-amber-400/10' : 'bg-surface-container-high'}`}
                >
                  <div className="flex items-center justify-between gap-2">
                    <span className="font-label-md text-label-md font-semibold text-on-surface">{item.name}</span>
                    <span className={`font-label-md text-label-md ${item.unlocked ? 'text-amber-300' : 'text-on-surface-variant'}`}>
                      {item.unlocked ? 'Unlocked' : item.points > 0 ? `+${item.points} CP` : 'Disabled'}
                    </span>
                  </div>
                  <p className="mt-0.5 font-body-sm text-body-sm text-on-surface-variant">{item.description}</p>
                  {!item.unlocked && (
                    <div className="mt-2">
                      <div className="h-1.5 w-full overflow-hidden rounded-full bg-surface-container-highest">
                        <div
                          className="h-full rounded-full bg-primary"
                          style={{ width: `${Math.min(100, (item.progress / item.threshold) * 100)}%` }}
                        />
                      </div>
                      <span className="mt-1 block font-label-sm text-label-sm text-on-surface-variant">
                        {item.progress} / {item.threshold}
                      </span>
                    </div>
                  )}
                </div>
              ))}
            </div>
          </>
        )}
      </div>
    </>
  )
}

Leaderboard.layout = (page: ReactNode) => <LayoutDiscuss>{page}</LayoutDiscuss>
