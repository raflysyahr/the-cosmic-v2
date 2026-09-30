# AGENTS.md — UI Discuss
> Laravel + Inertia React · Tailwind CSS
> Baca seluruh file ini sebelum menulis satu baris kode pun.

---

## Konteks Project

Website web comic bernama **"The Cosmic"**. Menambahkan UI untuk module baru:
- **Discuss** — halaman group chat real-time per komik (ala Telegram/WA)

Semua UI harus **konsisten 100% dengan desain yang sudah ada**. Tidak boleh mengarang desain baru.

---

## ⚠️ File yang TIDAK Boleh Disentuh

File berikut sudah ada dan **jangan di-edit, jangan di-overwrite, jangan dihapus**:

```
resources/js/Pages/Login.tsx       ← SUDAH ADA, SKIP
resources/js/Pages/Register.tsx    ← SUDAH ADA, SKIP
resources/js/Pages/Home.tsx
resources/js/Pages/AllComics.tsx
resources/js/Pages/Detail.tsx
resources/js/Pages/Reader.tsx
resources/js/Pages/Search.tsx
resources/js/Pages/Bookmarks.tsx
resources/js/Pages/GenrePage.tsx
resources/js/Pages/About.tsx
resources/js/Pages/Contact.tsx
resources/js/Pages/DMCA.tsx
resources/js/Pages/Privacy.tsx
resources/js/Pages/Maintenance.tsx
resources/js/Pages/NotFound.tsx
resources/js/Pages/AccessDenied.tsx
resources/js/Pages/ServerError.tsx
resources/js/Pages/SessionExpired.tsx
```

Jika task mengharuskan modifikasi file di atas, **hanya tambahkan elemen baru** — tidak mengubah atau menghapus kode yang sudah ada.

---

## Design System — Wajib Diikuti

Analisis dari kode `Home.tsx` dan screenshot yang ada.

### Warna

```
Background utama   : #000000 atau #0a0a0a (hitam murni)
Surface / card     : #111111
Border             : #2A2A2A
Border hover       : rgba(255,255,255,0.2)
Surface sekunder   : #1A1A1A

Teks utama         : #FFFFFF
Teks sekunder      : #999999
Teks muted         : #777777
Teks sangat muted  : #555555

Aksen / highlight  : #FFFFFF (putih, bukan warna lain)
Tag badge bg       : #FFFFFF
Tag badge teks     : #000000 (kontras tinggi)
Badge "NEW"        : font-bold, warna putih, tanpa background
```

### Tipografi

```
Font brand (navbar)  : font monospace / pixel-style → "The Cosmic" (sudah ada di Layout)
Section label        : text-xs font-bold tracking-wider uppercase text-[#555]
Judul konten         : font-bold text-white
Sub judul / meta     : text-sm text-[#777] atau text-[#555]
Body text            : text-sm text-[#999]
```

### Spacing & Layout

```
Container utama  : mx-auto max-w-7xl px-4 py-6
Section gap      : mb-8
Card padding     : p-3
Gap antar item   : gap-px (bukan gap-2 atau gap-4) dengan bg-[#2A2A2A] sebagai "gutter"
Grid komik       : grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5
```

### Komponen yang Sudah Ada — Gunakan Ulang

```
Layout           : import Layout from '../Components/layout/Layout'
Skeleton         : import Skeleton from '../Components/ui/Skeleton'
ComicGrid        : import ComicGrid from '../Components/comic/ComicGrid'
Pagination       : import Pagination from '../Components/comic/Pagination'
```

### Pola Border & Card

```tsx
// Card standar project
<div className="border border-[#2A2A2A] bg-[#111] transition-colors hover:border-white/20">

// Badge / tag (MANGA, MANHWA, HOT, NEW, ONGOING)
<span className="bg-white text-black text-xs font-bold px-2 py-0.5">ONGOING</span>

// Section heading
<h2 className="text-xs font-bold tracking-wider uppercase text-[#555]">
  Section Title
</h2>
```

### Input & Form Style

Belum ada di screenshot, tapi harus konsisten:
```tsx
// Input field
<input className="w-full bg-[#111] border border-[#2A2A2A] text-white text-sm px-4 py-3
                  placeholder:text-[#555] focus:outline-none focus:border-white/40
                  transition-colors" />

// Primary button
<button className="w-full bg-white text-black text-sm font-bold py-3
                   hover:bg-[#ccc] transition-colors">
  SUBMIT
</button>

// Secondary / ghost button
<button className="border border-[#2A2A2A] text-white text-sm px-4 py-2
                   hover:border-white/40 transition-colors">
  Cancel
</button>

// Link teks
<a className="text-[#777] hover:text-white text-sm transition-colors">
  Forgot password?
</a>
```

### Scroll Horizontal (untuk list)

```tsx
<div className="flex gap-2 overflow-x-auto pb-2 scrollbar-none">
```

### Skeleton Loading

```tsx
// Gunakan komponen Skeleton yang sudah ada
<Skeleton className="h-4 w-full" />
<Skeleton className="aspect-[720/1013]" />
```

---

## Aturan Wajib Menulis Kode

1. **Selalu bungkus halaman dengan `<Layout>`** — tidak ada halaman tanpa Layout kecuali halaman auth full-page
2. **Tidak ada warna selain yang ada di Design System di atas** — tidak boleh pakai warna biru, hijau, merah, dll
3. **Tidak ada rounded-xl, rounded-lg** — project ini tidak memakai border radius besar. Gunakan `rounded` (4px) maksimal, atau tidak sama sekali
4. **Semua label section pakai uppercase tracking-wider text-[#555]**
5. **Gunakan `gap-px` + `bg-[#2A2A2A]`** pada grid, bukan `gap-2` atau `gap-4`
6. **Tidak ada shadow** (`shadow-lg`, dll) — desain flat, tidak ada bayangan
7. **Transition selalu `transition-colors`** — bukan `transition-all`
8. **Icon** — gunakan `lucide-react` yang sudah tersedia di project
9. **Inertia Link** — gunakan `import { Link } from '@inertiajs/react'` untuk navigasi internal, bukan `<a>` biasa
10. **Semua file halaman** taruh di `resources/js/Pages/Discuss/`
11. **Semua komponen reusable** taruh di `resources/js/Components/discuss/`

---

## Struktur File UI yang Akan Dibuat

```
resources/js/
├── Pages/
│   └── Discuss/
│       ├── Index.tsx          # Daftar semua room
│       └── Room.tsx           # Halaman chat dalam room
│
└── Components/
    └── discuss/
        ├── MessageList.tsx    # Daftar pesan dengan scroll
        ├── MessageItem.tsx    # Satu item pesan
        ├── MessageInput.tsx   # Input kirim pesan
        ├── MemberSidebar.tsx  # Sidebar daftar member (optional/collapsible)
        ├── EmotePicker.tsx    # Picker emot custom
        ├── RankBadge.tsx      # Badge pangkat user
        └── ReactionBar.tsx    # Bar reaksi emot di bawah pesan

app/Modules/Discuss/Http/Controllers/
│   ├── PageController.php     # BARU — return Inertia::render() untuk halaman
│   └── (controller API yang sudah ada tetap tidak berubah)

routes/
├── web.php                    # Tambah route Inertia untuk Discuss (GET)
└── channels.php               # Tambah otorisasi broadcast channel

resources/js/
└── bootstrap.ts               # Tambah konfigurasi Laravel Echo + Reverb
```

---

## Task List UI

Kerjakan berurutan. Selesaikan satu task sebelum lanjut ke task berikutnya.

> **INGAT:** Tidak ada task yang menyentuh file Auth (`Login.tsx`, `Register.tsx`) atau file Pages yang sudah ada.

---

### FASE 1 — Komponen Discuss

#### Task 1 — Komponen `RankBadge`

Buat `resources/js/Components/discuss/RankBadge.tsx`

Props: `{ name: string, color: string, iconUrl?: string }`

Spesifikasi:
- Badge kecil inline
- Background transparan, border sesuai `color` prop
- Teks `name` berwarna sesuai `color` prop
- Ukuran: `text-[10px] font-bold tracking-wider uppercase px-1.5 py-0.5 border`
- Jika ada `iconUrl`, tampilkan gambar 10x10px sebelum teks

```tsx
// Contoh output
<span style={{ borderColor: color, color }} className="text-[10px] font-bold tracking-wider uppercase px-1.5 py-0.5 border inline-flex items-center gap-1">
  {iconUrl && <img src={iconUrl} className="w-2.5 h-2.5" />}
  {name}
</span>
```

---

#### Task 2 — Komponen `EmotePicker`

Buat `resources/js/Components/discuss/EmotePicker.tsx`

Props: `{ emotes: Emote[], onSelect: (emote: Emote) => void, onClose: () => void }`

Spesifikasi:
- Popup kecil, muncul di atas input
- Background `#111`, border `#2A2A2A`
- Grid emot: `grid-cols-8 gap-1 p-2`
- Tiap emot: gambar 28x28px, hover `bg-[#1A1A1A]`, `rounded` (kecil)
- Search input di atas grid: `bg-[#000] border-b border-[#2A2A2A] px-3 py-2 text-xs placeholder:text-[#555] focus:outline-none w-full text-white`
- Klik di luar picker → `onClose()`
- Tidak ada animasi kompleks — cukup conditional render

---

#### Task 3 — Komponen `ReactionBar`

Buat `resources/js/Components/discuss/ReactionBar.tsx`

Props: `{ reactions: GroupedReaction[], onToggle: (emoteId: string) => void, currentUserId: string }`

Type:
```ts
type GroupedReaction = {
  emoteId: string
  emoteCode: string
  imageUrl: string
  count: number
  userIds: string[]
}
```

Spesifikasi:
- Flex row, gap kecil, flex-wrap
- Tiap reaction: `flex items-center gap-1 px-2 py-0.5 border text-xs`
  - Jika user sudah react: `border-white/40 bg-[#1A1A1A] text-white`
  - Jika belum: `border-[#2A2A2A] text-[#777] hover:border-white/20`
- Isi: gambar emot 14x14px + count
- Klik → `onToggle(emoteId)`

---

#### Task 4 — Komponen `MessageItem`

Buat `resources/js/Components/discuss/MessageItem.tsx`

Props: `{ message: Message, currentUserId: string, onReply: (msg: Message) => void, onDelete: (id: string) => void, onReact: (msgId: string, emoteId: string) => void }`

Type Message:
```ts
type Message = {
  id: string
  body: string | null
  type: 'text' | 'image' | 'sticker' | 'system'
  user: { id: string, display_name: string, avatar_url: string | null, rank?: { name: string, color: string } }
  reply_to?: { id: string, body: string, user: { display_name: string } }
  attachments: string[]
  reactions: GroupedReaction[]
  is_edited: boolean
  is_deleted: boolean
  created_at: string
}
```

Spesifikasi layout:

```
[Avatar] [display_name] [RankBadge] [timestamp]
         [reply_to preview — jika ada]
         [body teks atau gambar]
         [ReactionBar — jika ada reactions]
         [action bar — muncul saat hover: Reply | React | Delete(jika owner)]
```

Detail:
- Avatar: lingkaran `w-8 h-8 bg-[#1A1A1A]`, jika tidak ada avatar tampilkan inisial huruf pertama display_name, `text-xs text-[#555]`
- `display_name`: `text-sm font-bold text-white`
- Timestamp: `text-[10px] text-[#555]`
- `is_deleted`: tampilkan `text-xs italic text-[#555]` — *"Message deleted"* — tidak tampilkan body
- `is_edited`: tampilkan teks `(edited)` setelah body, `text-[10px] text-[#555]`
- Reply preview: strip kiri `border-l-2 border-[#2A2A2A] pl-2 mb-1`, teks `text-xs text-[#555]` — `@username: preview body`
- Gambar attachment: `max-w-[240px]`, klik untuk buka full
- System message: centered, `text-xs text-[#555] italic`, tidak ada avatar
- Action bar (hover): `flex gap-3 mt-1`, icon-only dari lucide-react ukuran 14px warna `#555`, hover warna putih

---

#### Task 5 — Komponen `MessageList`

Buat `resources/js/Components/discuss/MessageList.tsx`

Props: `{ messages: Message[], currentUserId: string, onReply: fn, onDelete: fn, onReact: fn, loading: boolean }`

Spesifikasi:
- Container: `flex flex-col-reverse overflow-y-auto` — pesan terbaru di bawah
- Auto scroll ke bawah saat ada pesan baru (`useEffect` + `useRef` ke elemen bawah)
- Loading state: tampilkan 5x `Skeleton` untuk tiap pesan (`h-12 w-full`)
- Grouping tanggal: jika hari berbeda antar pesan, tampilkan separator tanggal — centered, `text-[10px] text-[#555] uppercase tracking-wider`, garis kiri-kanan tipis `border-[#2A2A2A]`
- Render `<MessageItem>` untuk tiap pesan

---

#### Task 6 — Komponen `MessageInput`

Buat `resources/js/Components/discuss/MessageInput.tsx`

Props: `{ onSend: (body: string, replyToId?: string) => void, replyTo?: Message, onCancelReply: () => void, emotes: Emote[], disabled?: boolean, disabledReason?: string }`

Spesifikasi:
- Sticky di bagian bawah layar: `sticky bottom-0 bg-black border-t border-[#2A2A2A]`
- Jika ada `replyTo`: tampilkan preview reply di atas input
  - `flex items-center justify-between px-4 py-2 border-b border-[#2A2A2A] bg-[#111]`
  - Teks: `text-xs text-[#555]` — `Replying to @username`
  - Button X untuk cancel: icon `X` lucide 12px warna `#555`
- Input area: `flex items-end gap-2 px-4 py-3`
  - `textarea` auto-resize (1–5 baris), `bg-transparent border-0 text-sm text-white placeholder:text-[#555] resize-none focus:outline-none flex-1`
  - Button emot: icon `Smile` lucide 18px warna `#555`, hover putih → buka `EmotePicker`
  - Button attach gambar: icon `Image` lucide 18px warna `#555`
  - Button send: icon `Send` lucide 18px — aktif putih jika ada teks, `#555` jika kosong
- Jika `disabled`: tampilkan pesan `disabledReason` di dalam input area, semua tombol disabled
- Enter kirim pesan, Shift+Enter newline

---

#### Task 7 — Komponen `MemberSidebar`

Buat `resources/js/Components/discuss/MemberSidebar.tsx`

Props: `{ members: Member[], currentUserId: string, onKick: fn, onMute: fn, onBan: fn }`

Type Member:
```ts
type Member = {
  userId: string
  displayName: string
  avatarUrl: string | null
  role: 'member' | 'moderator' | 'admin'
  rank: { name: string, color: string } | null
  xpPoints: number
  isOnline: boolean
}
```

Spesifikasi:
- Sidebar kanan, lebar `w-64`, `bg-[#0a0a0a] border-l border-[#2A2A2A]`
- Header: `MEMBERS (n)` — uppercase tracking-wider text-xs text-[#555], padding `px-4 py-3 border-b border-[#2A2A2A]`
- Grouping: Admin & Moderator di atas (dengan label), Member di bawah
- Tiap member row: `flex items-center gap-3 px-4 py-2 hover:bg-[#111] transition-colors`
  - Avatar circle `w-7 h-7`
  - Online dot: `w-2 h-2 rounded-full` — `bg-white` jika online, `bg-[#333]` jika offline
  - `display_name` text-sm text-white
  - `RankBadge` jika ada rank
  - Klik kanan / long press → context menu (Kick, Mute, Ban) — hanya tampil jika current user adalah moderator/admin
- Di mobile: sidebar ini disembunyikan default, buka via tombol di header room

---

### FASE 2 — Halaman Discuss

#### Task 8 — Halaman `Discuss/Index.tsx`

Halaman daftar semua room discuss.

Spesifikasi:
- Gunakan `<Layout>` (Layout utama project, ada navbar)
- Section heading: `DISCUSSION ROOMS`
- Grid room: `grid grid-cols-1 sm:grid-cols-2 gap-px bg-[#2A2A2A]`
- Tiap room card: `bg-[#111] p-4 hover:bg-[#1A1A1A] transition-colors`
  - Cover room (jika ada): `w-12 h-12 object-cover bg-[#1A1A1A]` di sebelah kiri
  - Nama room: `font-bold text-white text-sm`
  - Deskripsi: `text-xs text-[#555] line-clamp-2 mt-1`
  - Meta row: icon `Users` 12px `text-[#555]` + jumlah member · icon `MessageSquare` 12px + jumlah pesan
  - Badge tipe room: `PUBLIC` / `PRIVATE` / `INVITE ONLY` — style badge standar
  - Button `JOIN` atau `OPEN` — border `border-[#2A2A2A]` text-xs font-bold, hover `border-white/40`
- Empty state: `text-xs text-[#555] text-center py-12` — "No discussion rooms yet."
- Loading: Skeleton sesuai pola project

---

#### Task 9 — Halaman `Discuss/Room.tsx`

Halaman utama chat dalam satu room.

Spesifikasi layout:
```
[Room Header — sticky top]
[MessageList — flex-1, scrollable]
[MessageInput — sticky bottom]

[MemberSidebar — di kanan, toggle via button di header]
```

Room Header (`sticky top-0 bg-black border-b border-[#2A2A2A] z-10`):
- Kiri: tombol `←` kembali ke Index (icon `ArrowLeft` lucide 18px warna `#555`)
- Tengah: nama room `font-bold text-white text-sm` + jumlah member online `text-[10px] text-[#555]`
- Kanan: icon `Users` untuk toggle `MemberSidebar`, icon `MoreVertical` untuk room settings (jika moderator)

Layout utama:
```tsx
<div className="flex h-screen flex-col bg-black">
  {/* Header */}
  <div className="sticky top-0 z-10 ...">...</div>

  {/* Body */}
  <div className="flex flex-1 overflow-hidden">
    {/* Messages */}
    <div className="flex flex-1 flex-col overflow-hidden">
      <MessageList ... />
      <MessageInput ... />
    </div>

    {/* Sidebar — conditional */}
    {showSidebar && <MemberSidebar ... />}
  </div>
</div>
```

State management:
- `messages` — dari API + WebSocket listener
- `replyTo` — pesan yang sedang di-reply
- `showSidebar` — toggle member list
- `emotes` — fetch dari API saat mount

WebSocket (Laravel Echo):
```ts
import Echo from 'laravel-echo'

// Subscribe ke room channel
window.Echo.join(`room.${roomId}`)
  .listen('MessageSent', (e) => { /* prepend ke messages */ })
  .listen('MessageDeleted', (e) => { /* update is_deleted */ })
  .listen('MessageEdited', (e) => { /* update body */ })
  .listen('ReactionToggled', (e) => { /* update reactions */ })
  .here((users) => { /* set online members */ })
  .joining((user) => { /* tambah ke online */ })
  .leaving((user) => { /* hapus dari online */ })
```

---

### FASE 3 — Integrasi & Polish

#### Task 10 — Tambah Link Discuss ke Halaman Detail Series

Pada halaman detail series (`Pages/Series/Show.tsx` atau sejenisnya), tambahkan tombol/tab:
- `DISCUSS` — style badge standar, sejajar dengan info komik
- Klik navigasi ke `/rooms?context_type=comic&context_id={comicId}` atau room spesifik komik itu
- Gunakan icon `MessageSquare` dari lucide-react

Jangan mengubah desain halaman series yang sudah ada. Hanya **tambahkan** elemen baru.

---

#### Task 11 — Notifikasi Bell di Navbar

Cek apakah navbar `Layout` sudah punya area untuk icon tambahan. Jika ya:
- Tambahkan icon `Bell` lucide 18px warna `#555`, hover putih
- Jika ada unread notifications: dot putih kecil `w-2 h-2 rounded-full bg-white` absolute di kanan atas icon
- Klik → dropdown kecil list notifikasi (max 5 item), `bg-[#111] border border-[#2A2A2A] w-72`
- Tiap item: `px-4 py-3 border-b border-[#2A2A2A] hover:bg-[#1A1A1A] text-xs text-[#777]`
- "Mark all read" button di bawah: `text-[10px] text-[#555] hover:text-white px-4 py-2`

### FASE 4 — Koneksi Backend ↔ Frontend

> Fase ini wajib dikerjakan setelah Fase 1–3 selesai. Tanpa fase ini UI tidak akan menerima data dari backend.

---

#### Task 12 — Setup Laravel Echo & Reverb di Frontend

Cek file `resources/js/bootstrap.ts` (atau `bootstrap.js`). Tambahkan konfigurasi Echo jika belum ada. **Jangan hapus konfigurasi yang sudah ada.**

```ts
// resources/js/bootstrap.ts — TAMBAHKAN di bagian bawah

import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

window.Pusher = Pusher

window.Echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
  wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
  forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
  enabledTransports: ['ws', 'wss'],
})
```

Pastikan variabel berikut ada di `.env`:
```
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Install package jika belum ada:
```bash
npm install laravel-echo pusher-js
```

---

#### Task 13 — Buat `PageController` untuk Inertia

Buat `app/Modules/Discuss/Http/Controllers/PageController.php`

Controller ini **berbeda** dari controller API yang sudah ada. Fungsinya hanya render halaman Inertia dengan props data awal.

```php
<?php

namespace App\Modules\Discuss\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Modules\Discuss\Services\RoomService;
use App\Modules\Discuss\Services\MessageService;
use App\Modules\Discuss\Services\MemberService;
use App\Modules\Discuss\Services\EmoteService;
use App\Modules\Discuss\Services\NotificationService;

class PageController extends Controller
{
    public function __construct(
        private RoomService $roomService,
        private MessageService $messageService,
        private MemberService $memberService,
        private EmoteService $emoteService,
        private NotificationService $notificationService,
    ) {}

    // GET /discuss
    public function index()
    {
        return Inertia::render('Discuss/Index', [
            'rooms' => $this->roomService->publicRooms(),
        ]);
    }

    // GET /discuss/{slug}
    public function room(Request $request, string $slug)
    {
        $room = $this->roomService->findBySlug($slug);
        $userId = auth()->id();

        return Inertia::render('Discuss/Room', [
            'room'            => $room,
            'initialMessages' => $this->messageService->paginate($room->id, limit: 50),
            'members'         => $this->memberService->listForRoom($room->id),
            'emotes'          => $this->emoteService->availableFor($room->id),
            'currentUserId'   => $userId,
            'unreadCount'     => $this->notificationService->unreadCount($userId),
            'isMember'        => $this->memberService->isMember($room->id, $userId),
            'userRole'        => $this->memberService->roleOf($room->id, $userId),
        ]);
    }
}
```

Method tambahan yang perlu ditambahkan ke Service yang sudah ada:
- `RoomService::publicRooms(): Collection` — ambil semua room aktif yang public
- `RoomService::findBySlug(string $slug): Room` — cari room by slug, throw 404 jika tidak ada
- `MessageService::paginate(string $roomId, int $limit): Collection` — 50 pesan terbaru, urut created_at DESC
- `MemberService::isMember(string $roomId, string $userId): bool`
- `MemberService::roleOf(string $roomId, string $userId): ?string`

---

#### Task 14 — Tambah Route Web untuk Halaman Inertia

Buka `routes/web.php`. Tambahkan route group berikut **di bagian bawah**, jangan ubah route yang sudah ada:

```php
// routes/web.php — TAMBAHKAN

use App\Modules\Discuss\Http\Controllers\PageController;

Route::middleware(['auth', 'verified'])->prefix('discuss')->name('discuss.')->group(function () {
    Route::get('/', [PageController::class, 'index'])->name('index');
    Route::get('/{slug}', [PageController::class, 'room'])->name('room');
});
```

Catatan:
- Route prefix `discuss` — URL menjadi `/discuss` dan `/discuss/{slug}`
- Middleware `auth` — wajib login untuk akses halaman discuss
- Middleware `verified` — email harus sudah diverifikasi
- **Jangan** hapus atau ubah route yang sudah ada di `web.php`

---

#### Task 15 — Daftarkan Broadcast Channel Authorization

Buka `routes/channels.php`. Tambahkan otorisasi channel berikut:

```php
// routes/channels.php — TAMBAHKAN

use App\Modules\Discuss\Models\Member;

// Presence channel — room chat
// Format: room.{roomId}
Broadcast::channel('room.{roomId}', function ($user, $roomId) {
    $member = Member::where('room_id', $roomId)
                    ->where('user_id', $user->id)
                    ->where('is_banned', false)
                    ->first();

    if (!$member) return false;

    // Return user info untuk presence channel (siapa saja yang online)
    return [
        'id'           => $user->id,
        'display_name' => $user->display_name,
        'avatar_url'   => $user->avatar_url,
        'role'         => $member->role,
        'rank'         => $member->rank_id ? [
            'name'  => optional($member->rank)->name,
            'color' => optional($member->rank)->label_color,
        ] : null,
    ];
});

// Private channel — notifikasi personal user
// Format: private-user.{userId}
Broadcast::channel('private-user.{userId}', function ($user, $userId) {
    return $user->id === $userId;
});
```

---

#### Task 16 — Tambah Axios Default Config untuk CSRF

Cek `resources/js/bootstrap.ts`. Pastikan axios sudah dikonfigurasi dengan CSRF token. Tambahkan jika belum ada:

```ts
// resources/js/bootstrap.ts

import axios from 'axios'

window.axios = axios
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'
window.axios.defaults.withCredentials = true
window.axios.defaults.withXSRFToken = true
```

---

#### Task 17 — Tambah Method `listForRoom` di `MemberService`

Tambahkan method berikut ke `app/Modules/Discuss/Services/MemberService.php` jika belum ada:

```php
public function listForRoom(string $roomId): Collection
{
    return Member::where('room_id', $roomId)
        ->where('is_banned', false)
        ->orderByRaw("FIELD(role, 'admin', 'moderator', 'member')")
        ->get()
        ->map(function (Member $member) {
            // Ambil data user dari Auth module — query langsung, no relationship
            $user = \App\Modules\Auth\Models\User::select('id', 'display_name', 'avatar_url')
                ->where('id', $member->user_id)
                ->first();

            return [
                'userId'      => $member->user_id,
                'displayName' => $user?->display_name ?? 'Unknown',
                'avatarUrl'   => $user?->avatar_url,
                'role'        => $member->role,
                'xpPoints'    => $member->xp_points,
                'rank'        => $member->rank_id ? [
                    'name'  => optional($member->rank)->name,
                    'color' => optional($member->rank)->label_color,
                ] : null,
                'isOnline'    => false, // akan diupdate via presence channel
            ];
        });
}
```

---

#### Task 18 — Verifikasi Koneksi End-to-End

Setelah Task 12–17 selesai, lakukan verifikasi berikut secara berurutan:

**A. Backend API berjalan:**
```bash
php artisan serve
php artisan reverb:start
php artisan queue:work
```

**B. Cek route terdaftar:**
```bash
php artisan route:list --path=discuss
```
Pastikan ada: `GET discuss/`, `GET discuss/{slug}`, dan semua route API dari `AGENTS.md` backend.

**C. Cek broadcast channel:**
```bash
php artisan channel:list
```
Pastikan `room.{roomId}` dan `private-user.{userId}` terdaftar.

**D. Test halaman di browser:**
- Buka `/discuss` — pastikan daftar room muncul dari database
- Buka `/discuss/{slug}` — pastikan pesan awal muncul dari props Inertia
- Kirim pesan — pastikan muncul real-time di tab browser lain yang membuka room yang sama
- Cek Network tab di DevTools — pastikan ada koneksi WebSocket ke Reverb

**E. Jika ada error:**
- `419 CSRF token mismatch` → cek Task 16 (axios config)
- `403 Forbidden` di channel → cek Task 15 (channel authorization)
- WebSocket tidak connect → cek Task 12 (Echo config) dan pastikan Reverb berjalan
- Props tidak sampai ke React → cek Task 13 (PageController) dan Task 14 (route web)

---

## Catatan Implementasi

**Cara handle WebSocket dengan Inertia:**
```ts
// Di Room.tsx, setup Echo di useEffect
useEffect(() => {
  const channel = window.Echo.join(`room.${roomId}`)
  // ...listeners
  return () => {
    window.Echo.leave(`room.${roomId}`)
  }
}, [roomId])
```

**Cara fetch data di halaman Inertia:**
```ts
// Gunakan Inertia props untuk data awal, axios untuk load more
import { usePage } from '@inertiajs/react'
const { room, initialMessages, members, emotes } = usePage().props
```

**Tidak boleh:**
- `rounded-xl`, `rounded-2xl`, `rounded-full` (kecuali untuk avatar circle)
- Warna selain yang ada di design system
- `shadow-*` apapun
- Background gradient
- Animasi kompleks (cukup `transition-colors`)

