import { useState, type ReactNode } from 'react'
import LayoutDiscuss from '../../Components/layout/LayoutDiscuss'
import client from '../../api/client'
import { useCultivationToast } from '../../contexts/CultivationToastContext'
import { cpToastNote, type ContributionAwardedPayload } from '../../lib/cpToast'

// Halaman demo, hanya ada saat APP_ENV=local (lihat routes/web.php).

interface Sample {
  label: string
  payload: ContributionAwardedPayload & { source: string }
}

const CP_SAMPLES: Sample[] = [
  { label: 'Message +1', payload: { source: 'message', amount: 1 } },
  { label: 'Reply +4', payload: { source: 'reply', amount: 4 } },
  { label: 'Reaction +2', payload: { source: 'reaction_received', amount: 2 } },
  { label: 'Helpful +15', payload: { source: 'helpful', amount: 15 } },
  { label: 'Best Answer +25', payload: { source: 'best_answer', amount: 25 } },
  { label: 'Daily bonus +10', payload: { source: 'daily_bonus', amount: 10 } },
  { label: 'Streak 7 days +25', payload: { source: 'streak', amount: 25 } },
  { label: 'Achievement +20', payload: { source: 'achievement', amount: 20, detail: 'First Reply' } },
  { label: 'Valid report +5', payload: { source: 'report_valid', amount: 5 } },
  { label: 'Event x2 (+2)', payload: { source: 'message', amount: 2, multiplier: 2, event_name: 'Weekend Boost' } },
]

const btn = 'rounded-lg bg-surface-container-high px-3 py-2 text-left font-label-md text-label-md text-on-surface transition-colors hover:bg-surface-container-highest'

export default function ToastDemo({ resourceName }: { resourceName: string }) {
  const { showGain, showCp } = useCultivationToast()
  const [status, setStatus] = useState<string | null>(null)

  const showLocal = (payload: ContributionAwardedPayload) => showCp(payload.amount, cpToastNote(payload))

  const pushReal = async (payload: ContributionAwardedPayload & { source: string }) => {
    setStatus('Sending...')
    try {
      await client.post('/dev/toast-demo/push', payload)
      setStatus('Sent. If no toast appears, the push path is broken (check BROADCAST_CONNECTION=reverb and that Reverb is running).')
    } catch (err) {
      setStatus(err instanceof Error ? err.message : 'Failed to send.')
    }
  }

  const burst = () => {
    showGain(10, resourceName)
    CP_SAMPLES.slice(0, 4).forEach((sample, i) => {
      setTimeout(() => showLocal(sample.payload), (i + 1) * 400)
    })
  }

  return (
    <div className="mx-auto max-w-2xl px-3 pb-28 pt-4">
      <h1 className="mb-1 font-headline-sm text-headline-sm text-on-surface">Toast demo</h1>
      <p className="mb-4 font-body-sm text-body-sm text-on-surface-variant">
        Local only. Nothing here changes your CP or cultivation data.
      </p>

      <h2 className="mb-2 font-label-md text-label-md font-semibold uppercase tracking-wider text-on-surface-variant">
        Cultivation (client only)
      </h2>
      <div className="mb-5 grid grid-cols-2 gap-2">
        <button className={btn} onClick={() => showGain(10, resourceName)}>+10 {resourceName}</button>
        <button className={btn} onClick={() => showGain(25, resourceName)}>+25 {resourceName}</button>
      </div>
      <p className="-mt-3 mb-5 font-label-sm text-label-sm text-on-surface-variant">
        The real cultivation toast comes from the chapter-complete response, so it cannot be pushed from the server.
      </p>

      <h2 className="mb-2 font-label-md text-label-md font-semibold uppercase tracking-wider text-on-surface-variant">
        CP - show locally
      </h2>
      <div className="mb-5 grid grid-cols-2 gap-2">
        {CP_SAMPLES.map((sample) => (
          <button key={sample.label} className={btn} onClick={() => showLocal(sample.payload)}>
            {sample.label}
          </button>
        ))}
      </div>

      <h2 className="mb-2 font-label-md text-label-md font-semibold uppercase tracking-wider text-on-surface-variant">
        CP - push via Reverb
      </h2>
      <div className="mb-3 grid grid-cols-2 gap-2">
        {CP_SAMPLES.map((sample) => (
          <button key={sample.label} className={btn} onClick={() => pushReal(sample.payload)}>
            {sample.label}
          </button>
        ))}
      </div>
      {status && <p className="mb-5 font-label-md text-label-md text-on-surface-variant">{status}</p>}

      <h2 className="mb-2 font-label-md text-label-md font-semibold uppercase tracking-wider text-on-surface-variant">
        Stack
      </h2>
      <button className={btn} onClick={burst}>Burst: Cultivation + 4 CP</button>
    </div>
  )
}

ToastDemo.layout = (page: ReactNode) => <LayoutDiscuss>{page}</LayoutDiscuss>
