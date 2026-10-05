/// <reference types="vite/client" />

import { useSyncExternalStore } from 'react'

/**
 * Dukungan PWA di sisi client: registrasi service worker + alur "Install app".
 * Service worker-nya sendiri ada di public/sw.js (harus di root supaya scope-nya '/').
 */

// --- Service worker ---------------------------------------------------------

/**
 * Default: service worker hanya aktif di build produksi.
 *
 * Di dev (npm run dev) SW sengaja TIDAK didaftarkan, dan SW + cache `cosmic-*` sisa
 * pengujian build produksi di origin yang sama dibuang. Tanpa ini SW lama tetap
 * mengendalikan halaman dan menyajikan font/ikon basi, sehingga perubahan tidak
 * langsung terlihat di app yang terpasang.
 *
 * Mau menguji SW/offline sambil tetap pakai Vite HMR? Set `VITE_PWA_DEV=true` di .env
 * lalu restart `npm run dev`. Aman untuk HMR karena SW hanya mencegat /build/assets,
 * /fonts, /icons, dan navigasi (aset Vite dev ada di origin :5173, tidak disentuh).
 *
 * Browser hanya mengizinkan SW di HTTPS atau localhost — lewat IP LAN http://
 * registrasi akan ditolak diam-diam.
 */
export function registerServiceWorker() {
  if (typeof navigator === 'undefined' || !('serviceWorker' in navigator)) return

  const enabled = import.meta.env.PROD || import.meta.env.VITE_PWA_DEV === 'true'

  if (!enabled) {
    navigator.serviceWorker.getRegistrations()
      .then((registrations) => registrations.forEach((r) => r.unregister()))
      .catch(() => {})
    if ('caches' in window) {
      caches.keys()
        .then((names) => names.filter((n) => n.startsWith('cosmic-')).forEach((n) => caches.delete(n)))
        .catch(() => {})
    }
    return
  }

  window.addEventListener('load', () => {
    navigator.serviceWorker
      .register('/sw.js', { scope: '/', updateViaCache: 'none' })
      .catch((err) => console.warn('Service worker registration failed:', err))
  })
}

// --- Install prompt ---------------------------------------------------------

interface BeforeInstallPromptEvent extends Event {
  prompt: () => Promise<void>
  userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>
}

let deferredPrompt: BeforeInstallPromptEvent | null = null
const listeners = new Set<() => void>()
const notify = () => listeners.forEach((fn) => fn())

/**
 * Harus dipanggil sedini mungkin (di app.jsx, bukan di komponen): Chrome
 * menembakkan 'beforeinstallprompt' sekali, sering sebelum React sempat mount.
 */
export function initInstallPrompt() {
  if (typeof window === 'undefined') return

  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault()
    deferredPrompt = e as BeforeInstallPromptEvent
    notify()
  })
  window.addEventListener('appinstalled', () => {
    deferredPrompt = null
    notify()
  })
}

function isStandalone(): boolean {
  return window.matchMedia('(display-mode: standalone)').matches
    || (navigator as Navigator & { standalone?: boolean }).standalone === true
}

function isIos(): boolean {
  const ua = navigator.userAgent
  // iPadOS 13+ mengaku sebagai Mac; bedakan lewat layar sentuh.
  return /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
}

export interface PwaInstallState {
  /** Browser menyediakan prompt install native (Chrome/Edge/Android). */
  canPrompt: boolean
  /** iOS: tidak ada prompt native, user harus lewat Share → Add to Home Screen. */
  needsManualInstall: boolean
  /** Sudah berjalan sebagai app terpasang. */
  installed: boolean
  promptInstall: () => Promise<void>
}

const subscribe = (fn: () => void) => {
  listeners.add(fn)
  return () => { listeners.delete(fn) }
}

// useSyncExternalStore butuh snapshot yang stabil: pakai string primitif.
const getSnapshot = () => `${deferredPrompt ? 1 : 0}`
const getServerSnapshot = () => '0'

async function promptInstall() {
  if (!deferredPrompt) return
  const event = deferredPrompt
  // Event hanya boleh dipakai sekali.
  deferredPrompt = null
  notify()
  await event.prompt()
  await event.userChoice
}

export function usePwaInstall(): PwaInstallState {
  const snapshot = useSyncExternalStore(subscribe, getSnapshot, getServerSnapshot)
  const canPrompt = snapshot === '1'

  if (typeof window === 'undefined') {
    return { canPrompt: false, needsManualInstall: false, installed: false, promptInstall }
  }

  const installed = isStandalone()
  return {
    canPrompt: canPrompt && !installed,
    needsManualInstall: !installed && !canPrompt && isIos(),
    installed,
    promptInstall,
  }
}
