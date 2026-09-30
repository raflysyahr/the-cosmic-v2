<?php

namespace App\Modules\Discuss\Data;

class SendMessageData
{
    public function __construct(
        public readonly string $roomId,
        public readonly string $userId,
        public readonly ?string $replyToId,
        public readonly string $type,
        public readonly ?string $body,
        public readonly array $attachments,
        public readonly array $metadata,
    ) {}
}
