<?php

namespace App\Modules\Discuss\Enums;

enum MemberRole: string
{
    case Member = 'member';
    case Moderator = 'moderator';
    case Admin = 'admin';
}
