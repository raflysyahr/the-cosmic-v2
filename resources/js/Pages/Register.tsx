import { useState, useEffect } from 'react'
import { Link, router } from '@inertiajs/react'
import { Eye, EyeOff } from 'lucide-react'
import Layout from '../Components/layout/Layout'
import { useAuth } from '../contexts/AuthContext'

export default function Register() {
  const { register, user, loading: authLoading } = useAuth()
  const [name, setName] = useState('')
  const [username, setUsername] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [showPw, setShowPw] = useState(false)
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
    if (!name.trim() || !username.trim() || !email.trim() || !password) {
      setError('Please fill in all fields')
      return
    }
    if (password.length < 6) {
      setError('Password must be at least 6 characters')
      return
    }
    setLoading(true)
    setError('')
    try {
      await register(name.trim(), username.trim(), email.trim(), password)
      router.visit('/', { replace: true })
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Registration failed')
    } finally {
      setLoading(false)
    }
  }

  return (
    <Layout>
      <div className="mx-auto flex min-h-[60vh] max-w-sm flex-col justify-center px-4 py-12">
        <h1 className="mb-2 text-center text-lg font-bold text-white">Create Account</h1>
        <p className="mb-8 text-center text-xs text-[#555]">
          Sign up to save your bookmarks and reading progress
        </p>

        <form onSubmit={handleSubmit} className="flex flex-col gap-4">
          <div>
            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-[#555]">
              Display Name
            </label>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Your display name"
              className="h-9 w-full border border-[#2A2A2A] bg-[#111] px-3 text-xs text-white placeholder:text-[#555] transition-colors focus:border-white/30 focus:outline-none"
            />
          </div>

          <div>
            <label className="mb-1 block text-[10px] font-semibold uppercase tracking-wider text-[#555]">
              Username
            </label>
            <input
              type="text"
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              placeholder="Your username"
              autoComplete="username"
              className="h-9 w-full border border-[#2A2A2A] bg-[#111] px-3 text-xs text-white placeholder:text-[#555] transition-colors focus:border-white/30 focus:outline-none"
            />
          </div>

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
                placeholder="At least 6 characters"
                autoComplete="new-password"
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

          {error && (
            <p className="text-xs text-red-400">{error}</p>
          )}

          <button
            type="submit"
            disabled={loading}
            className="mt-2 h-9 bg-white text-xs font-bold text-black transition-colors hover:bg-[#ccc] disabled:opacity-50"
          >
            {loading ? 'Creating account…' : 'Create Account'}
          </button>
        </form>

        <p className="mt-6 text-center text-[10px] text-[#555]">
          Already have an account?{' '}
          <Link href="/login" className="text-[#999] underline transition-colors hover:text-white">
            Sign In
          </Link>
        </p>
      </div>
    </Layout>
  )
}
