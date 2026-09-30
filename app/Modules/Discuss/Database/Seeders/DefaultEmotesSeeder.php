<?php

namespace App\Modules\Discuss\Database\Seeders;

use App\Modules\Discuss\Models\Emote;
use Illuminate\Database\Seeder;

class DefaultEmotesSeeder extends Seeder
{
    public function run(): void
    {
        $emotes = [
            ['code' => ':like:',      'name' => 'Like',      'unicode' => '👍', 'image_url' => null],
            ['code' => ':love:',      'name' => 'Love',      'unicode' => '❤️',  'image_url' => null],
            ['code' => ':laugh:',     'name' => 'Laugh',     'unicode' => '😂', 'image_url' => null],
            ['code' => ':wow:',       'name' => 'Wow',       'unicode' => '😮', 'image_url' => null],
            ['code' => ':sad:',       'name' => 'Sad',       'unicode' => '😢', 'image_url' => null],
            ['code' => ':fire:',      'name' => 'Fire',      'unicode' => '🔥', 'image_url' => null],
            ['code' => ':clap:',      'name' => 'Clap',      'unicode' => '👏', 'image_url' => null],
            ['code' => ':think:',     'name' => 'Think',     'unicode' => '🤔', 'image_url' => null],
        ];

        foreach ($emotes as $emote) {
            Emote::create($emote);
        }
    }
}
