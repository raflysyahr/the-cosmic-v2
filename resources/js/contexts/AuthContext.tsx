import { createContext, useContext, useState, useEffect, useCallback, type ReactNode } from 'react'
import * as authApi from '../api/auth'

interface User {
  id: string
  username: string
  email: string
  displayName: string
  avatarUrl: string | null
  role: string
  status: string
}

interface AuthContextValue {
  user: User | null
  loading: boolean
  login: (email: string, password: string, remember?: boolean) => Promise<authApi.AuthResponse>
  register: (name: string, username: string, email: string, password: string) => Promise<authApi.AuthResponse>
  logout: () => Promise<void>
  setUser: (user: User | null) => void
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    authApi.getMe().then((u) => {
      if (u) setUser(u)
      setLoading(false)
    }).catch(() => setLoading(false))
  }, [])

  const login = useCallback(async (email: string, password: string, remember = false) => {
    const res = await authApi.login(email, password, remember)
    setUser(res.user as User)
    return res
  }, [])

  const register = useCallback(async (name: string, username: string, email: string, password: string) => {
    const res = await authApi.register(name, username, email, password)
    setUser(res.user as User)
    return res
  }, [])

  const logout = useCallback(async () => {
    await authApi.logout()
    setUser(null)
  }, [])

  return (
    <AuthContext.Provider value={{ user, loading, login, register, logout, setUser }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within AuthProvider')
  return ctx
}
