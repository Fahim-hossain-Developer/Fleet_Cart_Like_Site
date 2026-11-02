<?php

namespace App\Domain\Customers\Actions;

use App\Domain\Customers\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class NoticePreferences
{
    public const TOPICS = ['orders', 'stock', 'price', 'coupons', 'reviews'];

    public const CHANNELS = ['in_app', 'email', 'sms'];

    public function enabled(User $u, string $topic, string $channel): bool
    {
        if ($u->is_blocked) {
            return false;
        }$p = NotificationPreference::where('user_id', $u->id)->where('topic', $topic)->where('channel', $channel)->first();

        return $p->is_enabled ?? ($channel === 'in_app' || ($topic === 'orders' && $channel === 'email'));
    }

    /** @param array<string,mixed> $input */
    public function save(User $u, array $input): void
    {
        abort_if($u->is_blocked, 403);
        $rules = ['preferences' => 'present|array:'.implode(',', self::TOPICS)];
        foreach (self::TOPICS as $topic) {
            $rules['preferences.'.$topic] = 'array:'.implode(',', self::CHANNELS);
            foreach (self::CHANNELS as $channel) {
                $rules['preferences.'.$topic.'.'.$channel] = 'sometimes|boolean';
            }
        }$d = validator($input, $rules)->validate();
        DB::transaction(function () use ($u, $d) {
            foreach (self::TOPICS as $topic) {
                foreach (self::CHANNELS as $channel) {
                    $enabled = (bool) ($d['preferences'][$topic][$channel] ?? false);
                    if ($enabled && (($channel === 'email' && ! $u->email_verified_at) || ($channel === 'sms' && ! $u->phone_verified_at))) {
                        throw ValidationException::withMessages(['preferences' => __('marketing.verify_channel')]);
                    }NotificationPreference::updateOrCreate(['user_id' => $u->id, 'topic' => $topic, 'channel' => $channel], ['is_enabled' => $enabled, 'consented_at' => $enabled ? now() : null]);
                }
            }
        });
    }
}
