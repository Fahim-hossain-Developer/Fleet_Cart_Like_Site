<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Artisan;
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
