<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Data\PublicProfileData;
use App\Modules\Auth\Data\UserData;
use App\Modules\Auth\Http\Requests\UpdateProfileRequest;
use App\Modules\Auth\Models\User;
use App\Modules\Auth\Models\UserProfile;
use App\Modules\Auth\Services\PublicProfileActivityService;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProfileController
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'user' => UserData::fromModel($request->user()),
        ]);
    }

    public function edit(Request $request): \Inertia\Response
    {
        $user = $request->user();

        $profile = UserProfile::where('user_id', $user->id)->first();

        // Halaman profil (Pages/Profile.tsx, tampil di layout Discuss) hanya
        // butuh data milik user: bio/website/lokasi, tanggal bergabung, dan
        // status verifikasi email. Status cultivation (realm, level, EXP)
        // diambil frontend dari GET /api/cultivation, bukan lewat modul ini,
        // supaya modul Auth tidak bergantung pada modul Cultivation.
        return Inertia::render('Profile', [
            'profile' => $profile ? $profile->only(['bio', 'website_url', 'location']) : null,
            'joined' => $user->created_at?->format('Y-m-d'),
            'emailVerified' => $user->email_verified_at !== null,
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->update(['avatar_url' => Storage::url($path)]);
        }

        $user->update($request->only(['display_name', 'username']));

        UserProfile::updateOrCreate(
            ['user_id' => $user->id],
            $request->only(['bio', 'website_url', 'location', 'preferences']),
        );

        return response()->json([
            'message' => 'Profile updated.',
            'user' => UserData::fromModel($user->fresh()),
        ]);
    }

    /**
     * Public profile page for viewing another user. Deliberately excludes
     * email and bookmarks (private), unlike edit() which is the owner's
     * own profile page.
     */
    public function showPublic(Request $request, string $username): \Inertia\Response
    {
        $user = User::where('username', $username)->firstOrFail();
        $profile = UserProfile::where('user_id', $user->id)->first();

        $stats = [
            'messages' => Message::where('user_id', $user->id)->count(),
            'rooms'    => Member::where('user_id', $user->id)->count(),
            'xp'       => Member::where('user_id', $user->id)->sum('xp_points'),
        ];

        $publicProfile = new PublicProfileData(
            id: $user->id,
            username: $user->username,
            displayName: $user->display_name,
            avatarUrl: $user->avatar_url,
            bio: $profile?->bio,
            websiteUrl: $profile?->website_url,
            location: $profile?->location,
            memberSince: $user->created_at?->format('M Y'),
            stats: $stats,
        );

        // Tab Media / Links / Voice dipisah per Group vs Private chat; tab Groups
        // berisi daftar group. Aturan privasi ada di PublicProfileActivityService.
        $activity = app(PublicProfileActivityService::class)
            ->forUser($user->id, $request->user()?->id);

        return Inertia::render('Profile/Show', [
            'profile' => $publicProfile,
            'isSelf' => $request->user()?->id === $user->id,
            'media' => $activity['media'],
            'links' => $activity['links'],
            'voices' => $activity['voices'],
            'groups' => $activity['groups'],
        ]);
    }
}
