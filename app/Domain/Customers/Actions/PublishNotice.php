<?php

namespace App\Domain\Customers\Actions;

use App\Domain\Customers\Models\NoticeDelivery;
use App\Domain\Customers\Models\StoreNotification;
use App\Jobs\DeliverNotice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class PublishNotice
{
    /**
     * @param  array<string,string|int>  $params
     * @param  list<string>  $channels
     */
    public function execute(User $u, string $topic, string $key, array $params, string $url, string $source, bool $deliver = true, array $channels = ['email', 'sms']): ?StoreNotification
    {
        if ($u->is_blocked) {
            return null;
        }abort_unless(in_array($topic, NoticePreferences::TOPICS, true) && str_starts_with($url, '/') && ! str_starts_with($url, '//'), 422);
        $preferences = app(NoticePreferences::class);
        $dedupe = hash('sha256', $u->id.'|'.$source);
        $data = ['topic' => $topic, 'key' => $key, 'params' => $params, 'url' => $url, 'in_app' => $preferences->enabled($u, $topic, 'in_app')];
        $id = (string) Str::uuid();
        DB::transaction(function () use ($u, $data, $id, $dedupe, $topic, $preferences, $deliver, $channels) {
            StoreNotification::upsert([['id' => $id, 'type' => 'store', 'notifiable_type' => User::class, 'notifiable_id' => $u->id, 'data' => json_encode($data, JSON_THROW_ON_ERROR), 'dedupe_key' => $dedupe, 'created_at' => now(), 'updated_at' => now()]], ['dedupe_key'], ['updated_at']);
            $notice = StoreNotification::where('dedupe_key', $dedupe)->firstOrFail();
            if (! $deliver) {
                return;
            }foreach ($channels as $channel) {
                if (! $preferences->enabled($u, $topic, $channel)) {
                    continue;
                }NoticeDelivery::upsert([['notification_id' => $notice->id, 'channel' => $channel, 'status' => 'pending', 'attempts' => 0, 'created_at' => now(), 'updated_at' => now()]], ['notification_id', 'channel'], ['updated_at']);
                $outbox = NoticeDelivery::where('notification_id', $notice->id)->where('channel', $channel)->firstOrFail();
                DB::afterCommit(function () use ($outbox) {
                    try {
                        DeliverNotice::dispatch($outbox->id);
                    } catch (\Throwable $e) {
                        Log::warning('Notice pending delivery', ['delivery_id' => $outbox->id]);
                    }
                });
            }
        });

        return StoreNotification::where('dedupe_key', $dedupe)->firstOrFail();
    }
}
