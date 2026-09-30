<?php

namespace App\Modules\Discuss\Enums;

enum CpSource: string
{
    case Message = 'message';
    case Reply = 'reply';
    case ReactionReceived = 'reaction_received';
    case ReplyReceived = 'reply_received';
    case Revoke = 'revoke';

    /** Source yang boleh dipilih admin untuk event multiplier. */
    public static function earnable(): array
    {
        return [
            self::Message->value,
            self::Reply->value,
            self::ReactionReceived->value,
            self::ReplyReceived->value,
        ];
    }
}
