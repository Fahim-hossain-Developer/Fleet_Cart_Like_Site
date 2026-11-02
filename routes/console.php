<?php

use App\Domain\Customers\Models\AdminLoginChallenge;
use App\Domain\Customers\Models\SmsDelivery;
use App\Domain\Orders\Models\OrderEvent;
use App\Domain\Settings\Models\IntegrationEvent;
use App\Jobs\ProcessPaymentEvent;
use App\Jobs\SendAccountSetup;
use App\Jobs\SendAdminCode;
use App\Jobs\SendOrderConfirmation;
use App\Jobs\SendOrderUpdate;
use App\Jobs\SendSms;
use App\Models\Admin;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('app:create-admin', function () {
    if (Admin::query()->exists()) {
        $this->info('Admin already exists; credentials unchanged.');

        return;
    }
    if (! app()->environment('local')) {
        $this->error('Initial admin creation is local-only.');

        return 1;
    }
    $password = Str::password(24);
    $admin = Admin::query()->create(['name' => 'Store Admin', 'email' => 'admin@example.com', 'password' => $password]);
    $admin->assignRole('super-admin');
    $this->info('Admin: admin@example.com');
    $this->line('One-time password: '.$password);
})->purpose('Create the first local admin with a random password printed once');

Schedule::call(function (): void {
    foreach (DB::table('order_notifications')->whereNull('sent_at')->where('attempts', '<', 3)->pluck('order_id') as $id) {
        try {
            SendOrderConfirmation::dispatch((int) $id);
        } catch (Throwable $e) {
            Log::warning('Order confirmation retry failed', ['order_id' => $id]);
        }
    }
})->everyMinute()->name('order-confirmations')->withoutOverlapping();

Schedule::call(function (): void {
    foreach (DB::table('account_setup_tokens')->whereNull('sent_at')->whereNull('used_at')->where('expires_at', '>', now())->where('attempts', '<', 3)->pluck('id') as $id) {
        try {
            SendAccountSetup::dispatch((int) $id);
        } catch (Throwable $e) {
            Log::warning('Account setup retry failed', ['delivery_id' => $id]);
        }
    }
})->everyMinute()->name('account-setup-deliveries')->withoutOverlapping();

Schedule::call(function (): void {
    foreach (OrderEvent::where('notify', true)->whereNull('sent_at')->where('attempts', '<', 3)->pluck('id') as $id) {
        try {
            SendOrderUpdate::dispatch((int) $id);
        } catch (Throwable $e) {
            Log::warning('Order update retry failed', ['event_id' => $id]);
        }
    }
})->everyMinute()->name('order-updates')->withoutOverlapping();

Schedule::call(function (): void {
    foreach (IntegrationEvent::where('kind', 'payment')->where('status', 'received')->where('attempts', '<', 12)->limit(100)->pluck('id') as $id) {
        ProcessPaymentEvent::dispatch((int) $id);
    }
})->everyMinute()->name('payment-events')->withoutOverlapping();
Schedule::call(function (): void {
    foreach (AdminLoginChallenge::whereNull('sent_at')->whereNull('used_at')->where('expires_at', '>', now())->where('delivery_attempts', '<', 3)->pluck('id') as $id) {
        SendAdminCode::dispatch((int) $id);
    }
})->everyMinute()->name('admin-code-deliveries')->withoutOverlapping();

Schedule::call(function (): void {
    foreach (SmsDelivery::where('status', 'pending')->where('expires_at', '>', now())->where('attempts', '<', 3)->limit(100)->pluck('id') as $id) {
        SendSms::dispatch((int) $id);
    }
    SmsDelivery::where('expires_at', '<=', now())->whereNull('sent_at')->update(['payload' => null, 'receiver' => null, 'status' => 'expired']);
    AdminLoginChallenge::where('expires_at', '<=', now())->whereNotNull('delivery_payload')->update(['delivery_payload' => null]);
})->everyMinute()->name('sms-deliveries')->withoutOverlapping();
