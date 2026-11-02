<?php

use App\Domain\Orders\Actions\ManageOrder;
use App\Domain\Orders\Models\Order;
use App\Domain\Shipping\Actions\CourierEngine;
use App\Domain\Shipping\Models\CourierBooking;
use App\Domain\Shipping\Models\CourierCollection;
use App\Domain\Shipping\Models\CourierRemittance;
use App\Filament\Pages\IntegrationOperations;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function packedCourierOrder(): Order
{
    Queue::fake();
    $f = checkoutFixture();
    test()->post('/checkout', $f['data'])->assertRedirect();
    $o = Order::firstOrFail();
    test()->actingAs(catalogAdmin(), 'admin');
    foreach (['confirmed', 'processing', 'packed'] as $s) {
        app(ManageOrder::class)->transition($o, $s);
    }

    return $o->refresh();
}
it('creates a single test booking and never infers cash from delivery', function () {
    $o = packedCourierOrder();
    $engine = app(CourierEngine::class);
    $key = (string) Str::uuid();
    $b = $engine->book($o, 'courier_fake', $key);
    expect($b->status)->toBe('accepted')->and($b->cod_expected)->toBe(108000)->and($b->reported_collected)->toBeNull()->and($b->remitted_amount)->toBe(0);
    expect($engine->book($o, 'courier_fake', $key)->id)->toBe($b->id)->and($o->shipments()->count())->toBe(1);
    $b->update(['provider_status' => 'delivered']);
    $engine->query($b);
    $engine->query($b);
    expect($o->refresh()->status)->toBe('delivered')->and($o->paid_amount)->toBe(0)->and($b->refresh()->reported_collected)->toBeNull();
});
it('bounds and replays separate collection and remittance receipts', function () {
    $o = packedCourierOrder();
    $e = app(CourierEngine::class);
    $b = $e->book($o, 'courier_fake', (string) Str::uuid());
    $key = (string) Str::uuid();
    $e->receipt($b, 108000, 'COLLECTION-1', $key);
    $e->receipt($b, 108000, 'COLLECTION-1', $key);
    expect(CourierCollection::count())->toBe(1)->and($o->refresh()->paid_amount)->toBe(108000)->and($b->refresh()->remitted_amount)->toBe(0);
    expect(fn () => $e->receipt($b, 1, 'EXCESS', (string) Str::uuid()))->toThrow(ValidationException::class);
    $key = (string) Str::uuid();
    $e->receipt($b, 100000, 'BANK-1', $key, true);
    $e->receipt($b, 100000, 'BANK-1', $key, true);
    expect(CourierRemittance::count())->toBe(1)->and($b->refresh()->remitted_amount)->toBe(100000);
    expect(fn () => $e->receipt($b, 8001, 'EXCESS', (string) Str::uuid(), true))->toThrow(ValidationException::class);
});
it('requires actual carrier identifiers for manual booking and denies unknown providers', function () {
    $o = packedCourierOrder();
    $e = app(CourierEngine::class);
    expect(fn () => $e->book($o, 'courier_manual', (string) Str::uuid()))->toThrow(ValidationException::class);
    $b = $e->book($o, 'courier_manual', (string) Str::uuid(), 'REAL-CARRIER-REF', 'REAL-TRACKING');
    expect($b->status)->toBe('accepted')->and($o->shipments()->first()->tracking_no)->toBe('REAL-TRACKING');
    Livewire::test(IntegrationOperations::class)->set('orderId', $o->id)->assertSee('Expected COD')->assertSee('Unknown');
});

it('reconciles an uncertain test booking without creating another booking or shipment', function () {
    $o = packedCourierOrder();
    $e = app(CourierEngine::class);
    $b = $e->book($o, 'courier_fake', (string) Str::uuid());
    $b->update(['status' => 'unknown']);
    $e->query($b);
    $e->query($b);
    expect($o->shipments()->count())->toBe(1)->and(CourierBooking::count())->toBe(1)->and($b->refresh()->status)->toBe('accepted');
});
