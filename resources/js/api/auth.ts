import client from './client'
import axios from 'axios'

export interface User {
  id: string
  username: string
  email: string
  displayName: string
  avatarUrl: string | null
  role: string
  status: string
}

export interface AuthResponse {
  message: string
  user: User
}

async function ensureCsrf(): Promise<void> {
  await axios.get('/sanctum/csrf-cookie')
}

export async function login(email: string, password: string, remember = false): Promise<AuthResponse> {
  await ensureCsrf()
  const res = await client.post('/login', { email, password, remember })
  return res.data as AuthResponse
}

export async function register(name: string, username: string, email: string, password: string): Promise<AuthResponse> {
  await ensureCsrf()
  const res = await client.post('/register', { name, username, email, password })
  return res.data as AuthResponse
}

export async function getMe(): Promise<User | null> {
  try {
    const res = await client.get('/user')
    return (res.data as { user: User }).user
  } catch {
    return null
  }
}

export async function logout(): Promise<void> {
  await client.post('/logout')
}
