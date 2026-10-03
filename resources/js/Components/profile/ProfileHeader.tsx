import { ChartNoAxesColumn, Cloud, Crown } from 'lucide-react'
import Crest from '../cultivation/Crest'

export interface CultivationStatus {
  level: number
  stage: number
  progress: number
  stage_required: number
  progress_percent: number
  realm: { name: string }
  is_max_level: boolean
  badge:{ realm_name: string; realm_slug: string; stage: number; level: number; description:string } | null
}

interface ProfileHeaderProps {
  displayName: string
  username: string
  avatarUrl: string | null
  roleLabel: string
  cultivation: CultivationStatus | null
  cultivationFailed: boolean
}

const fmt = (n: number) => n.toLocaleString('en-US')

const Stat: FC<{ icon: FC<{ className?: string }>; label: string; value: JSX.Element }> = ({ icon: Icon, label, value }) => (
  <div className="min-w-0 flex-1 ">
    <div className="flex items-center gap-1 text-[11px] text-on-surface-variant">
      <Icon className="h-3.5 w-3.5 shrink-0 text-on-surface-variant" />


      <span className="truncate">{label}</span>
    </div>
    {/**<p className="mt-1 text-sm font-semibold leading-tight text-white">{value}</p>*/}
    {value}
  </div>
)

const ProfileHeader: FC<ProfileHeaderProps> = ({
  displayName, username, avatarUrl, roleLabel, cultivation, cultivationFailed,
}) => {
  const realm = cultivation?.realm.name ?? '-'
  const stage = cultivation ? `Stage ${cultivation.stage}` : '-'
  const level = cultivation ? `Lv. ${cultivation.level}` : '-'
  const percent = cultivation ? Math.max(0, Math.min(100, cultivation.progress_percent)) : 0

  return (
    <header className="relative px-5 pb-2 pt-8">
      <div className="relative flex items-center gap-4">
        <div className="shrink-0 rounded-[999px] bg-surface-container-highest p-[3px] ring-1 ring-outline-variant">
          <div className="flex h-20 w-20 items-center justify-center overflow-hidden rounded-[999px] bg-surface-container-low">
            {avatarUrl ? (
              <img src={avatarUrl} alt="" className="h-full w-full object-cover" />
            ) : (
              <span className="text-3xl font-bold text-white">{displayName.charAt(0).toUpperCase()}</span>
            )}
          </div>
        </div>

        <div className="min-w-0 flex-1">
          <div className="flex  items-center gap-x-2 gap-y-1">
            <h1 className="truncate text-md font-bold leading-tight text-white">{displayName}</h1>
            <span className="inline-flex items-center gap-1 rounded-[999px] border border-outline-variant bg-surface-container-high px-2.5 py-0.5 text-[11px] font-semibold text-on-surface">
              <Crown className="h-3 w-3" />
              {roleLabel}
            </span>
          </div>
          <p className="truncate text-base text-on-surface-variant">@{username}</p>

          <div className="mt-3 flex flex-col ">

            <Stat icon={Cloud} label="Realm" value={
                <div className="flex items-center gap-2">
                <Crest realm={
                    cultivation?.badge
                } size={30} />

                <p className="mt-1 text-sm font-semibold leading-tight text-white">
                {realm} - {stage}
                </p>
                </div>
            } />


            <Stat icon={ChartNoAxesColumn} label="Level" value={
                <p className="mt-1 text-sm font-semibold leading-tight text-white">{level}</p>
            } />
          </div>
        </div>
      </div>

      <div className="relative mt-6">
        <div className="mb-2 flex items-baseline justify-between text-sm">
          <span className="font-medium text-on-surface-variant">EXP</span>
          <span className="text-on-surface-variant">
            {cultivation
              ? cultivation.is_max_level
                ? 'MAX'
                : `${fmt(cultivation.progress)} / ${fmt(cultivation.stage_required)}`
              : cultivationFailed ? 'Unavailable' : '...'}
          </span>
        </div>
        <div
          role="progressbar"
          aria-valuemin={0}
          aria-valuemax={100}
          aria-valuenow={Math.round(percent)}
          className="h-2 w-full overflow-hidden rounded-full bg-surface-container-high"
        >
          <div
            className="h-full rounded-full bg-primary transition-[width] duration-500"
            style={{ width: `${cultivation?.is_max_level ? 100 : percent}%` }}
          />
        </div>
      </div>
    </header>
  )
}

export default ProfileHeader
