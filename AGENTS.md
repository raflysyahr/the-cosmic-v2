# AGENTS.md — Aturan Kerja untuk The Cosmic

> File ini berisi **aturan dasar**, bukan daftar fitur atau status project.
> Untuk fitur yang sudah ada, stack teknis, dan known issues, baca `PROJECT.md`.
> Baca file ini seluruhnya sebelum mengerjakan task apapun di `app/Modules/`.

---

## 1. Prinsip Utama

1. **Verifikasi sebelum asumsi.** Jangan percaya nama file, komentar lama, atau dokumen lain (termasuk `PROJECT.md`) sebagai kebenaran mutlak — baca kode yang sebenarnya berjalan sebelum mengubah atau mendeskripsikannya. Kode bisa berubah lebih cepat dari dokumentasi.
2. **Jangan pura-pura selesai.** Task yang berubah jadi lebih besar dari perkiraan awal harus dilaporkan, bukan disederhanakan diam-diam.
3. **Konsistensi > preferensi pribadi.** Kalau pola yang sudah ada di modul (nama variabel, cara query, gaya response) berbeda dari "cara paling ideal" menurut best practice umum, ikuti pola yang sudah ada di modul tersebut kecuali memang sedang memperbaiki bug arsitektural.
4. **Setiap perubahan pada `app/Modules/` harus disertai test yang membuktikan perubahan itu bekerja** — lihat §5.
5. **Fitur baru wajib langsung tercatat di `PROJECT.md`** — lihat §6. Task tidak dianggap selesai kalau fitur yang ditambahkan belum ada entrinya di sana.

---

## 2. Batasan Arsitektur (Modular Monolith)

Berlaku untuk `app/Modules/Auth/` dan `app/Modules/Discuss/`, dan untuk modul baru apa pun yang dibuat mengikuti pola yang sama.

- Setiap modul punya `ServiceProvider` sendiri yang mendaftarkan routes & migrations sendiri (`loadRoutesFrom`, `loadMigrationsFrom`). Broadcast channel authorization didaftarkan lewat `require` di `boot()` service provider modul (lihat `Discuss/Channels/RoomChannel.php` sebagai contoh), **bukan** lewat `routes/channels.php` di root.
- **Tidak ada foreign key constraint** di level database antar modul. Relasi antar modul hanya lewat kolom ULID biasa (misalnya `user_id` di tabel `discuss_members`, tanpa FK ke tabel `users`).
- **Tidak ada Eloquent relationship (`hasMany`, `belongsTo`, `belongsToMany`, dst.) yang melintasi batas modul**, dan sebagai konvensi project ini, **tidak ada Eloquent relationship sama sekali** bahkan untuk sesama model dalam satu modul (lihat pola `Message`, `Member`, `Room` — semuanya query manual `where(...)`, tidak ada satupun yang punya method relasi). Ikuti pola ini untuk model baru.
- Komunikasi antar modul hanya lewat:
  - **Laravel Events** (`event(new X($data))`, listener didaftarkan eksplisit lewat `Event::listen()` di `boot()` `ServiceProvider` modul tersebut — **bukan** `protected $listen` pada class yang `extends AuthServiceProvider`, karena properti itu diabaikan di sana; lihat `DiscussServiceProvider`), atau
  - **Query langsung dengan `select` kolom terbatas** ke model modul lain (lihat pola di bawah).

```php
// ✅ Benar — query langsung dengan select terbatas
use App\Modules\Auth\Models\User;

$user = User::select('id', 'display_name', 'avatar_url')
            ->where('id', $userId)
            ->first();

// ❌ Salah — jangan definisikan relationship di Model
class Message extends Model {
    public function user() { return $this->belongsTo(User::class); } // DILARANG
}

// ❌ Salah juga — jangan pakai withCount()/whenCounted() yang bergantung
// pada relasi yang tidak ada; hitung manual jika perlu agregat
$rooms = Room::withCount('members')->get(); // akan fatal error, relasi tidak ada
```

### Menambah fitur tanpa migration baru
Sebelum menambah kolom baru ke tabel yang sudah ada, cek dulu apakah tabel itu sudah punya kolom JSON generik (`settings`, `preferences`, `metadata`, `payload`, `perks` — namanya bervariasi per tabel, cek migration-nya). Kalau ada dan fiturnya bersifat extensible/opsional, simpan di situ, bukan bikin kolom baru:

```php
$room->update([
    'settings' => array_merge($room->settings, [
        'slow_mode_seconds' => 30,
    ]),
]);
```

Migration baru tetap boleh dibuat kalau memang butuh kolom terstruktur dengan index/constraint sendiri (misalnya kolom yang sering di-query dengan `WHERE`) — kolom JSON bukan solusi universal, hanya untuk data yang jarang di-query langsung.

---

## 3. Konvensi Kode (Backend)

- **Primary key**: ULID (`HasUlids` trait), bukan auto-increment integer, untuk semua tabel di dalam modul.
- **Enum PHP native** untuk semua kolom yang representasinya adalah `ENUM` di database — bukan string konstanta bebas. Cast di model (`protected function casts()`).
- **Business logic wajib di Service class.** Controller hanya: validasi request (lewat Form Request), panggil satu/lebih method Service, format response. Controller tidak boleh berisi query kompleks atau logic percabangan bisnis.
- **Controller adalah plain class** — tidak `extends Controller` — konsisten dengan `MessageController`, `MemberController`, `PageController`, `DirectController` yang sudah ada. Dependency lewat constructor injection.
- **Form Request** untuk semua validasi input HTTP.
- **DTO (readonly data class)** untuk transfer data antar layer (Controller → Service), terutama kalau data yang dibawa lebih dari 2-3 field.
- **Interface/Contract** (`XServiceContract`) untuk Service yang perlu di-mock di test atau punya lebih dari satu implementasi. Tidak wajib untuk semua Service — lihat pola yang sudah ada (`MessageServiceContract` ada, `RoomService` tidak punya contract) dan ikuti proporsinya: kalau service itu straightforward dan hanya satu implementasi yang masuk akal, contract boleh dilewati.
- **`ValidationException::withMessages([...])`** untuk semua penolakan otorisasi/aturan bisnis (bukan hanya validasi format input) — ini pola yang dipakai di seluruh modul (contoh: `MessageService::edit()` menolak non-owner dengan cara ini, bukan lempar exception generik atau `abort()`).
- Query builder/agregat (`count()`, sorting manual) untuk data lintas-model — **hindari fungsi SQL yang tidak portable** seperti `FIELD()` (MySQL-only), karena test suite jalan di SQLite. Kalau butuh custom ordering, urutkan di level PHP setelah `get()` untuk dataset kecil (per-room, per-user), bukan di level query untuk kasus seperti ini.

### 3.1 Konvensi UI/Frontend — Ada Dua Pola, Kenali Dulu Areanya

Project ini punya **dua sistem desain berbeda** yang berlaku di area berbeda (lihat `PROJECT.md` §7 bug #8 untuk detail temuan):

- **Halaman non-Discuss** (baca komik, profil, bookmark, halaman statis) mengikuti design system di `AGENTS_UI.md`: tema monokrom hitam-putih dengan hex hardcoded (`#000`, `#111`, `#555`, `#2A2A2A`), tanpa `rounded-xl`/shadow, spacing dan komponen (`Layout`, `Skeleton`, dll.) yang sudah didefinisikan eksplisit di sana. **Baca `AGENTS_UI.md` secara penuh sebelum menulis halaman/komponen baru di area ini.**
- **Modul Discuss** (`resources/js/Pages/Discuss/`, `resources/js/Components/discuss/`) memakai design-token Tailwind (`bg-surface`, `text-primary`, `text-on-surface-variant`, dst.) yang **tidak** terdaftar di `AGENTS_UI.md`. Ikuti pola token yang sudah dipakai di file-file tersebut untuk konsistensi visual dalam modul ini.

Jangan mencampur dua pola dalam satu halaman/komponen yang sama. Kalau ragu file yang sedang dikerjakan termasuk area yang mana, lihat pola styling yang sudah dipakai di file tersebut atau file tetangganya, bukan menerka dari salah satu sistem secara default.

---

## 4. Keamanan & Otorisasi — Wajib Dicek Setiap Kali

Ini bagian paling sering jadi sumber bug nyata di project ini (lihat histori bug di `PROJECT.md` §7). Setiap kali menulis atau mengubah endpoint yang melakukan aksi atas nama user, tanyakan secara eksplisit:

1. **Siapa yang boleh melakukan ini?** — Pemilik resource saja? Pemilik atau moderator/admin? Siapa saja yang login? Tulis pengecekan ini di Service, bukan cuma di Controller atau (lebih buruk) cuma di frontend.
2. **Apakah parameter yang diterima endpoint benar-benar apa yang dikira?** — Contoh nyata dari project ini: endpoint moderasi menerima `{userId}` dari frontend, tapi controller sempat salah mengira itu `Member.id` (primary key tabel member) dan memanggil `Member::findOrFail()` langsung dengannya — selalu gagal karena ID itu tidak pernah cocok. **Selalu verifikasi ke frontend caller yang sebenarnya**, jangan asumsikan dari nama parameter route saja.
3. **Apakah resource privat benar-benar diproteksi di server**, bukan cuma disembunyikan di UI? Room privat/DM harus ditolak (403) di level Controller/Service kalau requester bukan member — jangan andalkan frontend untuk tidak menampilkan link ke sana.
4. **Apakah aksi ini sudah punya feature test yang benar-benar menembak route HTTP** (bukan cuma unit test yang memanggil Service langsung dengan objek yang sudah benar)? Bug otorisasi di project ini justru lolos karena unit test ada tapi feature test HTTP tidak ada — unit test tidak menangkap kesalahan pada layer routing/controller.

Kalau menemukan bug otorisasi saat mengerjakan task lain, perbaiki di tempat (jangan tunda) kecuali diminta eksplisit untuk fokus ke task lain dulu — tapi tetap laporkan temuannya sebelum lanjut.

---

## 5. Testing — Wajib, Bukan Opsional

- **Setiap Service class baru atau yang diubah wajib punya unit test** yang mencakup jalur sukses dan jalur ditolak (otorisasi/validasi bisnis).
- **Setiap endpoint HTTP baru atau yang diubah wajib punya feature test** yang benar-benar menembak route (`$this->actingAs($user)->postJson(...)`), bukan hanya memanggil method Controller/Service secara langsung. Ini satu-satunya cara menangkap bug routing/parameter-binding.
- Gunakan `RefreshDatabase` trait di semua test.
- Gunakan factory (`Model::factory()`) untuk seed data test, jangan `Model::create()` manual kecuali untuk kasus yang butuh kontrol penuh atas semua field (misalnya `Member` yang field wajibnya banyak dan spesifik per skenario).
- Test harus lulus di SQLite (environment test project ini) — jangan menulis query yang hanya valid di MySQL kecuali sudah dipastikan ada fallback/alternatif portable.
- Sebelum melaporkan task selesai: jalankan **seluruh test suite** (`php artisan test`), bukan cuma test yang baru ditambahkan, untuk memastikan tidak ada regresi di modul lain.
- Untuk perubahan frontend (`.tsx`), jalankan `npm run build` untuk memverifikasi tidak ada error TypeScript/build sebelum melaporkan selesai.

---

## 6. Menjaga Dokumen Tetap Akurat

**Aturan wajib — bukan opsional:** setiap kali menambahkan, mengubah signifikan, atau menghapus sebuah fitur, `PROJECT.md` harus diperbarui **di sesi yang sama**, sebelum melaporkan task selesai ke pengguna. Ini berlaku sama seperti kewajiban testing di §5 — fitur baru tanpa update `PROJECT.md` dianggap task yang belum selesai, bukan "bisa didokumentasikan nanti".

Yang termasuk wajib update `PROJECT.md`:
- Menambahkan fitur baru (endpoint, halaman, kemampuan user-facing apa pun) → tambahkan baris baru di tabel fitur modul yang sesuai (§4/§5/§6 di `PROJECT.md`), dengan status dan keterangan singkat lokasi kode.
- Menemukan fitur yang ternyata sudah ada tapi belum tercatat, atau fitur yang ternyata tidak benar-benar berfungsi (misalnya dependensi eksternal yang belum dikonfigurasi) → perbarui baris yang relevan, jangan biarkan status yang salah bertahan.
- Menemukan bug baru yang belum sempat diperbaiki di sesi yang sama → tambahkan ke tabel Known Issues (§7 `PROJECT.md`) dengan lokasi dan dampak, supaya tidak hilang dari catatan.
- Bug yang sudah diperbaiki → pindahkan dari tabel Known Issues ke catatan ringkas "sudah diperbaiki" di bagian bawah tabel yang sama, jangan biarkan bug yang sudah selesai tetap tercatat sebagai open issue.

Kalau kamu menambah/mengubah aturan arsitektur yang berlaku ke seluruh project (misalnya menambah modul baru, mengubah konvensi penamaan) — bukan fitur spesifik — update file ini (`AGENTS.md`), bukan `PROJECT.md`.

Jangan biarkan `PROJECT.md` dan `AGENTS.md` saling duplikasi isi. Kalau ragu suatu informasi masuk yang mana: "aturan yang berlaku ke semua fitur masa depan" → `AGENTS.md`; "status/deskripsi fitur yang sudah ada sekarang" → `PROJECT.md`.
