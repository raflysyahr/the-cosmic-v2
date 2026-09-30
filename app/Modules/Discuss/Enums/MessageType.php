<?php

namespace App\Modules\Discuss\Enums;

enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case File = 'file';
    case Video = 'video';
    case Sticker = 'sticker';
    case System = 'system';
}
