import { useCallback, useEffect, useState, type ReactNode } from 'react'
import LayoutDiscuss from '../../Components/layout/LayoutDiscuss'
import { useShellChrome } from '../../Components/layout/ShellChrome'
import client from '../../api/client'

type Status = 'pending' | 'valid' | 'dismissed'

interface PersonRef {
  id: string
  display_name?: string
  username?: string | null
}

interface ReportRow {
  id: string
  status: Status
  reason: string
  note: string | null
  penalty: string | null
  created_at: string | null
  message: { id: string; body: string | null; is_deleted: boolean }
  author: PersonRef
  reporter: PersonRef
}

const STATUS_LABELS: Record<Status, string> = { pending: 'Pending', valid: 'Valid', dismissed: 'Dismissed' }

const name = (person: PersonRef) => person.display_name ?? person.username ?? 'Unknown'

function ReportCard({ report, slug, onResolved }: { report: ReportRow; slug: string; onResolved: () => void }) {
  const [penalty, setPenalty] = useState('none')
  const [deleteMessage, setDeleteMessage] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const resolve = async (outcome: 'valid' | 'dismissed') => {
    setBusy(true)
    setError(null)
    try {
      await client.post(`/rooms/${slug}/reports/${report.id}/resolve`, {
        outcome,
        penalty: outcome === 'valid' ? penalty : 'none',
        delete_message: outcome === 'valid' && deleteMessage,
      })
      onResolved()
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not resolve this report.')
      setBusy(false)
    }
  }

  return (
    <div className="rounded-lg bg-surface-container-high px-3 py-3">
      <div className="flex items-center justify-between gap-2">
        <span className="rounded-full bg-red-500/15 px-2 py-0.5 font-label-sm text-label-sm font-semibold capitalize text-red-300">
          {report.reason}
        </span>
        <span className="font-label-sm text-label-sm text-on-surface-variant">
          {report.created_at ? new Date(report.created_at).toLocaleString() : ''}
        </span>
      </div>

      <p className="mt-2 font-body-sm text-body-sm text-on-surface">
        {report.message.body ?? <em className="text-on-surface-variant">Message deleted</em>}
      </p>
      {report.note && (
        <p className="mt-1 font-body-sm text-body-sm text-on-surface-variant">Note: {report.note}</p>
      )}
      <p className="mt-2 font-label-sm text-label-sm text-on-surface-variant">
        By {name(report.author)} · reported by {name(report.reporter)}
      </p>

      {report.status === 'pending' ? (
        <div className="mt-3 flex flex-col gap-2">
          <div className="flex flex-wrap items-center gap-3">
            <label className="flex items-center gap-1.5 font-label-md text-label-md text-on-surface-variant">
              Penalty
              <select
                value={penalty}
                onChange={(e) => setPenalty(e.target.value)}
                className="rounded border border-outline-variant/40 bg-transparent px-1.5 py-1 text-on-surface"
              >
                <option value="none">None</option>
                <option value="spam">Spam</option>
                <option value="manipulation">Manipulation</option>
              </select>
            </label>
            <label className="flex items-center gap-1.5 font-label-md text-label-md text-on-surface-variant">
              <input type="checkbox" checked={deleteMessage} onChange={(e) => setDeleteMessage(e.target.checked)} />
              Delete message
            </label>
          </div>
          <div className="flex gap-2">
            <button
              disabled={busy}
              onClick={() => resolve('valid')}
              className="rounded bg-primary px-3 py-1.5 font-label-md text-label-md font-semibold text-on-primary disabled:opacity-50"
            >
              Valid
            </button>
            <button
              disabled={busy}
              onClick={() => resolve('dismissed')}
              className="rounded border border-outline-variant/40 px-3 py-1.5 font-label-md text-label-md text-on-surface-variant disabled:opacity-50"
            >
              Dismiss
            </button>
          </div>
          {error && <p className="font-label-sm text-label-sm text-red-400">{error}</p>}
        </div>
      ) : (
        <p className="mt-2 font-label-sm text-label-sm text-on-surface-variant">
          {STATUS_LABELS[report.status]}
          {report.penalty && report.penalty !== 'none' ? ` · penalty: ${report.penalty}` : ''}
        </p>
      )}
    </div>
  )
}

export default function Reports({ room }: { room: { slug: string; name: string } }) {
  useShellChrome({ title: `Reports · ${room.name}` })
  const [status, setStatus] = useState<Status>('pending')
  const [reports, setReports] = useState<ReportRow[] | null>(null)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(() => {
    setError(null)
    client.get(`/rooms/${room.slug}/reports`, { params: { status } })
      .then((res) => setReports(res.data?.reports ?? []))
      .catch((err: Error) => setError(err.message))
  }, [room.slug, status])

  useEffect(() => {
    setReports(null)
    load()
  }, [load])

  return (
    <>
      <div className="mx-auto max-w-2xl px-2 pb-6 pt-4">
        <div className="mb-3 flex gap-2">
          {(Object.keys(STATUS_LABELS) as Status[]).map((key) => (
            <button
              key={key}
              onClick={() => setStatus(key)}
              className={`rounded-full px-3 py-1 font-label-md text-label-md transition-colors ${
                status === key
                  ? 'bg-primary text-on-primary'
                  : 'bg-surface-container-high text-on-surface-variant hover:text-on-surface'
              }`}
            >
              {STATUS_LABELS[key]}
            </button>
          ))}
        </div>

        {error && <p className="py-6 text-center font-body-sm text-body-sm text-red-400">{error}</p>}
        {reports === null && !error && (
          <p className="py-8 text-center font-body-sm text-body-sm text-on-surface-variant">Loading...</p>
        )}
        {reports && reports.length === 0 && (
          <p className="py-8 text-center font-body-sm text-body-sm text-on-surface-variant">
            No {STATUS_LABELS[status].toLowerCase()} reports.
          </p>
        )}

        <div className="flex flex-col gap-3">
          {reports?.map((report) => (
            <ReportCard key={report.id} report={report} slug={room.slug} onResolved={load} />
          ))}
        </div>
      </div>
    </>
  )
}

Reports.layout = (page: ReactNode) => <LayoutDiscuss>{page}</LayoutDiscuss>
