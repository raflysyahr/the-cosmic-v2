// Deklarasi tipe global untuk window.Echo & window.Pusher, di-set di
// bootstrap.js. Tanpa ini, `window.Echo.join(...)` / `.private(...)` di
// Room.tsx dan Layout.tsx gagal type-check di bawah "strict": true
// (tsconfig.json) karena `Echo`/`Pusher` bukan properti bawaan `Window`.
import type Echo from 'laravel-echo'
import type Pusher from 'pusher-js'

declare global {
  interface Window {
    Echo: Echo<'reverb'>
    Pusher: typeof Pusher
  }
}

export {}
