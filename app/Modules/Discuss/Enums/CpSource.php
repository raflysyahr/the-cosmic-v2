<?php

namespace App\Modules\Discuss\Enums;

enum CpSource: string
{
    case Message = 'message';
    case Reply = 'reply';
    case ReactionReceived = 'reaction_received';
    case ReplyReceived = 'reply_received';
    case Helpful = 'helpful';
    case BestAnswer = 'best_answer';
    case DailyBonus = 'daily_bonus';
    case Streak = 'streak';
    case Achievement = 'achievement';
    case ReportValid = 'report_valid';
    case Penalty = 'penalty';
    case Revoke = 'revoke';

    /**
     * Source yang boleh dipilih admin untuk event multiplier. Achievement
     * (milestone sekali seumur hidup), Penalty, dan Revoke sengaja tidak ada.
     */
    public static function earnable(): array
    {
        return [
            self::Message->value,
            self::Reply->value,
            self::ReactionReceived->value,
            self::ReplyReceived->value,
            self::Helpful->value,
            self::BestAnswer->value,
            self::DailyBonus->value,
            self::Streak->value,
            self::ReportValid->value,
        ];
    }
}
