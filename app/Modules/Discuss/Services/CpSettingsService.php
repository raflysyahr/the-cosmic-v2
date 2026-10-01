<?php

namespace App\Modules\Discuss\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\CpSetting;
use App\Modules\Discuss\Services\Concerns\AuthorizesCpAdmin;

class CpSettingsService
{
    use AuthorizesCpAdmin;

    /** Key yang boleh di-override admin beserta tipenya. */
    public const TYPES = [
        'enabled' => 'bool',
        'message_points' => 'int',
        'message_daily_cap' => 'int',
        'reply_tier1_limit' => 'int',
        'reply_tier1_points' => 'int',
        'reply_tier2_limit' => 'int',
        'reply_tier2_points' => 'int',
        'reaction_points' => 'int',
        'reaction_cap_per_message' => 'int',
        'require_verified_reactor' => 'bool',
        'reply_received_points' => 'int',
        'reply_received_cap_per_message' => 'int',
        'burst_count' => 'int',
        'burst_window_seconds' => 'int',
        'duplicate_window_hours' => 'int',
        'min_alnum_chars' => 'int',
        'helpful_points' => 'int',
        'helpful_min_member_hours' => 'int',
        'helpful_daily_give_limit' => 'int',
        'best_answer_points' => 'int',
        'daily_bonus_points' => 'int',
        'daily_bonus_min_replies' => 'int',
        'daily_bonus_min_messages' => 'int',
        'streak_3_points' => 'int',
        'streak_7_points' => 'int',
        'streak_14_points' => 'int',
        'streak_30_points' => 'int',
        'achievement_first_reply_points' => 'int',
        'achievement_replies_100_points' => 'int',
        'achievement_likes_100_points' => 'int',
        'achievement_helpful_10_points' => 'int',
        'achievement_best_answer_10_points' => 'int',
        'achievement_active_30_points' => 'int',
        'report_valid_points' => 'int',
        'report_daily_limit' => 'int',
        'penalty_spam_points' => 'int',
        'penalty_manipulation_points' => 'int',
    ];

    /**
     * Nilai efektif = default di config/discuss_cp.php ditimpa override DB.
     * Sengaja tanpa cache: satu query kecil per award, dan admin ingin
     * perubahan langsung berlaku tanpa menunggu cache kedaluwarsa.
     */
    public function all(): array
    {
        $values = [];
        foreach (self::TYPES as $key => $type) {
            $values[$key] = $this->cast($type, config("discuss_cp.{$key}"));
        }

        foreach (CpSetting::all() as $row) {
            $key = (string) $row->getKey();
            if (! isset(self::TYPES[$key])) {
                continue;
            }
            $raw = is_array($row->value) ? ($row->value['v'] ?? null) : null;
            if ($raw === null) {
                continue;
            }
            $values[$key] = $this->cast(self::TYPES[$key], $raw);
        }

        return $values;
    }

    public function defaults(): array
    {
        $values = [];
        foreach (self::TYPES as $key => $type) {
            $values[$key] = $this->cast($type, config("discuss_cp.{$key}"));
        }

        return $values;
    }

    public function show(User $actor): array
    {
        $this->assertCpAdmin($actor);

        return ['settings' => $this->all(), 'defaults' => $this->defaults()];
    }

    /**
     * @param array<string, mixed> $input key => nilai baru, atau null untuk
     *        mengembalikan key itu ke default.
     */
    public function update(User $actor, array $input): array
    {
        $this->assertCpAdmin($actor);

        foreach ($input as $key => $value) {
            if (! isset(self::TYPES[$key])) {
                continue;
            }

            if ($value === null) {
                CpSetting::where('key', $key)->delete();
                continue;
            }

            CpSetting::updateOrCreate(
                ['key' => $key],
                ['value' => ['v' => $this->cast(self::TYPES[$key], $value)], 'updated_by' => $actor->id],
            );
        }

        return ['settings' => $this->all(), 'defaults' => $this->defaults()];
    }

    private function cast(string $type, mixed $value): bool|int
    {
        return $type === 'bool' ? filter_var($value, FILTER_VALIDATE_BOOLEAN) : (int) $value;
    }
}
