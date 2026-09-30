// Small formatting helper shared by file/video message cards.
//
// "Downloaded" state used to be tracked here via a separate localStorage
// flag; it's now derived directly from the browser's media cache (see
// mediaCache.ts) so there's one source of truth — actually having the bytes.

export function formatFileSize(bytes?: number | null): string {
  if (!bytes || bytes < 0) return ''
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}
