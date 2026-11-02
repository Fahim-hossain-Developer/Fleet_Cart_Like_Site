<?php

use App\Domain\Settings\Actions\Settings;
use App\Domain\Settings\Models\Setting;
use App\Models\Admin;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Pages\Auth\Login;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

it('renders the storefront and changes language through a validated session', function () {
    $this->get('/')->assertOk()->assertSee('Your next chapter')->assertSee('lang="en"', false);
    $this->post('/locale', ['locale' => 'bn'])->assertRedirect('/');
    $this->get('/')->assertOk()->assertSee('আপনার নতুন অধ্যায়')->assertSee('lang="bn"', false);
    $this->post('/locale', ['locale' => 'invalid'])->assertSessionHasErrors('locale');
});
it('persists validated settings and invalidates cache', function () {
    $settings = app(Settings::class);
    expect($settings->get('name'))->toBe('Commerce');
    $settings->set('name', 'New Store');
    expect($settings->get('name'))->toBe('New Store');
    $settings->set('name', 'Updated Store');
    expect($settings->get('name'))->toBe('Updated Store');
    expect(Activity::query()->where('subject_type', Setting::class)->count())->toBe(2);
});
it('rejects unknown settings', fn () => app(Settings::class)->set('unsafe_script', '<script>'))
    ->throws(ValidationException::class);
it('rejects invalid supported setting values', fn () => app(Settings::class)->set('locale', 'xx'))
    ->throws(ValidationException::class);
it('seeds staff roles idempotently with least privilege', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);
    expect(Role::count())->toBe(5);
    expect(Role::findByName('support', 'admin')->hasPermissionTo('settings.manage'))->toBeFalse();
    expect(Role::findByName('super-admin', 'admin')->hasPermissionTo('settings.manage'))->toBeTrue();
});
it('protects the admin dashboard and separates customer sessions', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->actingAs(User::factory()->create(), 'web')->get('/admin')->assertRedirect('/admin/login');
});
it('allows authorized staff and rejects inactive or unprivileged staff', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = Admin::create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'test-password']);
    $admin->assignRole('super-admin');
    $this->actingAs($admin, 'admin')->get('/admin')->assertOk();
    $admin->update(['is_active' => false]);
    $this->get('/admin')->assertForbidden();
    $admin->update(['is_active' => true]);
    $admin->syncRoles([]);
    $this->get('/admin')->assertForbidden();
});
it('logs staff changes without recording password hashes', function () {
    $admin = Admin::create(['name' => 'Staff', 'email' => 'staff@example.test', 'password' => 'test-password']);
    $admin->update(['name' => 'Updated Staff']);
    $activity = Activity::query()->latest('id')->firstOrFail();
    expect($activity->properties->get('attributes'))->toBe(['name' => 'Updated Staff']);
});
it('authenticates staff using the admin guard', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = Admin::create(['name' => 'Staff', 'email' => 'staff@example.test', 'password' => 'test-password']);
    $admin->assignRole('support');
    Livewire::test(Login::class)
        ->fillForm(['email' => $admin->email, 'password' => 'test-password'])->call('authenticate')->assertHasNoFormErrors();
    $this->assertAuthenticatedAs($admin, 'admin');
    $this->assertGuest('web');
});
it('does not create a default staff password outside local development', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    expect(Artisan::call('app:create-admin'))->toBe(1)->and(Admin::count())->toBe(0);
});

it('creates a random local admin once and preserves credentials on repeated setup', function () {
    $this->app->detectEnvironment(fn () => 'local');
    $this->seed(RolesAndPermissionsSeeder::class);
    expect(Artisan::call('app:create-admin'))->toBe(0);
    $admin = Admin::query()->firstOrFail();
    $hash = $admin->password;
    expect($admin->hasRole('super-admin'))->toBeTrue();
    expect(Artisan::call('app:create-admin'))->toBe(0);
    expect(Admin::count())->toBe(1)->and($admin->fresh()->password)->toBe($hash);
});

it('enforces the settings policy for staff roles', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = Admin::create(['name' => 'Staff', 'email' => 'staff@example.test', 'password' => 'test-password']);
    $admin->assignRole('support');
    expect($admin->can('viewAny', Setting::class))->toBeFalse();
    $admin->syncRoles(['super-admin']);
    expect($admin->can('viewAny', Setting::class))->toBeTrue();
});
