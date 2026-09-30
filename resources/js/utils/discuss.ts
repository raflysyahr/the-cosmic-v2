/** Extracts a human-readable error message from a failed API response,
 * falling back to a generic message if the response body doesn't match
 * the expected Laravel validation-error shape. */
export async function extractErrorMessage(response: Response, fallback: string): Promise<string> {
  const body = await response.json().catch(() => null)
  const firstError = body?.errors ? Object.values(body.errors)[0] : null
  if (Array.isArray(firstError) && typeof firstError[0] === 'string') return firstError[0]
  if (typeof body?.message === 'string') return body.message
  return fallback
}

/** True if the given ISO 8601 timestamp represents a moment still in the future. */
export function isCurrentlyMuted(mutedUntil: string | null): boolean {
  if (!mutedUntil) return false
  const ts = new Date(mutedUntil).getTime()
  return !Number.isNaN(ts) && ts > Date.now()
}
