<?php

namespace App\Modules\Discuss\Data;

class CreateRoomData
{
    public function __construct(
        public readonly string $name,
        public readonly string $slug,
        public readonly ?string $description,
        public readonly ?string $coverUrl,
        public readonly string $type,
        public readonly string $ownerUserId,
        public readonly ?string $contextType,
        public readonly ?string $contextId,
        public readonly array $settings,
    ) {}
}
