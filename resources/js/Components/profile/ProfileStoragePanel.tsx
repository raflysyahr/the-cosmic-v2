import { useState, type FC } from 'react'
import { clearMediaCache, getCacheUsage } from '../../lib/mediaCache'
import { PanelShell, subtleButton } from './ProfileParts'

const formatBytes = (bytes: number): string => {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

const ProfileStoragePanel: FC = () => {
  const [usage, setUsage] = useState(getCacheUsage)
  const [busy, setBusy] = useState(false)
  const [cleared, setCleared] = useState(false)

  const clear = async () => {
    const ok = window.confirm(
      'Clear all cached media on this device? Files you saved from chats will need to be opened online again.',
    )
    if (!ok) return
    setBusy(true)
    try {
      await clearMediaCache()
      setUsage(getCacheUsage())
      setCleared(true)
    } finally {
      setBusy(false)
    }
  }

  return (
    <PanelShell>
      <div className="rounded-xl border border-outline-variant/30 bg-surface-container-low px-4 py-5">
        <p className="text-xs text-on-surface-variant">Cached media on this device</p>
        <p className="mt-1 text-3xl font-bold text-white">{formatBytes(usage.bytes)}</p>
        <p className="mt-1 text-sm text-on-surface-variant">
          {usage.count} {usage.count === 1 ? 'file' : 'files'} from chats
        </p>
      </div>
      <p className="mt-3 text-xs text-on-surface-variant">
        Images, videos and files you open in Discuss are kept on this device so they load instantly next time.
        Clearing the cache only affects this device.
      </p>

      <button type="button" onClick={clear} disabled={busy || usage.count === 0} className={`${subtleButton} mt-5 w-full`}>
        {busy ? 'Clearing...' : 'Clear media cache'}
      </button>
      {cleared && <p className="mt-3 text-sm text-green-400">Cache cleared.</p>}
    </PanelShell>
  )
}

export default ProfileStoragePanel
