<?php

namespace App\Modules\Discuss\Models;

use App\Modules\Discuss\Enums\PenaltyType;
use App\Modules\Discuss\Enums\ReportReason;
use App\Modules\Discuss\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasUlids;

    protected $table = 'discuss_reports';

    protected $fillable = [
        'room_id', 'message_id', 'message_author_id', 'reporter_id',
        'reason', 'note', 'status', 'penalty', 'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'reason' => ReportReason::class,
            'status' => ReportStatus::class,
            'penalty' => PenaltyType::class,
            'resolved_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    // Hanya created_at (tanpa updated_at) — pola sama dengan Reaction/CpLog.
    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $report) {
            $report->created_at ??= now();
        });
    }
}
