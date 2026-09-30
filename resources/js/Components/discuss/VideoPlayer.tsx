import { useRef, useState, useEffect, type FC, type PointerEvent } from 'react'
import { Play, Pause, Volume2, VolumeX, Loader2, AlertTriangle, RotateCcw, RotateCw } from 'lucide-react'
import { formatDuration } from '../../lib/media'

const SKIP_SECONDS = 10
const SPEEDS = [0.5, 0.75, 1, 1.25, 1.5, 2] as const

interface VideoPlayerProps {
  src: string
  poster?: string | null
  autoPlay?: boolean
  /** Duration from metadata, used when the file itself reports none (e.g. some webm). */
  durationHint?: number | null
  /** Controlled controls visibility; when omitted the player manages it itself. */
  chromeHidden?: boolean
  onToggleChrome?: () => void
  /** Space kept free under the control bar (e.g. for an overlaid caption input). */
  bottomOffset?: number
}

/**
 * Video player with custom controls (no native browser UI): play/pause, seek
 * bar, time and mute. Tapping the picture toggles the controls, like the image
 * viewer toggles its caption.
 */
const VideoPlayer: FC<VideoPlayerProps> = ({
  src, poster, autoPlay, durationHint, chromeHidden, onToggleChrome, bottomOffset = 0,
}) => {
  const videoRef = useRef<HTMLVideoElement>(null)
  const barRef = useRef<HTMLDivElement>(null)
  const seeking = useRef(false)

  const [playing, setPlaying] = useState(false)
  const [current, setCurrent] = useState(0)
  const [duration, setDuration] = useState(0)
  const [muted, setMuted] = useState(false)
  const [buffering, setBuffering] = useState(false)
  const [failed, setFailed] = useState(false)
  const [innerHidden, setInnerHidden] = useState(false)
  const [rate, setRate] = useState(1)
  const [skipFlash, setSkipFlash] = useState<'back' | 'forward' | null>(null)
  const skipFlashTimer = useRef<ReturnType<typeof setTimeout> | null>(null)

  const hidden = chromeHidden ?? innerHidden
  const toggleChrome = onToggleChrome ?? (() => setInnerHidden((h) => !h))
  const total = duration > 0 ? duration : (durationHint ?? 0)
  const progress = total > 0 ? Math.min(100, (current / total) * 100) : 0

  const togglePlay = () => {
    const v = videoRef.current
    if (!v) return
    if (v.paused || v.ended) v.play().catch(() => setFailed(true))
    else v.pause()
  }

  // Playback speed cycles through SPEEDS on each tap; re-applied whenever a new
  // <video> element loads (the browser resets it to 1× on src change).
  useEffect(() => {
    if (videoRef.current) videoRef.current.playbackRate = rate
  }, [rate, src])

  const cycleSpeed = () => {
    const i = SPEEDS.indexOf(rate as typeof SPEEDS[number])
    setRate(SPEEDS[(i + 1) % SPEEDS.length])
  }

  const skip = (seconds: number) => {
    const v = videoRef.current
    if (!v || total <= 0) return
    v.currentTime = Math.min(total, Math.max(0, v.currentTime + seconds))
    setCurrent(v.currentTime)
    setSkipFlash(seconds < 0 ? 'back' : 'forward')
    if (skipFlashTimer.current) clearTimeout(skipFlashTimer.current)
    skipFlashTimer.current = setTimeout(() => setSkipFlash(null), 450)
  }

  const seekFromPointer = (e: PointerEvent<HTMLDivElement>) => {
    const v = videoRef.current
    const bar = barRef.current
    if (!v || !bar || total <= 0) return
    const rect = bar.getBoundingClientRect()
    const ratio = Math.min(1, Math.max(0, (e.clientX - rect.left) / rect.width))
    v.currentTime = ratio * total
    setCurrent(v.currentTime)
  }

  return (
    <div className="relative h-full w-full select-none bg-black" onClick={toggleChrome}>
      <video
        ref={videoRef}
        src={src}
        poster={poster ?? undefined}
        autoPlay={autoPlay}
        playsInline
        preload="metadata"
        className="h-full w-full object-contain"
        onLoadedMetadata={(e) => setDuration(Number.isFinite(e.currentTarget.duration) ? e.currentTarget.duration : 0)}
        onDurationChange={(e) => setDuration(Number.isFinite(e.currentTarget.duration) ? e.currentTarget.duration : 0)}
        onTimeUpdate={(e) => { if (!seeking.current) setCurrent(e.currentTarget.currentTime) }}
        onPlay={() => setPlaying(true)}
        onPause={() => setPlaying(false)}
        onEnded={() => setPlaying(false)}
        onWaiting={() => setBuffering(true)}
        onPlaying={() => { setBuffering(false); setPlaying(true) }}
        onCanPlay={() => setBuffering(false)}
        onVolumeChange={(e) => setMuted(e.currentTarget.muted)}
        onError={() => setFailed(true)}
      />

      {/* Double-tap the left/right half of the picture to skip ±10s, like YouTube/Instagram */}
      {!failed && (
        <div className="absolute inset-0 flex">
          <div
            className="h-full w-1/2"
            onDoubleClick={(e) => { e.stopPropagation(); skip(-SKIP_SECONDS) }}
          />
          <div
            className="h-full w-1/2"
            onDoubleClick={(e) => { e.stopPropagation(); skip(SKIP_SECONDS) }}
          />
        </div>
      )}

      {/* Brief ⟲10 / 10⟳ flash on double-tap skip */}
      {skipFlash && (
        <div className={`pointer-events-none absolute inset-y-0 flex w-1/3 items-center justify-center ${skipFlash === 'back' ? 'left-0' : 'right-0'}`}>
          <div className="flex flex-col items-center gap-1 rounded-full bg-black/55 p-4 text-white">
            {skipFlash === 'back' ? <RotateCcw className="h-6 w-6" /> : <RotateCw className="h-6 w-6" />}
            <span className="font-label-sm text-label-sm">{SKIP_SECONDS}s</span>
          </div>
        </div>
      )}

      {/* Centre state: error / buffering / big play button while paused */}
      {failed ? (
        <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-2 text-white/80">
          <AlertTriangle className="h-8 w-8" />
          <span className="font-body-sm text-body-sm">This video can't be played</span>
        </div>
      ) : buffering && playing ? (
        <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
          <Loader2 className="h-10 w-10 animate-spin text-white/90" />
        </div>
      ) : !playing ? (
        <button
          type="button"
          onClick={(e) => { e.stopPropagation(); togglePlay() }}
          className="absolute inset-0 m-auto flex h-16 w-16 items-center justify-center rounded-full bg-black/55 text-white transition-transform active:scale-95"
        >
          <Play className="ml-1 h-8 w-8" fill="currentColor" />
        </button>
      ) : null}

      {/* Control bar */}
      {!hidden && !failed && (
        <div
          className="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/75 to-transparent px-3 pt-10"
          style={{ paddingBottom: 12 + bottomOffset }}
          onClick={(e) => e.stopPropagation()}
        >
          {/* Seek bar */}
          <div
            ref={barRef}
            className="group relative flex h-5 cursor-pointer touch-none items-center"
            onPointerDown={(e) => { seeking.current = true; e.currentTarget.setPointerCapture(e.pointerId); seekFromPointer(e) }}
            onPointerMove={(e) => { if (seeking.current) seekFromPointer(e) }}
            onPointerUp={() => { seeking.current = false }}
            onPointerCancel={() => { seeking.current = false }}
          >
            <div className="h-1 w-full rounded-full bg-white/30">
              <div className="h-full rounded-full bg-primary" style={{ width: `${progress}%` }} />
            </div>
            <div
              className="absolute h-3 w-3 -translate-x-1/2 rounded-full bg-primary shadow"
              style={{ left: `${progress}%` }}
            />
          </div>

          <div className="mt-1 flex items-center gap-3 text-white">
            <button
              type="button"
              onClick={() => skip(-SKIP_SECONDS)}
              className="flex items-center justify-center p-1 active:scale-95"
              aria-label={`Back ${SKIP_SECONDS}s`}
            >
              <RotateCcw className="h-[18px] w-[18px]" />
            </button>
            <button type="button" onClick={togglePlay} className="flex items-center justify-center p-1 active:scale-95">
              {playing ? <Pause className="h-5 w-5" fill="currentColor" /> : <Play className="h-5 w-5" fill="currentColor" />}
            </button>
            <button
              type="button"
              onClick={() => skip(SKIP_SECONDS)}
              className="flex items-center justify-center p-1 active:scale-95"
              aria-label={`Forward ${SKIP_SECONDS}s`}
            >
              <RotateCw className="h-[18px] w-[18px]" />
            </button>
            <span className="font-label-sm text-label-sm tabular-nums">
              {formatDuration(current)} / {formatDuration(total)}
            </span>
            <button
              type="button"
              onClick={cycleSpeed}
              className="ml-auto flex min-w-[34px] items-center justify-center rounded-md bg-white/15 px-1.5 py-0.5 font-label-sm text-label-sm active:scale-95"
            >
              {rate}×
            </button>
            <button
              type="button"
              onClick={() => { const v = videoRef.current; if (v) v.muted = !v.muted }}
              className="flex items-center justify-center p-1 active:scale-95"
            >
              {muted ? <VolumeX className="h-5 w-5" /> : <Volume2 className="h-5 w-5" />}
            </button>
          </div>
        </div>
      )}
    </div>
  )
}

export default VideoPlayer
