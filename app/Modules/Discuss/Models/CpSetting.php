<?php

namespace App\Modules\Discuss\Models;

use Illuminate\Database\Eloquent\Model;

class CpSetting extends Model
{
    protected $table = 'discuss_cp_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'updated_by'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'updated_at' => 'datetime',
        ];
    }

    // Hanya updated_at (tanpa created_at).
    const CREATED_AT = null;
}
