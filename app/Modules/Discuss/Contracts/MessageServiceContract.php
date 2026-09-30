<?php

namespace App\Modules\Discuss\Contracts;

use App\Modules\Discuss\Data\SendMessageData;
use App\Modules\Discuss\Models\Message;

interface MessageServiceContract
{
    public function send(SendMessageData $data): Message;

    public function edit(Message $message, string $newBody, string $byUserId): Message;

    public function delete(Message $message, string $byUserId): void;
}
