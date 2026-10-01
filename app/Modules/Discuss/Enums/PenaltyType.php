<?php

namespace App\Modules\Discuss\Enums;

enum PenaltyType: string
{
    case None = 'none';
    case Spam = 'spam';
    case Manipulation = 'manipulation';

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /** Key setting yang menyimpan jumlah poin penalti (null = tanpa penalti). */
    public function settingKey(): ?string
    {
        return match ($this) {
            self::None => null,
            self::Spam => 'penalty_spam_points',
            self::Manipulation => 'penalty_manipulation_points',
        };
    }
}
