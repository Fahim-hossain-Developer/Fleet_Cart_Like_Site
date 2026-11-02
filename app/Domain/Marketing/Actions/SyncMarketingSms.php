<?php

namespace App\Domain\Marketing\Actions;

use App\Domain\Customers\Models\NoticeDelivery;
use App\Domain\Customers\Models\SmsDelivery;
use App\Domain\Marketing\Models\CartRecoveryMessage;

final class SyncMarketingSms
{
    public function execute(): void
    {
        foreach ([NoticeDelivery::class, CartRecoveryMessage::class] as $class) {
            $class::where('status', 'queued_sms')->orderBy('id')->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $sms = SmsDelivery::find($row->sms_delivery_id);
                    if ($sms && in_array($sms->status, ['pending', 'sending'], true)) {
                        continue;
                    }
                    $status = $sms?->status === 'sent' ? 'sent' : ($sms?->status === 'expired' ? 'expired' : 'unknown');
                    $row->update(['status' => $status, 'sent_at' => $status === 'sent' ? $sms->sent_at : null, 'failure_code' => $status === 'sent' ? null : 'sms_'.$status]);
                }
            });
        }
    }
}
