<?php

namespace App\Modules\Auth\Enums;

enum UserRole: string
{
    case Reader = 'reader';
    case Moderator = 'moderator';
    case Creator = 'creator';
    case Admin = 'admin';
}
