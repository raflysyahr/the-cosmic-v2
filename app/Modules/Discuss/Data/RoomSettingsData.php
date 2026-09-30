<?php

namespace App\Modules\Discuss\Data;

class RoomSettingsData
{
    public function __construct(
        public readonly array $settings,
    ) {}
}
