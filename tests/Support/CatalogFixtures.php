<?php

use App\Models\Admin;
use Database\Seeders\RolesAndPermissionsSeeder;

function catalogAdmin(string $role = 'super-admin'): Admin
{
    test()->seed(RolesAndPermissionsSeeder::class);
    $admin = Admin::create(['name' => 'Catalog staff', 'email' => fake()->unique()->safeEmail(), 'password' => 'test-password']);
    $admin->assignRole($role);

    return $admin;
}
