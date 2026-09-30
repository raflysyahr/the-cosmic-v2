import client from './client'
import type { Genre, ApiResponse } from '../types'

export async function fetchGenres(): Promise<Genre[]> {
  const res = await client.get<ApiResponse<Genre[]>>('/v1/genres')
  return res.data.data || []
}
