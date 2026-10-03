import { useCallback, useEffect, useState, type ReactNode } from 'react'
import LayoutDiscuss from '../../Components/layout/LayoutDiscuss'
import client from '../../api/client'

type Value = number | boolean

interface Field {
  key: string
  label: string
  bool?: boolean
}

// Urutan & pengelompokan mengikuti config/discuss_cp.php. Key yang tidak
// terdaftar di server diabaikan (CpSettingsService::TYPES), jadi daftar ini
// hanya soal tampilan.
const GROUPS: { title: string; fields: Field[] }[] = [
  { title: 'General', fields: [{ key: 'enabled', label: 'CP enabled', bool: true }] },
  {
    title: 'Messages & replies',
    fields: [
      { key: 'message_points', label: 'Plain message points' },
      { key: 'message_daily_cap', label: 'Plain message daily cap (base points)' },
      { key: 'reply_tier1_limit', label: 'Reply tier 1: replies per day' },
      { key: 'reply_tier1_points', label: 'Reply tier 1 points' },
      { key: 'reply_tier2_limit', label: 'Reply tier 2: replies per day' },
      { key: 'reply_tier2_points', label: 'Reply tier 2 points' },
    ],
  },
  {
    title: 'Reactions & replies received',
    fields: [
      { key: 'reaction_points', label: 'Reaction points' },
      { key: 'reaction_cap_per_message', label: 'Reaction cap per message' },
      { key: 'require_verified_reactor', label: 'Only verified reactors count', bool: true },
      { key: 'reply_received_points', label: 'Reply received points' },
      { key: 'reply_received_cap_per_message', label: 'Reply received cap per message' },
    ],
  },
  {
    title: 'Anti-spam',
    fields: [
      { key: 'burst_count', label: 'Burst: max messages' },
      { key: 'burst_window_seconds', label: 'Burst window (seconds)' },
      { key: 'duplicate_window_hours', label: 'Duplicate text window (hours)' },
      { key: 'min_alnum_chars', label: 'Min letters/digits per message' },
    ],
  },
  {
    title: 'Helpful & Best Answer',
    fields: [
      { key: 'helpful_points', label: 'Helpful points' },
      { key: 'helpful_min_member_hours', label: 'Helpful: min member age (hours)' },
      { key: 'helpful_daily_give_limit', label: 'Helpful marks per person per day' },
      { key: 'best_answer_points', label: 'Best Answer points' },
    ],
  },
  {
    title: 'Daily bonus & streak',
    fields: [
      { key: 'daily_bonus_points', label: 'Daily bonus points' },
      { key: 'daily_bonus_min_replies', label: 'Qualifying replies (0 = off)' },
      { key: 'daily_bonus_min_messages', label: 'Qualifying messages (0 = off)' },
      { key: 'streak_3_points', label: '3-day streak points' },
      { key: 'streak_7_points', label: '7-day streak points' },
      { key: 'streak_14_points', label: '14-day streak points' },
      { key: 'streak_30_points', label: '30-day streak points' },
    ],
  },
  {
    title: 'Achievements (0 = disabled)',
    fields: [
      { key: 'achievement_first_reply_points', label: 'First Reply' },
      { key: 'achievement_replies_100_points', label: '100 Replies' },
      { key: 'achievement_likes_100_points', label: '100 Reactions' },
      { key: 'achievement_helpful_10_points', label: '10 Helpful' },
      { key: 'achievement_best_answer_10_points', label: '10 Best Answers' },
      { key: 'achievement_active_30_points', label: 'Active 30 Days' },
    ],
  },
  {
    title: 'Reports & penalties',
    fields: [
      { key: 'report_valid_points', label: 'Valid report points' },
      { key: 'report_daily_limit', label: 'Reports per person per day' },
      { key: 'penalty_spam_points', label: 'Spam penalty' },
      { key: 'penalty_manipulation_points', label: 'Manipulation penalty' },
    ],
  },
]

const EVENT_SOURCES = [
  'message', 'reply', 'reaction_received', 'reply_received',
  'helpful', 'best_answer', 'daily_bonus', 'streak', 'report_valid',
]

interface CpEvent {
  id: string
  name: string
  multiplier: number
  sources: string[] | null
  starts_at: string
  ends_at: string
  is_active: boolean
  is_running: boolean
}

const toLocalInput = (date: Date) => {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

const inputClass = 'rounded border border-outline-variant/40 bg-transparent px-2 py-1.5 font-body-sm text-body-sm text-on-surface'

export default function CpAdmin() {
  const [settings, setSettings] = useState<Record<string, Value>>({})
  const [defaults, setDefaults] = useState<Record<string, Value>>({})
  const [draft, setDraft] = useState<Record<string, Value>>({})
  const [events, setEvents] = useState<CpEvent[]>([])
  const [message, setMessage] = useState<{ kind: 'ok' | 'error'; text: string } | null>(null)
  const [saving, setSaving] = useState(false)

  const [evName, setEvName] = useState('')
  const [evMultiplier, setEvMultiplier] = useState('2')
  const [evSources, setEvSources] = useState<string[]>([])
  const [evStart, setEvStart] = useState(toLocalInput(new Date()))
  const [evEnd, setEvEnd] = useState(toLocalInput(new Date(Date.now() + 86_400_000)))

  const fail = (err: unknown) =>
    setMessage({ kind: 'error', text: err instanceof Error ? err.message : 'Something went wrong.' })

  const loadEvents = useCallback(() => {
    client.get('/admin/cp/events').then((res) => setEvents(res.data?.events ?? [])).catch(fail)
  }, [])

  useEffect(() => {
    client.get('/admin/cp/settings')
      .then((res) => {
        setSettings(res.data.settings)
        setDefaults(res.data.defaults)
        setDraft(res.data.settings)
      })
      .catch(fail)
    loadEvents()
  }, [loadEvents])

  const dirtyKeys = Object.keys(draft).filter((key) => draft[key] !== settings[key])

  const saveSettings = async () => {
    setSaving(true)
    setMessage(null)
    try {
      // Nilai yang sama dengan default dikirim sebagai null = hapus override.
      const payload: Record<string, Value | null> = {}
      dirtyKeys.forEach((key) => { payload[key] = draft[key] === defaults[key] ? null : draft[key] })

      const res = await client.put('/admin/cp/settings', payload)
      setSettings(res.data.settings)
      setDefaults(res.data.defaults)
      setDraft(res.data.settings)
      setMessage({ kind: 'ok', text: 'Settings saved.' })
    } catch (err) {
      fail(err)
    } finally {
      setSaving(false)
    }
  }

  const createEvent = async () => {
    setMessage(null)
    try {
      await client.post('/admin/cp/events', {
        name: evName.trim(),
        multiplier: Number(evMultiplier),
        sources: evSources,
        starts_at: new Date(evStart).toISOString(),
        ends_at: new Date(evEnd).toISOString(),
      })
      setEvName('')
      setEvSources([])
      loadEvents()
      setMessage({ kind: 'ok', text: 'Event created.' })
    } catch (err) {
      fail(err)
    }
  }

  const toggleEvent = (event: CpEvent) =>
    client.put(`/admin/cp/events/${event.id}`, { is_active: !event.is_active }).then(loadEvents).catch(fail)

  const deleteEvent = (event: CpEvent) => {
    if (!window.confirm(`Delete event "${event.name}"?`)) return
    client.delete(`/admin/cp/events/${event.id}`).then(loadEvents).catch(fail)
  }

  return (
    <>
      <div className="mx-auto max-w-2xl px-2 pb-0 pt-4">
        {message && (
          <p className={`mb-3 rounded px-3 py-2 font-label-md text-label-md ${
            message.kind === 'ok' ? 'bg-green-500/15 text-green-300' : 'bg-red-500/15 text-red-300'
          }`}>
            {message.text}
          </p>
        )}

        <h2 className="mb-2 font-label-md text-label-md font-semibold uppercase tracking-wider text-on-surface-variant">
          Multiplier events
        </h2>

        <div className="mb-3 flex flex-col gap-2">
          {events.length === 0 && (
            <p className="font-body-sm text-body-sm text-on-surface-variant">No events yet.</p>
          )}
          {events.map((event) => (
            <div key={event.id} className="rounded-lg bg-surface-container-high px-3 py-2.5">
              <div className="flex items-center justify-between gap-2">
                <span className="font-label-md text-label-md font-semibold text-on-surface">
                  x{event.multiplier} · {event.name}
                </span>
                <span className={`font-label-sm text-label-sm ${event.is_running ? 'text-green-300' : 'text-on-surface-variant'}`}>
                  {event.is_running ? 'Running' : event.is_active ? 'Scheduled / ended' : 'Off'}
                </span>
              </div>
              <p className="mt-0.5 font-label-sm text-label-sm text-on-surface-variant">
                {new Date(event.starts_at).toLocaleString()} → {new Date(event.ends_at).toLocaleString()}
                {' · '}{event.sources?.length ? event.sources.join(', ') : 'all sources'}
              </p>
              <div className="mt-2 flex gap-3">
                <button onClick={() => toggleEvent(event)} className="font-label-md text-label-md text-primary">
                  {event.is_active ? 'Turn off' : 'Turn on'}
                </button>
                <button onClick={() => deleteEvent(event)} className="font-label-md text-label-md text-red-400">
                  Delete
                </button>
              </div>
            </div>
          ))}
        </div>

        <div className="mb-8 flex flex-col gap-2 rounded-lg border border-outline-variant/30 p-3">
          <span className="font-label-md text-label-md font-semibold text-on-surface">New event</span>
          <input className={inputClass} placeholder="Name (e.g. Weekend Boost)" value={evName} onChange={(e) => setEvName(e.target.value)} />
          <label className="flex items-center gap-2 font-label-md text-label-md text-on-surface-variant">
            Multiplier
            <input className={`${inputClass} w-20`} type="number" min="1" max="10" step="0.5" value={evMultiplier} onChange={(e) => setEvMultiplier(e.target.value)} />
          </label>
          <div className="flex flex-wrap gap-x-3 gap-y-1">
            {EVENT_SOURCES.map((source) => (
              <label key={source} className="flex items-center gap-1 font-label-sm text-label-sm text-on-surface-variant">
                <input
                  type="checkbox"
                  checked={evSources.includes(source)}
                  onChange={(e) => setEvSources((prev) => e.target.checked ? [...prev, source] : prev.filter((s) => s !== source))}
                />
                {source}
              </label>
            ))}
          </div>
          <span className="font-label-sm text-label-sm text-on-surface-variant">
            Leave all unchecked to boost every source.
          </span>
          <div className="flex flex-wrap gap-2">
            <input className={inputClass} type="datetime-local" value={evStart} onChange={(e) => setEvStart(e.target.value)} />
            <input className={inputClass} type="datetime-local" value={evEnd} onChange={(e) => setEvEnd(e.target.value)} />
          </div>
          <button
            onClick={createEvent}
            disabled={!evName.trim()}
            className="self-start rounded bg-primary px-3 py-1.5 font-label-md text-label-md font-semibold text-on-primary disabled:opacity-50"
          >
            Create event
          </button>
        </div>

        <h2 className="mb-2 font-label-md text-label-md font-semibold uppercase tracking-wider text-on-surface-variant">
          Point settings
        </h2>

        {GROUPS.map((group) => (
          <div key={group.title} className="mb-4">
            <h3 className="mb-1.5 font-label-md text-label-md font-semibold text-on-surface">{group.title}</h3>
            <div className="flex flex-col divide-y divide-outline-variant/20">
              {group.fields.map((field) => {
                const value = draft[field.key]
                const isDefault = value === defaults[field.key]
                return (
                  <div key={field.key} className="flex items-center gap-2 py-2">
                    <span className="min-w-0 flex-1 font-body-sm text-body-sm text-on-surface">{field.label}</span>
                    {field.bool ? (
                      <input
                        type="checkbox"
                        checked={!!value}
                        onChange={(e) => setDraft((prev) => ({ ...prev, [field.key]: e.target.checked }))}
                      />
                    ) : (
                      <input
                        className={`${inputClass} w-20 text-right`}
                        type="number"
                        min="0"
                        value={typeof value === 'number' ? value : ''}
                        onChange={(e) => setDraft((prev) => ({ ...prev, [field.key]: Number(e.target.value) }))}
                      />
                    )}
                    <button
                      disabled={isDefault}
                      onClick={() => setDraft((prev) => ({ ...prev, [field.key]: defaults[field.key] }))}
                      title={`Default: ${String(defaults[field.key])}`}
                      className="w-12 text-right font-label-sm text-label-sm text-primary disabled:text-on-surface-variant/40"
                    >
                      Reset
                    </button>
                  </div>
                )
              })}
            </div>
          </div>
        ))}

        <div className="sticky bottom-0 -mx-2 mt-4 border-t border-outline-variant/30 bg-surface px-4 py-2">
          <div className="mx-auto flex max-w-2xl items-center justify-between">
            <span className="font-label-md text-label-md text-on-surface-variant">
              {dirtyKeys.length === 0 ? 'No changes' : `${dirtyKeys.length} unsaved change${dirtyKeys.length > 1 ? 's' : ''}`}
            </span>
            <button
              onClick={saveSettings}
              disabled={dirtyKeys.length === 0 || saving}
              className="rounded bg-primary px-4 py-1.5 font-label-md text-label-md font-semibold text-on-primary disabled:opacity-50"
            >
              {saving ? 'Saving...' : 'Save settings'}
            </button>
          </div>
        </div>
      </div>
    </>
  )
}

CpAdmin.layout = (page: ReactNode) => <LayoutDiscuss>{page}</LayoutDiscuss>
