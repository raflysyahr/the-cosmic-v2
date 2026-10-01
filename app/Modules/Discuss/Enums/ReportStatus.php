<?php

namespace App\Modules\Discuss\Enums;

enum ReportStatus: string
{
    case Pending = 'pending';
    case Valid = 'valid';
    case Dismissed = 'dismissed';
}
