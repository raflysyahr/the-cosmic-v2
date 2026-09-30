import { useState, useEffect } from 'react'
import { Link, router } from '@inertiajs/react'
import { Eye, EyeOff } from 'lucide-react'
import Layout from '../Components/layout/Layout'
import { useAuth } from '../contexts/AuthContext'

export default function Login() {
  const { login, user, loading: authLoading } = useAuth()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [showPw, setShowPw] = useState(false)
  const [remember, setRemember] = useState(false)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    if (!authLoading && user) {
      router.visit('/', { replace: true })
    }
  }, [user, authLoading])

  if (authLoading) return null
  if (user) return null

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!email.trim() || !password) {
      setError('Please fill in all fields')
      return
    }
    setLoading(true)
    setError('')
    try {
      await login(email.trim(), password, remember)
      router.visit('/', { replace: true })
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Login failed')
    } finally {
      setLoading(false)
    }
  }

  return (
    <Layout>
      <div className="mx-auto flex min-h-[60vh] max-w-sm flex-col justify-center px-4 py-12">
        <h1 className="mb-2 text-center text-lg font-bold text-white">Sign In</h1>
        <p className="mb-8 text-center text-xs text-[#555]">
          Sign in to access your bookmarks and reading history
        </p>

        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <div>
            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-[#555]">
              Email
            </label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="you@example.com"
              autoComplete="email"
              className="h-9 w-full border border-[#2A2A2A] bg-[#111] px-3 text-xs text-white placeholder:text-[#555] transition-colors focus:border-white/30 focus:outline-none"
            />
          </div>

          <div>
            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-[#555]">
              Password
            </label>
            <div className="relative">
              <input
                type={showPw ? 'text' : 'password'}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="Enter your password"
                autoComplete="current-password"
                className="h-9 w-full border border-[#2A2A2A] bg-[#111] px-3 pr-9 text-xs text-white placeholder:text-[#555] transition-colors focus:border-white/30 focus:outline-none"
              />
              <button
                type="button"
                onClick={() => setShowPw(!showPw)}
                className="absolute right-2 top-1/2 -translate-y-1/2 text-[#555] transition-colors hover:text-white"
                tabIndex={-1}
              >
                {showPw ? <EyeOff className="h-3.5 w-3.5" /> : <Eye className="h-3.5 w-3.5" />}
              </button>
            </div>
          </div>

          <label className="flex cursor-pointer items-center gap-2 text-xs text-[#555]">
            <input
              type="checkbox"
              checked={remember}
              onChange={(e) => setRemember(e.target.checked)}
              className="h-3.5 w-3.5 accent-white"
            />
            Remember me
          </label>

          {error && (
            <p className="text-xs text-red-400">{error}</p>
          )}

          <button
            type="submit"
            disabled={loading}
            className="mt-2 h-9 bg-white text-xs font-bold text-black transition-colors hover:bg-[#ccc] disabled:opacity-50"
          >
            {loading ? 'Signing in…' : 'Sign In'}
          </button>
        </form>

        <p className="mt-6 text-center text-[10px] text-[#555]">
          Don't have an account?{' '}
          <Link href="/register" className="text-[#999] underline transition-colors hover:text-white">
            Sign Up
          </Link>
        </p>
        <p className="mt-2 text-center text-[10px] text-[#555]">
          <Link href="/" className="text-[#777] underline transition-colors hover:text-white">
            Back to Home
          </Link>
        </p>
      </div>
    </Layout>
  )
}
