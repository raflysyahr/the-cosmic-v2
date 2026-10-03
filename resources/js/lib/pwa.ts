/// <reference types="vite/client" />

import { useSyncExternalStore } from 'react'

/**
 * Dukungan PWA di sisi client: registrasi service worker + alur "Install app".
 * Service worker-nya sendiri ada di public/sw.js (harus di root supaya scope-nya '/').
 */

// --- Service worker ---------------------------------------------------------

/**
 * Hanya di build produksi. Di dev (Vite HMR) service worker justru merusak:
 * asset dev tidak boleh di-cache dan HMR bergantung pada request langsung.
 * Browser juga hanya mengizinkan SW di HTTPS atau localhost — lewat IP LAN
 * http:// registrasi akan ditolak diam-diam.
 */
export function registerServiceWorker() {
  if (!import.meta.env.PROD || typeof navigator === 'undefined' || !('serviceWorker' in navigator)) return

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
