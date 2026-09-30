<?php

namespace App\Modules\Auth\Data;

class PublicProfileData
{
    public function __construct(
        public readonly string $id,
        public readonly string $username,
        public readonly string $displayName,
        public readonly ?string $avatarUrl,
        public readonly ?string $bio,
        public readonly ?string $websiteUrl,
        public readonly ?string $location,
        public readonly ?string $memberSince,
        public readonly array $stats,
    ) {}
}
