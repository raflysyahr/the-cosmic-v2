# Struktur Folder Module — Auth & Discuss
> Laravel Modular Monolith · Inertia React

---

## Gambaran Umum

```
app/
└── Modules/
    ├── Auth/
    └── Discuss/
```

Setiap module **mandiri** — punya Models, Services, Events, hingga Routes sendiri.  
Tidak ada dependency silang langsung; komunikasi antar module lewat **Events** atau **Service contracts**.

---

## Module Auth

```
app/Modules/Auth/
│
├── AuthServiceProvider.php          # Register routes, bindings module ini
│
├── Http/
│   ├── Controllers/
│   │   ├── LoginController.php
│   │   ├── RegisterController.php
│   │   ├── LogoutController.php
│   │   ├── PasswordResetController.php
│   │   ├── EmailVerificationController.php
│   │   └── SocialAuthController.php      # OAuth via Socialite
│   │
│   ├── Requests/
│   │   ├── LoginRequest.php
│   │   ├── RegisterRequest.php
│   │   ├── PasswordResetRequest.php
│   │   └── UpdateProfileRequest.php
│   │
│   └── Middleware/
│       ├── EnsureEmailVerified.php
│       └── RedirectIfAuthenticated.php
│
├── Models/
│   ├── User.php
│   ├── UserProfile.php
│   ├── UserSocialAccount.php
│   └── AuthSession.php
│
├── Services/
│   ├── AuthService.php               # Login, register, logout logic
│   ├── SocialAuthService.php         # Handle OAuth callback
│   ├── EmailVerificationService.php
│   └── PasswordResetService.php
│
├── Events/
│   ├── UserRegistered.php
│   ├── UserLoggedIn.php
│   └── UserBanned.php
│
├── Listeners/
│   ├── SendVerificationEmail.php     # Listen: UserRegistered
│   └── UpdateLastLogin.php           # Listen: UserLoggedIn
│
├── Notifications/
│   ├── VerifyEmailNotification.php
│   └── PasswordResetNotification.php
│
├── Enums/
│   ├── UserRole.php                  # reader | moderator | creator | admin
│   └── UserStatus.php               # active | suspended | banned
│
├── Data/                             # DTO (Data Transfer Objects)
│   ├── RegisterData.php
│   └── UserData.php
│
├── Contracts/
│   └── AuthServiceContract.php       # Interface, bisa di-mock untuk testing
│
├── database/
│   ├── migrations/
│   │   ├── 2024_01_01_000001_create_users_table.php
│   │   ├── 2024_01_01_000002_create_user_profiles_table.php
│   │   ├── 2024_01_01_000003_create_user_social_accounts_table.php
│   │   ├── 2024_01_01_000004_create_auth_sessions_table.php
│   │   └── 2024_01_01_000005_create_password_reset_tokens_table.php
│   │
│   └── seeders/
│       └── AuthDatabaseSeeder.php
│
├── routes/
│   └── auth.php                      # Semua route module ini
│
└── tests/
    ├── Unit/
    │   ├── AuthServiceTest.php
    │   └── UserModelTest.php
    └── Feature/
        ├── LoginTest.php
        ├── RegisterTest.php
        └── SocialAuthTest.php
```

---

## Module Discuss

```
app/Modules/Discuss/
│
├── DiscussServiceProvider.php        # Register routes, bindings, broadcast channels
│
├── Http/
│   ├── Controllers/
│   │   ├── RoomController.php        # CRUD room
│   │   ├── MemberController.php      # Join, leave, kick
│   │   ├── MessageController.php     # Send, edit, delete pesan
│   │   ├── ReactionController.php    # Tambah / hapus reaksi emot
│   │   ├── EmoteController.php       # Upload, manage custom emot
│   │   ├── RankController.php        # CRUD sistem pangkat
│   │   └── NotificationController.php
│   │
│   ├── Requests/
│   │   ├── CreateRoomRequest.php
│   │   ├── SendMessageRequest.php
│   │   ├── UpdateRoomSettingsRequest.php
│   │   ├── UploadEmoteRequest.php
│   │   └── CreateRankRequest.php
│   │
│   └── Resources/                    # API Resource (JSON transformer)
│       ├── RoomResource.php
│       ├── MessageResource.php
│       ├── MemberResource.php
│       └── NotificationResource.php
│
├── Models/
│   ├── Room.php
│   ├── Member.php
│   ├── Message.php
│   ├── Reaction.php
│   ├── Emote.php
│   ├── Rank.php
│   └── Notification.php
│
├── Services/
│   ├── RoomService.php               # Buat, arsipkan, generate invite link
│   ├── MessageService.php            # Kirim, edit, soft delete pesan
│   ├── MemberService.php             # Join, leave, kick, mute, ban
│   ├── ReactionService.php           # Toggle reaksi
│   ├── EmoteService.php              # Upload & validasi emot
│   ├── RankService.php               # Hitung XP, naik pangkat otomatis
│   └── NotificationService.php       # Buat & kirim notif in-app
│
├── Events/                           # Broadcast via Laravel Reverb
│   ├── MessageSent.php               # → broadcast ke room channel
│   ├── MessageDeleted.php
│   ├── MessageEdited.php
│   ├── ReactionToggled.php
│   ├── MemberJoined.php
│   ├── MemberLeft.php
│   ├── MemberBanned.php
│   └── MemberRankUpgraded.php
│
├── Listeners/
│   ├── AwardXpOnMessage.php          # Listen: MessageSent → tambah XP member
│   ├── CheckRankPromotion.php        # Listen: MessageSent → cek naik pangkat
│   ├── SendMentionNotification.php   # Listen: MessageSent → parse @mention
│   └── SendReplyNotification.php     # Listen: MessageSent → notif reply
│
├── Jobs/
│   ├── BroadcastMessage.php          # Queue broadcast pesan besar
│   └── ProcessEmoteUpload.php        # Resize & upload emot ke storage
│
├── Enums/
│   ├── RoomType.php                  # public | private | invite_only
│   ├── MessageType.php               # text | image | sticker | system
│   └── MemberRole.php               # member | moderator | admin
│
├── Data/                             # DTO
│   ├── SendMessageData.php
│   ├── CreateRoomData.php
│   └── RoomSettingsData.php
│
├── Contracts/
│   ├── MessageServiceContract.php
│   └── RankServiceContract.php
│
├── Channels/                         # Laravel broadcast channel auth
│   └── RoomChannel.php               # Authorize siapa boleh join channel
│
├── database/
│   ├── migrations/
│   │   ├── 2024_01_02_000001_create_discuss_rooms_table.php
│   │   ├── 2024_01_02_000002_create_discuss_members_table.php
│   │   ├── 2024_01_02_000003_create_discuss_messages_table.php
│   │   ├── 2024_01_02_000004_create_discuss_reactions_table.php
│   │   ├── 2024_01_02_000005_create_discuss_emotes_table.php
│   │   ├── 2024_01_02_000006_create_discuss_ranks_table.php
│   │   └── 2024_01_02_000007_create_discuss_notifications_table.php
│   │
│   └── seeders/
│       ├── DiscussDatabaseSeeder.php
│       ├── DefaultEmotesSeeder.php   # Seed emot default platform
│       └── DefaultRanksSeeder.php    # Seed rank: Newcomer → Legend
│
├── routes/
│   └── discuss.php
│
└── tests/
    ├── Unit/
    │   ├── RankServiceTest.php
    │   └── MessageServiceTest.php
    └── Feature/
        ├── SendMessageTest.php
        ├── JoinRoomTest.php
        └── ReactionTest.php
```

---

## Registrasi ke Laravel

### `config/app.php`
```php
'providers' => [
    // ...
    App\Modules\Auth\AuthServiceProvider::class,
    App\Modules\Discuss\DiscussServiceProvider::class,
],
```

### `app/Modules/Auth/AuthServiceProvider.php`
```php
public function boot(): void
{
    $this->loadRoutesFrom(__DIR__ . '/routes/auth.php');
    $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
}
```

### `app/Modules/Discuss/DiscussServiceProvider.php`
```php
public function boot(): void
{
    $this->loadRoutesFrom(__DIR__ . '/routes/discuss.php');
    $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

    // Register broadcast channels
    require __DIR__ . '/Channels/RoomChannel.php';
}
```

---

## Komunikasi Antar Module

Discuss butuh data user (nama, avatar) dari Auth — tapi **tidak boleh** import Model Auth langsung.

```
Discuss → (Event / Service Contract) → Auth
```

### Pola yang dipakai: Event

```php
// Discuss\Listeners\SendMentionNotification.php
// Butuh display_name user — ambil via query biasa, bukan Eloquent relation

use App\Modules\Auth\Models\User;  // ✅ boleh query, tapi tidak pakai ->belongsTo()

$user = User::select('id', 'display_name', 'avatar_url')
             ->where('id', $userId)
             ->first();
```

> **Aturan:** boleh query tabel Auth dari Discuss, tapi **tidak boleh** definisikan  
> Eloquent `relationship` yang melintasi module (`hasMany`, `belongsTo`, dll).

---

## Ringkasan File Penting

| File | Fungsi |
|---|---|
| `AuthServiceProvider.php` | Boot routes & migrations Auth |
| `DiscussServiceProvider.php` | Boot routes, migrations, broadcast channels |
| `RankService.php` | Hitung XP + promosi pangkat otomatis |
| `MessageService.php` | Kirim pesan + trigger events |
| `RoomChannel.php` | Otorisasi siapa boleh subscribe channel WebSocket |
| `Events/MessageSent.php` | Broadcast real-time ke semua member room |
| `Listeners/AwardXpOnMessage.php` | XP naik tiap kirim pesan |
| `Listeners/CheckRankPromotion.php` | Cek & upgrade pangkat otomatis |

