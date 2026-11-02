<?php

use App\Jobs\SendAccountSetup;
use App\Jobs\SendOrderConfirmation;
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
