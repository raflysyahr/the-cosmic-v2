import { useEffect, useState, type ReactNode } from 'react'
import { Link } from '@inertiajs/react'
import { Check, Flag, ShieldCheck, Shield, Trash2, X } from 'lucide-react'
import LayoutDiscuss from '../../Components/layout/LayoutDiscuss'
import { useShellChrome } from '../../Components/layout/ShellChrome'
import { Empty, TabPills } from '../../Components/profile/SharedContent'
import client from '../../api/client'

/* ───────────────────────── types ───────────────────────── */

type Status = 'pending' | 'valid' | 'dismissed'
type Penalty = 'none' | 'spam' | 'manipulation'

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

const STATUS_TABS: { id: Status; label: string }[] = [
  { id: 'pending', label: 'Pending' },
  { id: 'valid', label: 'Valid' },
  { id: 'dismissed', label: 'Dismissed' },
]

const PENALTIES: { id: Penalty; label: string }[] = [
  { id: 'none', label: 'None' },
  { id: 'spam', label: 'Spam' },
  { id: 'manipulation', label: 'Manipulation' },
]

// Selaras dengan ReportReasonPicker (alasan yang bisa dipilih pelapor).
const REASON_LABEL: Record<string, string> = {
  spam: 'Spam',
  harassment: 'Harassment',
  inappropriate: 'Inappropriate content',
  manipulation: 'Points manipulation',
  other: 'Other',
}

/* ───────────────────────── helpers ───────────────────────── */

// Catatan: tailwind.config.js menimpa rounded-full (= 0.75rem) dan rounded-lg/xl,
// jadi bentuk lingkaran/pil selalu pakai nilai arbitrary (rounded-[999px]).

const personName = (p: PersonRef) => p.display_name ?? p.username ?? 'Unknown'

const fmtWhen = (iso: string | null): string => {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  return d.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })
}

/* ───────────────────────── pieces ───────────────────────── */

function Person({ label, person }: { label: string; person: PersonRef }) {
  const name = personName(person)

  const body = (
    <span className="flex min-w-0 items-center gap-2.5">
      <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-[999px] bg-[#1c1c1c] text-xs font-semibold text-neutral-300">
        {name.charAt(0).toUpperCase() || '?'}
      </span>
      <span className="min-w-0">
        <span className="block text-[10px] font-semibold uppercase tracking-wider text-neutral-500">{label}</span>
        <span className="block truncate text-[13px] font-medium text-white">{name}</span>
      </span>
    </span>
  )

  return person.username ? (
    <Link href={`/u/${person.username}`} className="min-w-0 flex-1 transition-opacity active:opacity-70">
      {body}
    </Link>
  ) : (
    <div className="min-w-0 flex-1">{body}</div>
  )
}

function ReportCard({
  report,
  slug,
  onResolved,
}: {
  report: ReportRow
  slug: string
  onResolved: (id: string) => void
}) {
  const [penalty, setPenalty] = useState<Penalty>('none')
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
      onResolved(report.id)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not resolve this report.')
      setBusy(false)
    }
  }

  const canDelete = !report.message.is_deleted

  return (
    <article className="rounded-[18px] border border-[#262626] bg-[#0f0f0f] p-4">
      {/* Alasan + waktu */}
      <div className="flex items-center justify-between gap-2">
        <span className="flex items-center gap-1.5 rounded-[999px] bg-red-500/15 px-2.5 py-1 text-xs font-semibold text-red-300">
          <Flag className="h-3 w-3" />
          {REASON_LABEL[report.reason] ?? report.reason}
        </span>
        <span className="shrink-0 text-xs text-neutral-500">{fmtWhen(report.created_at)}</span>
      </div>

      {/* Pesan yang dilaporkan */}
      <blockquote className="mt-3 rounded-[12px] border-l-2 border-red-400/60 bg-[#151515] px-3.5 py-3">
        {report.message.body ? (
          <p className="whitespace-pre-line break-words text-sm leading-snug text-neutral-200">{report.message.body}</p>
        ) : (
          <p className="text-sm italic text-neutral-500">Message deleted</p>
        )}
      </blockquote>

      {report.note && (
        <p className="mt-2.5 break-words text-[13px] text-neutral-400">
          <span className="font-semibold text-neutral-300">Note:</span> {report.note}
        </p>
      )}

      {/* Penulis & pelapor */}
      <div className="mt-3.5 flex items-center gap-3 border-t border-[#222] pt-3.5">
        <Person label="Author" person={report.author} />
        <Person label="Reporter" person={report.reporter} />
      </div>

      {report.status === 'pending' ? (
        <div className="mt-4 flex flex-col gap-3.5 border-t border-[#222] pt-4">
          {/* Penalti */}
          <div>
            <p className="mb-2 text-xs font-semibold uppercase tracking-wider text-neutral-500">Penalty for author</p>
            <div role="radiogroup" aria-label="Penalty" className="grid grid-cols-3 gap-1 rounded-[999px] border border-[#262626] bg-[#0b0b0b] p-1">
              {PENALTIES.map((p) => {
                const active = penalty === p.id
                return (
                  <button
                    key={p.id}
                    type="button"
                    role="radio"
                    aria-checked={active}
                    disabled={busy}
                    onClick={() => setPenalty(p.id)}
                    className={`truncate rounded-[999px] px-2 py-2 text-[13px] font-medium transition-colors disabled:opacity-50 ${
                      active ? 'bg-[#252525] text-white' : 'text-neutral-400 hover:text-neutral-200'
                    }`}
                  >
                    {p.label}
                  </button>
                )
              })}
            </div>
          </div>

          {/* Hapus pesan */}
          <button
            type="button"
            role="switch"
            aria-checked={deleteMessage}
            disabled={busy || !canDelete}
            onClick={() => setDeleteMessage((v) => !v)}
            className="flex items-center justify-between gap-3 rounded-[12px] border border-[#222] px-3.5 py-3 text-left transition-colors hover:bg-[#151515] disabled:opacity-40"
          >
            <span className="flex items-center gap-2.5 text-sm text-neutral-200">
              <Trash2 className="h-4 w-4 text-neutral-500" />
              Delete message
            </span>
            <span
              className={`flex h-6 w-10 shrink-0 items-center rounded-[999px] p-0.5 transition-colors ${
                deleteMessage ? 'bg-white' : 'bg-[#2a2a2a]'
              }`}
            >
              <span
                className={`h-5 w-5 rounded-[999px] transition-transform ${
                  deleteMessage ? 'translate-x-4 bg-black' : 'translate-x-0 bg-neutral-400'
                }`}
              />
            </span>
          </button>

          {/* Aksi */}
          <div className="grid grid-cols-2 gap-2">
            <button
              type="button"
              disabled={busy}
              onClick={() => resolve('valid')}
              className="flex items-center justify-center gap-2 rounded-[999px] bg-white px-4 py-2.5 text-sm font-bold text-black transition-colors hover:bg-[#ddd] disabled:opacity-50"
            >
              <Check className="h-4 w-4" />
              {busy ? 'Saving…' : 'Mark valid'}
            </button>
            <button
              type="button"
              disabled={busy}
              onClick={() => resolve('dismissed')}
              className="flex items-center justify-center gap-2 rounded-[999px] border border-[#2e2e2e] px-4 py-2.5 text-sm font-semibold text-neutral-300 transition-colors hover:bg-[#1a1a1a] disabled:opacity-50"
            >
              <X className="h-4 w-4" />
              Dismiss
            </button>
          </div>

          {error && <p className="text-xs text-red-400">{error}</p>}
        </div>
      ) : (
        <div className="mt-4 flex flex-wrap items-center gap-2 border-t border-[#222] pt-3.5">
          <span
            className={`flex items-center gap-1.5 rounded-[999px] px-2.5 py-1 text-xs font-semibold ${
              report.status === 'valid' ? 'bg-green-500/15 text-green-300' : 'bg-[#1c1c1c] text-neutral-400'
            }`}
          >
            {report.status === 'valid' ? <Check className="h-3 w-3" /> : <X className="h-3 w-3" />}
            {report.status === 'valid' ? 'Valid' : 'Dismissed'}
          </span>
          {report.penalty && report.penalty !== 'none' && (
            <span className="rounded-[999px] bg-red-500/15 px-2.5 py-1 text-xs font-semibold capitalize text-red-300">
              Penalty: {report.penalty}
            </span>
          )}
        </div>
      )}
    </article>
  )
}

function SkeletonCard() {
  return (
    <div className="animate-pulse rounded-[18px] border border-[#262626] bg-[#0f0f0f] p-4">
      <div className="h-6 w-28 rounded-[999px] bg-[#1c1c1c]" />
      <div className="mt-3 h-16 rounded-[12px] bg-[#151515]" />
      <div className="mt-4 h-8 rounded-[999px] bg-[#151515]" />
    </div>
  )
}

/* ───────────────────────── page ───────────────────────── */

export default function Reports({ room }: { room: { slug: string; name: string } }) {
  useShellChrome({ title: `Reports · ${room.name}` })

  const [status, setStatus] = useState<Status>('pending')
  const [reports, setReports] = useState<ReportRow[] | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [reloadKey, setReloadKey] = useState(0)

  // `cancelled` mencegah respons lama menimpa daftar saat user cepat ganti tab.
  useEffect(() => {
    let cancelled = false
    setReports(null)
    setError(null)

    client
      .get(`/rooms/${room.slug}/reports`, { params: { status } })
      .then((res) => {
        if (!cancelled) setReports(res.data?.reports ?? [])
      })
      .catch((err: Error) => {
        if (!cancelled) setError(err.message)
      })

    return () => {
      cancelled = true
    }
  }, [room.slug, status, reloadKey])

  // Laporan yang baru ditutup langsung hilang dari antrean pending.
  const handleResolved = (id: string) => setReports((prev) => prev?.filter((r) => r.id !== id) ?? prev)

  const tabLabel = STATUS_TABS.find((t) => t.id === status)?.label.toLowerCase() ?? status

  return (
    <div className="min-h-full bg-[#0b0b0b] px-4 pb-10 pt-6">
      {/* Ringkasan */}
      <section className="flex items-start gap-4 rounded-[20px] border border-[#262626] bg-[#0f0f0f] px-5 py-5">
        <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-[999px] bg-[#1c1c1c] text-neutral-300">
          <Shield className="h-6 w-6" strokeWidth={1.6} />
        </span>
        <div className="min-w-0">
          <h1 className="truncate text-lg font-bold text-white">{room.name}</h1>
          <p className="mt-0.5 text-[13px] leading-snug text-neutral-500">
            A valid report rewards the reporter with CP. You can also penalize the author and delete the message.
          </p>
        </div>
      </section>

      <TabPills tabs={STATUS_TABS} active={status} onChange={setStatus} label="Report status" />

      <div role="tabpanel" className="mt-4">
        {reports && reports.length > 0 && (
          <p className="mb-3 px-1 text-xs font-semibold uppercase tracking-wider text-neutral-500">
            {reports.length} {tabLabel} {reports.length === 1 ? 'report' : 'reports'}
          </p>
        )}

        {error && (
          <div className="flex flex-col items-center gap-3 py-12 text-center">
            <p className="text-sm text-red-400">{error}</p>
            <button
              type="button"
              onClick={() => setReloadKey((k) => k + 1)}
              className="rounded-[999px] border border-[#2e2e2e] px-4 py-2 text-xs font-semibold text-neutral-300 transition-colors hover:bg-[#1a1a1a]"
            >
              Try again
            </button>
          </div>
        )}

        {reports === null && !error && (
          <div className="flex flex-col gap-3">
            <SkeletonCard />
            <SkeletonCard />
          </div>
        )}

        {reports && reports.length === 0 && <Empty icon={ShieldCheck} text={`No ${tabLabel} reports`} />}

        {reports && reports.length > 0 && (
          <div className="flex flex-col gap-3">
            {reports.map((report) => (
              <ReportCard key={report.id} report={report} slug={room.slug} onResolved={handleResolved} />
            ))}
          </div>
        )}
      </div>
    </div>
  )
}

Reports.layout = (page: ReactNode) => <LayoutDiscuss>{page}</LayoutDiscuss>
