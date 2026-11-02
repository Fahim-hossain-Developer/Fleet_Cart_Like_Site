<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $map = [
            'super-admin' => ['integrations.manage', 'customers.export', 'orders.export', 'payments.manage', 'refunds.manage', 'customers.manage', 'checkout.manage', 'shipping.manage', 'marketing.manage', 'admin.access', 'settings.manage', 'staff.manage', 'catalog.manage', 'orders.manage', 'content.manage', 'customers.view', 'reports.view'],
            'manager' => ['customers.export', 'orders.export', 'payments.manage', 'refunds.manage', 'customers.manage', 'shipping.manage', 'marketing.manage', 'admin.access', 'catalog.manage', 'orders.manage', 'content.manage', 'customers.view', 'reports.view'],
            'order-processor' => ['admin.access', 'orders.manage', 'customers.view'],
            'content-editor' => ['admin.access', 'content.manage'],
            'support' => ['admin.access', 'customers.view'],
        ];
        foreach ($map['super-admin'] as $name) {
            Permission::findOrCreate($name, 'admin');
        }
        foreach ($map as $name => $permissions) {
            Role::findOrCreate($name, 'admin')->syncPermissions($permissions);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
