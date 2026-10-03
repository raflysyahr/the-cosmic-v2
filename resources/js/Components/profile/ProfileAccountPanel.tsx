import { useState, type FC } from 'react'
import { router } from '@inertiajs/react'
import { BadgeCheck, MailWarning } from 'lucide-react'
import client from '../../api/client'
import { useAuth } from '../../contexts/AuthContext'
import { Card, InfoRow, PanelShell, subtleButton } from './ProfileParts'

interface ProfileAccountPanelProps {
  emailVerified: boolean
  roleLabel: string
}

const ProfileAccountPanel: FC<ProfileAccountPanelProps> = ({ emailVerified, roleLabel }) => {
  const { user, logout } = useAuth()
  const [sending, setSending] = useState(false)
  const [message, setMessage] = useState<{ kind: 'ok' | 'error'; text: string } | null>(null)

  const resend = async () => {
    setSending(true)
    setMessage(null)
    try {
      await client.post('/email/verification-notification')
      setMessage({ kind: 'ok', text: 'Verification email sent. Check your inbox.' })
    } catch (err) {
      setMessage({ kind: 'error', text: err instanceof Error ? err.message : 'Could not send the email.' })
    } finally {
      setSending(false)
    }
  }

  const signOut = () => {
    logout().finally(() => router.visit('/', { replace: true }))
  }

  return (
    <PanelShell>
      <div className="-mx-4 -mt-2">
        <Card>
          <InfoRow icon={emailVerified ? BadgeCheck : MailWarning} label="Email">
            <p>{user?.email}</p>
            <p className={`mt-0.5 text-xs ${emailVerified ? 'text-green-400' : 'text-amber-300'}`}>
              {emailVerified ? 'Verified' : 'Not verified'}
            </p>
          </InfoRow>
          <InfoRow icon={BadgeCheck} label="Role">{roleLabel}</InfoRow>
        </Card>
      </div>

      {!emailVerified && (
        <button type="button" onClick={resend} disabled={sending} className={`${subtleButton} mt-5 w-full`}>
          {sending ? 'Sending...' : 'Resend verification email'}
        </button>
      )}
      {message && (
        <p className={`mt-3 text-sm ${message.kind === 'ok' ? 'text-green-400' : 'text-red-400'}`}>{message.text}</p>
      )}

      <button
        type="button"
        onClick={signOut}
        className="mt-5 w-full rounded-lg border border-error/40 px-4 py-2.5 text-sm font-semibold text-error transition-colors hover:bg-error/10"
      >
        Log out
      </button>
    </PanelShell>
  )
}

export default ProfileAccountPanel
