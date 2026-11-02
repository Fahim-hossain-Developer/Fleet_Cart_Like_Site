<?php

namespace App\Filament\Pages;

use App\Domain\Customers\Actions\NormalizePhone;
use App\Domain\Customers\Actions\Sms;
use App\Domain\Orders\Actions\ManageOrder;
use App\Domain\Orders\Actions\OrderAccess;
use App\Domain\Orders\Models\Order;
use App\Domain\Payments\Actions\IntegrationFailure;
use App\Domain\Payments\Actions\PaymentEngine;
use App\Domain\Payments\Actions\RefundEngine;
use App\Domain\Payments\Models\GatewayAttempt;
use App\Domain\Payments\Models\GatewayRefund;
use App\Domain\Shipping\Actions\CourierEngine;
use App\Domain\Shipping\Models\CourierBooking;
use App\Models\Admin;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

class IntegrationOperations extends Page
{
    protected static string $view = 'filament.pages.integration-operations';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    public ?int $orderId = null;

    public string $driver = 'courier_manual';

    public string $reference = '';

    public string $tracking = '';

    public int $amount = 1;

    public string $reason = '';

    public string $testPhone = '';

    #[Locked]
    public string $key;

    public static function canAccess(): bool
    {
        $a = auth('admin')->user();

        return $a instanceof Admin && $a->is_active && $a->hasPermissionTo('orders.manage', 'admin');
    }

    public static function getNavigationLabel(): string
    {
        return __('integrations.operations');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('checkout.commerce');
    }

    public function getTitle(): string
    {
        return __('integrations.operations');
    }

    public function mount(): void
    {
        $this->key = (string) Str::uuid();
    }

    public function updatedOrderId(): void
    {
        $this->key = (string) Str::uuid();
        $this->reference = '';
        $this->tracking = '';
    }

    private function order(): Order
    {
        abort_unless(static::canAccess(), 403);

        return Order::findOrFail($this->orderId);
    }

    public function verifyPayment(int $id): void
    {
        $o = $this->order();
        app(OrderAccess::class)->staff('payments.manage');
        $a = GatewayAttempt::where('order_id', $o->id)->findOrFail($id);
        $this->run(fn () => app(PaymentEngine::class)->verify($a, true));
    }

    public function attachReference(int $id): void
    {
        $o = $this->order();
        app(OrderAccess::class)->staff('payments.manage');
        validator(['reference' => $this->reference], ['reference' => 'required|string|max:191'])->validate();
        DB::transaction(function () use ($o, $id) {
            Order::lockForUpdate()->findOrFail($o->id);
            $a = GatewayAttempt::where('order_id', $o->id)->lockForUpdate()->findOrFail($id);
            abort_unless(in_array($a->status, ['unknown', 'initializing', 'initiated'], true) && ! $a->provider_reference && (! $a->lease_until || now()->gte($a->lease_until)), 422);
            $a->update(['provider_reference' => $this->reference, 'status' => 'unknown']);
            app(ManageOrder::class)->event($o, 'reconciliation_reference', __('integrations.reference_attached'), false);
        });
        $this->verifyPayment($id);
    }

    public function refund(int $id): void
    {
        $o = $this->order();
        $a = GatewayAttempt::where('order_id', $o->id)->findOrFail($id);
        $this->run(fn () => app(RefundEngine::class)->start($a, $this->amount, $this->reason, $this->key));
    }

    public function attachRefundReference(int $id): void
    {
        $o = $this->order();
        $r = GatewayRefund::where('order_id', $o->id)->findOrFail($id);
        $this->run(fn () => app(RefundEngine::class)->attachReference($r, $this->reference));
    }

    public function verifyRefund(int $id): void
    {
        $o = $this->order();
        app(OrderAccess::class)->staff('refunds.manage');
        $r = GatewayRefund::where('order_id', $o->id)->findOrFail($id);
        $this->run(fn () => app(RefundEngine::class)->verify($r));
    }

    public function book(): void
    {
        $o = $this->order();
        $this->run(fn () => app(CourierEngine::class)->book($o, $this->driver, $this->key, $this->reference, $this->tracking));
    }

    public function confirmManual(int $id): void
    {
        $o = $this->order();
        $b = CourierBooking::where('order_id', $o->id)->findOrFail($id);
        $this->run(fn () => app(CourierEngine::class)->confirmManual($b, $this->reference, $this->tracking));
    }

    public function checkCourier(int $id): void
    {
        $o = $this->order();
        $b = CourierBooking::where('order_id', $o->id)->findOrFail($id);
        $this->run(fn () => app(CourierEngine::class)->query($b));
    }

    public function simulateDelivered(int $id): void
    {
        $o = $this->order();
        app(OrderAccess::class)->staff();
        $b = CourierBooking::where('order_id', $o->id)->findOrFail($id);
        abort_unless(app()->environment('local', 'testing') && $b->driver === 'courier_fake', 403);
        $b->update(['provider_status' => 'delivered']);
        $this->checkCourier($id);
    }

    public function receipt(int $id, bool $remit = false): void
    {
        $o = $this->order();
        $b = CourierBooking::where('order_id', $o->id)->findOrFail($id);
        $this->run(fn () => app(CourierEngine::class)->receipt($b, $this->amount, $this->reference, $this->key, $remit));
    }

    public function testSms(): void
    {
        app(OrderAccess::class)->staff('integrations.manage');
        $phone = app(NormalizePhone::class)->execute($this->testPhone);
        app(Sms::class)->queue($phone, __('integrations.test_message'));
        Notification::make()->title(__('integrations.queued'))->success()->send();
    }

    private function run(callable $action): void
    {
        try {
            $action();
            $this->key = (string) Str::uuid();
            Notification::make()->title(__('integrations.reconciled'))->success()->send();
        } catch (IntegrationFailure $e) {
            Notification::make()->title(__('payments.verification_pending'))->warning()->send();
        }
    }
}
