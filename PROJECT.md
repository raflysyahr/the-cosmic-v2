# PROJECT.md — The Cosmic

> Dokumen ini merangkum **apa saja fitur yang benar-benar ada** di codebase saat ini (diverifikasi langsung dari kode, bukan dari rencana/dokumentasi lama), stack teknis, dan status known-issues. Untuk aturan arsitektur & cara kerja tim, baca `AGENTS.md`.

Terakhir diverifikasi: Juli 2026, dengan cara membaca langsung struktur `app/Modules`, `app/Http`, `app/Services`, `resources/js/Pages`, dan menjalankan test suite.

---

## 1. Ringkasan Project

The Cosmic adalah web comic reader (mirip Komikcast) yang dibangun sebagai **modular monolith**: Laravel 13 + Inertia.js + React/TypeScript di satu repo yang sama, tanpa API terpisah untuk frontend-nya sendiri. Dua modul custom (`Auth`, `Discuss`) dibangun mengikuti pola modular yang ketat; sisanya (baca komik) adalah kode "legacy-style" yang lebih longgar, mengonsumsi data dari layanan pihak ketiga.

---

## 2. Stack Teknis

| Layer | Teknologi | Versi |
|---|---|---|
| Backend framework | Laravel | ^13.8 |
| PHP | — | ^8.3 |
| Frontend bridge | Inertia.js (React adapter) | ^2.0 |
| Frontend | React | ^18.2 |
| Bahasa frontend | TypeScript | ^6.0.3 (campur `.tsx`/`.jsx`) |
| Build tool | Vite | ^6.4 |
| Styling | Tailwind CSS | ^3.2 (`@tailwindcss/forms`) |
| Realtime | Laravel Reverb (WebSocket server) + Laravel Echo + Pusher protocol client | Reverb ^1.10, laravel-echo ^2.3, pusher-js ^8.5 |
| Auth token (API) | Laravel Sanctum | ^4.0 |
| OAuth | Laravel Socialite | ^5.28 |
| Ikon | lucide-react | — |
| Emoji rendering | react-fluentui-emoji | — |
| Testing | PHPUnit | ^12.5 |
| Linting/format PHP | Laravel Pint | ^1.27 |
| DB (test) | SQLite (in-memory, via `RefreshDatabase`) | — |
| DB (produksi, diasumsikan) | MySQL (skema pakai `ENUM`, `ULID`) | — |

Primary key semua tabel custom (`Auth`, `Discuss`, `Cultivation`) menggunakan **ULID**, bukan auto-increment integer.

---

## 3. Peta Modul & Kode

```
app/
├── Modules/
│   ├── Auth/         ← modular, mengikuti AGENTS.md ketat
│   ├── Discuss/      ← modular, mengikuti AGENTS.md ketat
│   └── Cultivation/  ← modular, mengikuti AGENTS.md ketat
├── Http/Controllers/Api/V1/   ← legacy, thin proxy ke layanan komik eksternal
├── Services/KomikcastService.php  ← proxy tunggal ke worker eksternal
└── Models/         ← KOSONG (tidak ada model legacy aktif)
```

Modul `Auth`, `Discuss`, dan `Cultivation` masing-masing punya `ServiceProvider`, `routes/`, `Database/Migrations`, `tests/` sendiri — didaftarkan di `bootstrap/providers.php`.

⚠️ **Ada dua tabel orphan di `database/migrations` root** (`bookmarks`, `reading_histories`) yang tidak berada di modul manapun, tidak punya prefix (`auth_`/`discuss_`), dan **tidak punya model Eloquent sama sekali**. Lihat known issue #7 di §8.

---

## 4. Fitur — Auth Module

Semua terverifikasi ada di `app/Modules/Auth/`.

| Fitur | Status | Keterangan |
|---|---|---|
| Register (email/password) | ✅ Ada | `RegisterController` → `AuthService::register()`, fire `UserRegistered` |
| Login (email/password) | ✅ Ada | `LoginController` → `AuthService::login()`, cek status `banned`/`suspended` |
| Logout | ✅ Ada | `LogoutController`; **ada duplikasi** — lihat §8 |
| Verifikasi email | ✅ Ada | `EmailVerificationController` + `VerifyEmailNotification` |
| Reset password | ✅ Ada | `PasswordResetController` + `PasswordResetNotification` |
| OAuth (Socialite, provider generic) | ✅ Ada (kode) | `SocialAuthController` — stateless, cocok untuk konsumsi token API. **Kredensial provider (Google/GitHub dll.) tidak dikonfigurasi di `config/services.php`** — perlu ditambahkan di `.env` + config sebelum benar-benar bisa dipakai. |
| Profil sendiri (edit) | ✅ Ada | `ProfileController::edit()`. Stat "Bookmarks" di halaman ini **tidak lagi dari backend** — diambil dari `useBookmarks()` hook (client-side, `localStorage`), karena tidak pernah ada penyimpanan bookmark di server (lihat §8 riwayat perbaikan). |
| Profil publik (lihat user lain) | ✅ Ada | `GET /u/{username}` → `ProfileController::showPublic()`. Sengaja **tidak** expose email & jumlah bookmark (privat). Menampilkan stat: messages, rooms, xp. Tombol "Message" langsung buka DM. Bisa diakses juga lewat link nama member di halaman "Discuss About" (lihat §5.1). |
| Role user | ✅ Ada | Enum `UserRole`: `reader \| moderator \| creator \| admin` |
| Status user | ✅ Ada | Enum `UserStatus`: `active \| suspended \| banned` |

---

## 5. Fitur — Discuss Module (Group Chat + DM)

Semua terverifikasi ada di `app/Modules/Discuss/`. Real-time via Laravel Reverb (WebSocket), broadcasting pakai presence channel (`Echo.join`). Untuk skema database lengkap termasuk desain Direct Message, lihat `ERD.md`.

### 5.1 Room / Group Chat
| Fitur | Status | Keterangan |
|---|---|---|
| Tipe room | ✅ Ada | 3 tipe: `public`, `private`, `invite_only` (enum `RoomType`) |
| Buat room | ✅ Ada | `RoomController::store()` |
| List room publik + room milik user | ✅ Ada | `RoomService::roomsForUser()` |
| Join room publik | ✅ Ada (baru diperbaiki) | Tombol "Join Room" di UI `Room.tsx`; hanya `public` yang bisa di-join langsung, `private`/`invite_only` ditolak |
| Room otomatis per komik (`comic-{slug}`) | ✅ Ada | `RoomService::findOrCreateForComic()`, dipicu saat user buka `/discuss/comic-{slug}` |
| Arsip room | ✅ Ada | `RoomService::archive()` (soft: `is_active=false`) |
| Invite link | ⚠️ Sebagian | `RoomService::generateInviteLink()` ada (generate token acak), tapi **belum ada endpoint/flow untuk memvalidasi link tsb saat join** — room `invite_only` saat ini tidak bisa di-join oleh siapa pun lewat jalur normal |
| Halaman "Discuss About" (`GET /discuss/{slug}/about`) | ✅ Ada | `PageController::about()` → `Discuss/About.tsx`. Diakses dengan klik avatar + nama room di header `Room.tsx` (hanya untuk group room, bukan direct chat). Menampilkan avatar room besar, nama room, jumlah member, lalu dua tab **Member** dan **Media** tepat di bawahnya. Guard membership sama seperti `room()` — non-member ditolak 403 untuk room privat. |
| Tab Member (di halaman About) | ✅ Ada | List seluruh member, diurutkan admin → moderator → member. Setiap baris (kecuali tombol aksi moderasi) adalah link ke `/u/{username}` (profil publik member tsb). Aksi kick/mute/ban tetap tersedia di sini untuk moderator/admin (dipindah dari `MemberSidebar` yang sudah dihapus — lihat §5.4). |
| Tab Media (di halaman About) | ⚠️ Placeholder | Belum ada backend media-gallery; saat ini hanya menampilkan teks "Media gallery coming soon". Menampilkan pesan bertipe `image` dari `discuss_messages` adalah kandidat implementasi berikutnya. |

### 5.2 Direct Message (1:1 chat)
| Fitur | Status | Keterangan |
|---|---|---|
| Mulai/buka DM dengan user lain | ✅ Ada | `POST /direct/{username}` → `DirectController::open()`, reuse `Room` dengan `context_type=direct`, `context_id` deterministik dari 2 user id (detail desain: `ERD.md` §"Direct Message") |
| List semua DM | ✅ Ada | `GET /direct` → `Discuss/DirectList.tsx` |
| Tab navigasi Chats/Groups | ✅ Ada | Di `Discuss/Index.tsx` dan `Discuss/DirectList.tsx` |
| Halaman chat DM (reuse `Room.tsx`) | ✅ Ada | Header otomatis tampilkan nama & avatar lawan bicara, bukan nama room; header tidak bisa diklik ke halaman About (konsep "about" tidak relevan untuk chat 1:1) |
| Proteksi privasi DM | ✅ Ada | Non-member (termasuk yang bukan bagian dari DM tsb) mendapat 403 saat akses `/discuss/{slug}` (dan `/discuss/{slug}/about`) |

### 5.3 Pesan
| Fitur | Status | Keterangan |
|---|---|---|
| Kirim pesan | ✅ Ada | Validasi member aktif & tidak sedang di-mute |
| Edit pesan | ✅ Ada | **Hanya pemilik pesan** (diperbaiki dari bug bypass sebelumnya) |
| Hapus pesan (soft delete) | ✅ Ada | Pemilik pesan **atau** moderator/admin room |
| Reply ke pesan | ✅ Ada | `reply_to_id` |
| Reaksi/emote di pesan | ✅ Ada | Hanya untuk pesan orang lain. Satu reaction per user per message (switch = replace). One-click pada reaction yang sudah ada. Animasi real-time: pop untuk emote baru, bump untuk count bertambah. Default: Unicode emoji. |
| Pin/unpin pesan | ✅ Ada | Hanya admin. Admin bisa pin pesan sendiri maupun pesan member lain. Max 5 pinned per room, disimpan di `settings->pinned_message_ids` (array). Optimistic update + real-time broadcast. Modal list jika >1 pinned. Tidak berlaku untuk direct chat. |
| Real-time push pesan baru/edit/delete | ✅ Ada | Broadcast via `PresenceChannel`, event `MessageSent`/`MessageEdited`/`MessageDeleted` |
| Tipe pesan | ✅ Ada | Enum `MessageType`: `text \| image \| sticker \| system` |
| Custom emote (upload) | ✅ Ada | `EmoteService::upload()`, per-room atau global |
| Slow mode, XP per pesan | ✅ Ada (via JSON `settings`) | Disimpan di kolom `settings` room, bukan kolom terpisah |

### 5.4 Moderasi Member
| Fitur | Status | Keterangan |
|---|---|---|
| Kick member | ✅ Ada, popup sukses/gagal | Hanya moderator/admin; tidak bisa kick diri sendiri; moderator tidak bisa kick sesama moderator/admin. **UI pindah dari sidebar geser ke tab Member di halaman "Discuss About"** (lihat §5.1) — `MemberSidebar.tsx` sudah dihapus. |
| Mute member (durasi custom) | ✅ Ada, popup input durasi + countdown timer | Backend: `MemberService::listForRoom()` mengekspos `mutedUntil` (ISO 8601) dan `username` per member. Frontend (`Room.tsx`): saat user yang sedang login sendiri dimute, `MessageInput` dan `EmojiPicker` disembunyikan total, digantikan banner "You are muted" dengan countdown mundur (format `1h 05m 30s`/`05m 30s`/`30s`, update tiap detik). Begitu countdown habis, member list otomatis di-refresh dan input muncul kembali tanpa reload halaman. Aksi mute sendiri sekarang dilakukan dari tab Member di halaman About; ikon kecil di sebelah nama member yang sedang dimute juga tampil di list tsb. |
| Ban member | ✅ Ada, popup sukses/gagal | Permanen, dengan konfirmasi dulu; UI di tab Member halaman About |
| Leave room | ✅ Ada | `MemberService::leave()` |
| List member per room | ✅ Ada | Diurutkan admin → moderator → member (sort di PHP, bukan SQL `FIELD()`, supaya portable); setiap member linkable ke profil publiknya; **rank (nama & warna) kini benar-benar tampil** — sebelumnya selalu `null` karena bug (lihat §8 riwayat perbaikan) |

### 5.5 Sistem Rank/XP
| Fitur | Status | Keterangan |
|---|---|---|
| XP otomatis per pesan → **Contribution Points (CP)** | ✅ Ada (menggantikan XP flat) | `AwardXpOnMessage` kini memanggil `ContributionService::awardForMessage()` (aturan: poin dasar, batas harian, filter kualitas, dedupe, multiplier event). Saldo tetap di `discuss_members.xp_points` lewat `RankService::awardXp()`, jadi rank/profil/UI lama tidak berubah — hanya label UI yang bisa disebut "CP". `room.settings.xp_per_message = 0` tetap dipakai sebagai saklar mematikan CP di satu room. DM (`settings.is_direct`) tidak memberi CP. |
| CP: pesan biasa +1 (maks 20 poin dasar/hari) | ✅ Ada | `ContributionService`; nilai bisa diubah admin |
| CP: reply ke pesan orang lain, bertingkat per hari | ✅ Ada | #1–10 = +4, #11–20 = +2, #21+ = 0 (default). Reply ke pesan sendiri dihitung pesan biasa. |
| CP: penerima reply (+1) dan penerima reaksi (+2) | ✅ Ada | `reply_received` & `reaction_received`; batas per pesan (50 / 100 poin dasar). Reaksi dari akun email belum terverifikasi tidak dihitung. Dedupe per (pesan, reaktor): toggle/ganti emote tidak menambah CP, un-react tidak mencabut. Rank penerima ikut dicek naik (`CheckRankPromotion` hanya untuk pengirim pesan). |
| CP: anti-spam | ✅ Ada | >10 pesan dalam 20 detik, isi sama persis dalam 24 jam, atau teks tanpa huruf/angka (mis. emoji saja, tanpa lampiran) = 0 CP. Semua angka bisa diubah admin. |
| CP: pencabutan saat pesan dihapus | ✅ Ada | `RevokeCpOnMessageDeleted` → entri negatif di ledger (`source = revoke`); entri asli tidak diubah sehingga hapus-lalu-kirim-ulang tidak membebaskan kuota harian. Saldo tidak turun di bawah 0; rank tidak diturunkan. |
| CP: ledger | ✅ Ada | Tabel `discuss_cp_logs` (unique `user_id, source, reference`). Sumber leaderboard. |
| CP: event multiplier (admin) | ✅ Ada (API saja, belum ada halaman UI admin) | Tabel `discuss_cp_events`; `CpEventService`. Berlaku per source & per room (opsional), rentang waktu, bisa dimatikan. Event tumpang tindih memakai multiplier terbesar (tidak ditumpuk). Batas harian/per-pesan dihitung dari poin dasar, bukan hasil kali. Endpoint: `GET/POST /api/admin/cp/events`, `PUT/DELETE /api/admin/cp/events/{id}`; `GET /api/cp/active-events` untuk semua user login. |
| CP: pengaturan nilai oleh admin | ✅ Ada (API saja) | Default di `config/discuss_cp.php`, override di tabel `discuss_cp_settings` (`CpSettingsService`). `GET/PUT /api/admin/cp/settings`; kirim `null` untuk mengembalikan ke default. Admin = `users.role = admin` (global), bukan admin per-room. |
| CP: leaderboard | ✅ Ada (API saja) | `GET /api/discuss/leaderboard?period=weekly\|monthly\|all` (global, hanya room publik) dan `GET /api/rooms/{slug}/leaderboard` (room non-publik: member saja, 403 jika bukan). Dihitung dari `SUM(amount)` ledger, tanpa kolom reset mingguan. Awal minggu = Senin, zona waktu aplikasi. |
| CP: notifikasi "+N CP" | ✅ Ada | Event `ContributionAwarded` (`ShouldBroadcastNow`, channel `user.{id}`) → `Layout.tsx`/`LayoutDiscuss.tsx` memanggil `showCp()` di `CultivationToastContext`, tampil di stack toast yang sama dengan XP Cultivation (warna amber, catatan "x2 <nama event>" saat ada multiplier). |
| **Belum dibangun** (Fase 2–3 rencana CP) | ⏳ | Helpful, Best Answer, Daily Discussion, streak, achievement, report valid, halaman UI admin CP, halaman UI leaderboard, pengurangan CP (penalti). |
| Auto-promosi rank | ✅ Ada | `CheckRankPromotion` listener, fire `MemberRankUpgraded` |
| Rank per-room & global | ✅ Ada | `RankService::ranksForRoom()`, seeded lewat `DefaultRanksSeeder` (Newcomer/Regular/Veteran/Legend). Rank yang ditetapkan ke member kini benar-benar resolve nama & warnanya di `MemberService::listForRoom()` dan payload presence channel (`RoomChannel.php`) — keduanya sempat diam-diam selalu mengembalikan `null` (lihat §8). |
| CRUD rank (admin room) | ✅ Ada | `RankController` |

### 5.6 Notifikasi
| Fitur | Status | Keterangan |
|---|---|---|
| Notifikasi mention | ✅ Ada | `SendMentionNotification` listener, parse `metadata.mentions` |
| Notifikasi reply | ✅ Ada | `SendReplyNotification` listener |
| Mark read / mark all read | ✅ Ada | `NotificationController` |
| Urutan notifikasi (terbaru dulu) | ✅ Ada (baru diperbaiki) | `NotificationController::index()` sort `orderBy('created_at', 'desc')` sekarang benar-benar berfungsi — sebelumnya `created_at` selalu `NULL` sehingga urutan tidak terdefinisi (lihat §8 riwayat perbaikan). |

---

## 6. Fitur — Baca Komik (Non-Modular / Legacy)

Modul ini **tidak** mengikuti pola `Service+Contract+DTO` ketat seperti `Auth`/`Discuss` — murni thin-proxy ke layanan eksternal.

| Fitur | Status | Keterangan |
|---|---|---|
| List/browse series | ✅ Ada | `KomikcastService::getSeries()` — proxy ke worker eksternal (`nice-try-your-job.xor96982.workers.dev`) |
| Cari series | ✅ Ada | `KomikcastService::search()` |
| Trending | ✅ Ada | `KomikcastService::getTrending()` |
| Detail series | ✅ Ada | `KomikcastService::getDetail()` |
| List genre & filter by genre | ✅ Ada | `KomikcastService::getGenres()` |
| List chapter + baca chapter (gambar) | ✅ Ada | `getChapters()`, `getChapterPages()` |
| Proxy gambar (hindari hotlink/CORS) | ✅ Ada | `KomikcastService::proxyImage()` → `/api/v1/image` |
| Bookmark | ✅ Ada — **server + client** | `BookmarkService` (backend, tabel `bookmarks`) + `useBookmarks()` hook (client-side). Backend menyimpan `cover_image` yang di-refresh otomatis dari upstream saat list dipanggil (self-healing, URL CDN yang expire akan diganti URL fresh). |
| Riwayat baca | ✅ Ada — **server + client** | `ReadingHistoryService` (backend, tabel `reading_histories`) + `useReadingHistory()` hook (client-side). Backend menyimpan `cover_image` yang di-refresh otomatis dari upstream saat list dipanggil (self-healing). Frontend `Home.tsx` tampilkan "Continue Reading" dari API backend (logged-in) atau localStorage fallback (guest). |

⚠️ Semua data komik (judul, cover, chapter, gambar) berasal dari **layanan pihak ketiga eksternal**, bukan database lokal. Availability fitur baca bergantung penuh pada uptime worker tsb.

---

## 7. Fitur — Cultivation Module

Sistem progression global per-user (1 user = 1 progress cultivation, lintas seluruh platform) — **terpisah total** dari sistem XP/rank per-room di §5.5 (Discuss). Struktur: 5 Era → 20 Realm → 200 Stage (10 stage/realm), di-generate otomatis oleh seeder, bukan ditulis manual satu-satu.

| Fitur | Status | Keterangan |
|---|---|---|
| Struktur data (Era/Realm/Stage) | ✅ Ada | `CultivationDatabaseSeeder` generate 5 era, 20 realm, 200 stage dari formula (`stage_required = 100 × 2^(sort_order-1)`) |
| Progress & breakthrough otomatis | ✅ Ada | `CultivationService::addProgress()` — bisa breakthrough berkali-kali sekaligus (lompat beberapa stage/realm) kalau amount besar; capped di realm 20 stage 10 (progress dibuang, tidak menumpuk) |
| Anti-spam / dedupe XP | ✅ Ada | Tabel `cultivation_progress_logs`, unique constraint `(user_id, source, reference)` — 1 sumber (mis. 1 chapter) cuma bisa kasih XP sekali per user |
| XP dari baca chapter | ✅ Ada, dengan anti-curang | Endpoint terpisah `POST /api/cultivation/chapter-complete` (BUKAN lewat `ReadingHistoryService::markRead()`, yang murni catat riwayat baca). Syarat di client (`Reader.tsx`): semua gambar ter-load (`onLoad`/`onError` per-`<img>`), scroll sampai gambar terakhir terlihat (`IntersectionObserver`, bukan `scrollHeight` — lihat riwayat perbaikan di §8), durasi baca > 6 detik. Server hitung ulang durasi dari `started_at` vs jam server sendiri, tidak percaya penuh ke klaim client. |
| Nilai XP per sumber (config) | ✅ Ada | `config/cultivation.php`, diatur admin lewat `.env` (`CULTIVATION_XP_CHAPTER_READ`, dst) tanpa edit kode |
| Toast "+N Resource" | ✅ Ada | `CultivationToastContext` (antrian, bukan 1 slot seperti `PopupContext`) + `CultivationToastStack` (pojok kiri bawah, transparan, teks hijau, auto-hilang). Dipasang di `Layout.tsx` (global) **dan** langsung di `Reader.tsx` — karena `Reader.tsx`/`Room.tsx` tidak pakai `<Layout>` |
| Status cultivation (level/realm/progress) | ✅ Ada (API only) | `GET /api/cultivation` — belum ada halaman/widget UI yang menampilkan ini secara visual (baru toast gain saja) |
| Halaman panduan Realm & Stage | ✅ Ada | `/cultivation-guide` (`Pages/CultivationGuide.tsx`), desain sama dengan `Pages/DMCA.tsx`. Tabel 5 era + 20 realm diambil dinamis dari `GET /api/cultivation/guide` (publik, tanpa auth) — bukan hardcode, supaya tidak desync kalau data realm berubah. Belum ditautkan dari Footer/navigasi manapun — dicek dulu apakah perlu ditambahkan. |
| Badge realm di Discuss chat | ✅ Ada | `RealmBadgeController` serve gambar dari `storage/app/private/realm/{slug}.png` (publik, tanpa auth, pola sama dengan `CoverController` di modul legacy). `CultivationService::getRealmBadgeData()` disambungkan ke `MessageResource` (field `user.realm`) dan dirender di `MessageItem.tsx`. ⚠️ Slug ikon file **BUKAN** `Str::slug()` biasa — realm 2-kata seperti "Star Lord"/"World Lord" file-nya tanpa dash (`starlord.png`, bukan `star-lord.png`), lihat `CultivationService::realmIconSlug()` dan test regression-nya. |
| Hook Comment | ⏳ Disiapkan, belum disambung | Modul Comment belum dibangun. `addProgress($user, 'comment', "comment:{id}", config('cultivation.xp.comment'))` tinggal dipanggil saat modul itu dibuat — lihat contoh di docblock `CultivationService` |
| Hook Mission Board | ⏳ Disiapkan, belum disambung | Sama seperti Comment — modul Mission Board belum dibangun, `addProgress()` tinggal dipanggil dengan `source='mission'` |

⚠️ Modul ini **awalnya dibangun sebelum `AGENTS.md` melarang Eloquent relationship** — sempat pakai `hasOne`/`belongsTo` di beberapa Model dan `User::cultivation()`. Sudah dibenahi total: semua Model murni tanpa method relasi, semua akses lintas tabel lewat query manual `select()/where()` di `CultivationService` (pola sama dengan `MessageResource.php` di Discuss).

---

## 8. Known Issues — Terverifikasi, Belum Diperbaiki

Bagian ini murni katalog bug yang ditemukan selama audit & pengembangan berjalan, agar tidak hilang dari catatan.

| # | Bug | Lokasi | Dampak |
|---|---|---|---|
| 8 | **Dua design system berbeda dipakai bersamaan** | `AGENTS_UI.md` mendefinisikan tema monokrom hitam-putih dengan hex hardcoded (`#111`, `#555`, `#2A2A2A`, tanpa `rounded-xl`/shadow) — dipakai konsisten di `Profile.tsx`, `Bookmarks.tsx`, `Home.tsx`, dll. Tapi modul `Discuss` (`Room.tsx`, `Index.tsx`, `DirectList.tsx`, `DiscussRoomCard.tsx`, `About.tsx`) memakai **design-token Tailwind** (`bg-surface`, `text-primary`, `text-on-surface-variant`, dst.) yang tidak terdaftar sama sekali di `AGENTS_UI.md`. | Halaman `Discuss/*` akan terlihat visual berbeda dari halaman lain (komik, profil). Perlu keputusan: pertahankan dua sistem (kalau memang disengaja sebagai tema berbeda per area), atau satukan salah satu jadi standar tunggal. Belum diverifikasi mana yang dimaksud sebagai sumber kebenaran saat ini. |
| 9 | Tab Media di halaman "Discuss About" masih placeholder | `resources/js/Pages/Discuss/About.tsx` | Menampilkan teks statis "Media gallery coming soon"; belum ada backend/endpoint untuk list pesan bertipe `image` per room secara terpisah dari feed chat biasa. |
| 10 | **Listener event modul Auth tidak terdaftar** | `app/Modules/Auth/AuthServiceProvider.php` masih `extends ...Providers\AuthServiceProvider` dengan `protected $listen` — properti itu hanya dibaca `EventServiceProvider`, dan listener di `app/Modules/*/Listeners` tidak ikut auto-discovery. Modul Discuss punya bug yang sama dan sudah diperbaiki (lihat bawah). | `SendVerificationEmail` dan `UpdateLastLogin` kemungkinan besar tidak pernah jalan. Belum diperbaiki karena mengaktifkannya mengubah perilaku register/login (email terkirim) dan belum bisa diuji di sesi ini. Perbaikan: pakai `Event::listen()` di `boot()` seperti `DiscussServiceProvider`. |

**Sudah diperbaiki (dicatat sebagai referensi histori):**
- Bypass otorisasi edit pesan; bypass otorisasi kick/mute/ban; salah resolve `Member` di endpoint moderasi (pakai `user_id` bukan `Member.id`); `RoomController`/`RoomResource` crash karena relasi Eloquent yang tidak ada; `MemberService::listForRoom()` pakai fungsi SQL non-portable (`FIELD()`); route `GET /rooms/{slug}/members` yang belum terdaftar; tidak ada membership check saat akses room privat/DM; `mutedUntil` tidak pernah diekspos ke frontend (mute UI tidak berfungsi sama sekali sebelumnya).
- **`ProfileController::edit()` fatal error** karena memakai `App\Models\Bookmark` yang tidak pernah ada — dihapus, stat bookmark di `Profile.tsx` sekarang diambil dari `useBookmarks()` (client-side), bukan backend.
- **Rank member selalu `null`** di dua tempat sekaligus: `MemberService::listForRoom()` dan `RoomChannel.php` (payload presence channel). Root cause: kode mengakses `$member->rank` seolah itu relasi Eloquent, padahal `Member` model sengaja tidak punya relasi apa pun (sesuai `AGENTS.md`) — jadi `$member->rank` diam-diam selalu resolve ke `null` alih-alih error. Diperbaiki dengan query manual (`Rank::whereIn('id', ...)->get()->keyBy('id')`, di-batch per-request bukan N+1 per member).
- **6 model dengan `$timestamps = false` tidak pernah mengisi kolom timestamp-nya sendiri**: `Reaction`, `Emote`, `Rank`, `Notification` (kolom `created_at`), dan `UserSocialAccount` (kolom `created_at`), `UserProfile` (kolom `updated_at`). Semuanya benar mendisable `$timestamps` (karena tabelnya memang cuma punya satu dari dua kolom, bukan keduanya — Eloquent tidak bisa partial-manage), tapi tidak ada yang mengisi kolom itu secara manual, dan 2 di antaranya (`UserProfile`, `UserSocialAccount`) bahkan punya deklarasi `protected $updatedAt`/`$createdAt` yang terlihat seperti sudah menangani ini tapi sebenarnya dead code (properti itu hanya dibaca kalau `$timestamps = true`). Diperbaiki dengan hook `booted()` (`static::creating()`/`static::saving()`) di tiap model, plus cast eksplisit `datetime` (yang juga sebelumnya hilang, menyebabkan field-field ini balik sebagai string mentah, bukan instance `Carbon`, kalau dibaca). **Dampak nyata yang ikut ketahuan**: `NotificationController::index()` mengurutkan `orderBy('created_at', 'desc')` — karena kolom itu selalu `NULL`, urutan notifikasi sebenarnya tidak pernah benar-benar "terbaru dulu".
- **Cover image di "Continue Reading" dan Bookmark expired**: URL cover dari CDN eksternal (imgkc1.my.id) punya expiry token. URL ini disimpan apa adanya di DB (`reading_histories.cover_image`, `bookmarks.cover_image`) dan localStorage. Setelah expire, gambar tidak tampil. Diperbaiki di `ReadingHistoryService::recent()` dan `BookmarkService::list()` — setiap kali data diambil, cover URL di-refresh dari upstream (`KomikcastService::getDetail()`) dan di-update di DB (self-healing). Upstream error ditangkap gracefully, fallback ke URL tersimpan.
- **Reaction emotes kosong**: `DefaultEmotesSeeder` men-seed emote dengan image path `/emotes/like.png` yang file-nya tidak ada di `public/emotes/`. Diperbaiki: tambah kolom `unicode` ke `discuss_emotes`, seeder sekarang pakai Unicode emoji (👍❤️😂😮😢🔥👏🤔), `ReactionBar` dan inline picker di `MessageItem` di-update untuk render Unicode emoji. Kolom `image_url` dijadikan nullable untuk mendukung emote tanpa gambar custom.
- Listener modul Discuss (`AwardXpOnMessage`, `CheckRankPromotion`, `SendMentionNotification`, `SendReplyNotification`) tidak pernah terdaftar — `DiscussServiceProvider` memakai `$listen` pada `AuthServiceProvider`. Sekarang didaftarkan lewat `Event::listen()`. Akibat yang ikut berubah: XP/rank dan notifikasi mention/reply yang sebelumnya tidak pernah jalan kini aktif.
- Toast "Rank Up!" (`Layout.tsx`, `LayoutDiscuss.tsx`) mendengarkan event `MemberRankUpgraded` tanpa FQCN sehingga tidak cocok dengan nama event di modul Discuss; kini memakai `.App\\Modules\\Discuss\\Events\\MemberRankUpgraded`. Belum diuji di browser dengan Reverb.

---


## 9. Dokumen Pendukung di Root Project

Selain `AGENTS.md` dan `PROJECT.md`, ada beberapa file markdown lain di root untuk detail spesifik:

| File | Isi | Status |
|---|---|---|
| `ERD.md` | Skema database lengkap per kolom (tipe, index, contoh JSON) untuk modul Auth & Discuss, **termasuk desain Direct Message** (`context_type=direct`, strategi uniqueness room per pasangan user) | Referensi detail schema — digabung dari `ERD.md` + `direct_message.md` lama menjadi satu dokumen, tanpa duplikasi |
| `MODULE_STRUCTURE.md` | Struktur folder lengkap per file untuk modul Auth & Discuss | Duplikasi sebagian besar dari §3 di atas, dengan detail file lebih rinci |
| `AGENTS_UI.md` | **Design system UI** (warna, tipografi, spacing, komponen wajib) untuk halaman non-Discuss | Lihat bug #8 — desain di sini **berbeda** dari yang dipakai di modul Discuss |

File-file transkrip/catatan sesi AI lama (`0f41.md`, `hh.md`, `session-ses_0f41.md`, `auth.md`) sudah **dihapus** — bukan dokumen arsitektur, murni log percakapan build session yang tidak relevan sebagai referensi. `direct_message.md` sudah **digabung ke `ERD.md`** dan dihapus setelah itu, tanpa duplikasi.

---

## 10. Menjalankan Project

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build      # atau: npm run dev untuk development
php artisan test    # jalankan seluruh test suite
```

Untuk development real-time (chat), jalankan juga:
```bash
php artisan reverb:start
php artisan queue:listen
```

`composer run dev` menjalankan server, queue listener, log viewer (`pail`), dan Vite dev server sekaligus secara paralel.
