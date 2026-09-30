// Helpers for photo / video messages: reading a video's duration + poster frame
// in the browser (the server has no ffmpeg), formatting, and reply thumbnails.

export interface VideoMeta {
  duration: number | null
  width: number | null
  height: number | null
  /** JPEG poster frame, uploaded next to the video. null if it couldn't be decoded. */
  thumbnail: Blob | null
}

export interface VideoInfo {
  name?: string
  size?: number
  mime?: string
  duration?: number | null
  width?: number | null
  height?: number | null
  thumbnail?: string | null
}

export const MAX_VIDEO_BYTES = 50 * 1024 * 1024 // keep in sync with SendMessageRequest::VIDEO_RULES
export const VIDEO_ACCEPT = 'video/mp4,video/quicktime,video/webm'

const THUMB_MAX_WIDTH = 480
const META_TIMEOUT_MS = 8000

/**
 * Loads the video off-screen to read its duration/dimensions and grab a poster
 * frame. Always resolves — with nulls if the browser can't decode the file —
 * so a weird codec never blocks sending.
 */
export function readVideoMeta(file: File): Promise<VideoMeta> {
  return new Promise((resolve) => {
    const url = URL.createObjectURL(file)
    const video = document.createElement('video')
    video.preload = 'metadata'
    video.muted = true
    video.playsInline = true

    const info: VideoMeta = { duration: null, width: null, height: null, thumbnail: null }
    let done = false

    const finish = () => {
      if (done) return
      done = true
      clearTimeout(timer)
      video.removeAttribute('src')
      video.load()
      URL.revokeObjectURL(url)
      resolve(info)
    }
    const timer = setTimeout(finish, META_TIMEOUT_MS)

    video.onerror = finish
    video.onloadedmetadata = () => {
      // Some webm files report Infinity — treat that as unknown.
      info.duration = Number.isFinite(video.duration) ? video.duration : null
      info.width = video.videoWidth || null
      info.height = video.videoHeight || null
      // Seek slightly in so the poster isn't a black first frame.
      video.currentTime = info.duration ? Math.min(1, info.duration / 4) : 0.1
    }
    video.onseeked = () => {
      try {
        const w = video.videoWidth
        const h = video.videoHeight
        if (!w || !h) return finish()
        const scale = Math.min(1, THUMB_MAX_WIDTH / w)
        const canvas = document.createElement('canvas')
        canvas.width = Math.round(w * scale)
        canvas.height = Math.round(h * scale)
        canvas.getContext('2d')?.drawImage(video, 0, 0, canvas.width, canvas.height)
        canvas.toBlob((blob) => {
          info.thumbnail = blob
          finish()
        }, 'image/jpeg', 0.75)
      } catch {
        finish()
      }
    }
    video.src = url
  })
}

/** 75 → "1:15", 3725 → "1:02:05" */
export function formatDuration(seconds?: number | null): string {
  if (seconds == null || !Number.isFinite(seconds) || seconds < 0) return '0:00'
  const total = Math.floor(seconds)
  const h = Math.floor(total / 3600)
  const m = Math.floor((total % 3600) / 60)
  const s = total % 60
  const ss = String(s).padStart(2, '0')
  return h > 0 ? `${h}:${String(m).padStart(2, '0')}:${ss}` : `${m}:${ss}`
}

interface ThumbSource {
  type?: string
  attachments?: string[]
  metadata?: { video?: VideoInfo; thumbnail?: string | null } | null
  /** Already resolved by the server for `reply_to` payloads. */
  thumbnail?: string | null
}

/**
 * Small preview for a replied-to photo/video message, or null for anything else.
 * `url` may be null for a video whose poster frame couldn't be generated.
 */
export function getReplyThumb(m?: ThumbSource | null): { url: string | null; isVideo: boolean } | null {
  if (!m) return null
  if (m.type === 'image') return { url: m.thumbnail ?? m.metadata?.thumbnail ?? m.attachments?.[0] ?? null, isVideo: false }
  if (m.type === 'video') return { url: m.thumbnail ?? m.metadata?.video?.thumbnail ?? null, isVideo: true }
  return null
}
