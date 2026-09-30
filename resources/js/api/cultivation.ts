import client from './client'

export interface CultivationRealmGuide {
  id: string
  name: string
  full_name: string
  slug: string
  level_start: number
  level_end: number
  stage_required: number
  sort_order: number
}

export interface CultivationEraGuide {
  id: string
  name: string
  resource_name: string
  resource_slug: string
  realms: CultivationRealmGuide[]
}

/**
 * Data referensi (5 era + 20 realm) untuk halaman panduan publik —
 * TIDAK butuh login, endpoint ini publik (lihat routes/cultivation.php).
 */
export async function fetchCultivationGuide(): Promise<CultivationEraGuide[]> {
  const res = await client.get<{ success: boolean; data: CultivationEraGuide[] }>('/cultivation/guide')
  return res.data.data || []
}
