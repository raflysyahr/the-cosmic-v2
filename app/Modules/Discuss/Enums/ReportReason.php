<?php

namespace App\Modules\Discuss\Enums;

enum ReportReason: string
{
    case Spam = 'spam';
    case Harassment = 'harassment';
    case Inappropriate = 'inappropriate';
    case Manipulation = 'manipulation';
    case Other = 'other';

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
