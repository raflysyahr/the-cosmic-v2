<?php

namespace App\Modules\Discuss\Enums;

enum RoomType: string
{
    case Public = 'public';
    case Private = 'private';
    case InviteOnly = 'invite_only';
}
