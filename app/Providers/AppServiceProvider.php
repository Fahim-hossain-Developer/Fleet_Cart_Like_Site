<?php

namespace App\Providers;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use App\Domain\Cart\Policies\CartItemPolicy;
use App\Domain\Cart\Policies\CartPolicy;
use App\Domain\Catalog\Policies\CatalogPolicy;
use App\Domain\Checkout\Models\TaxClass;
use App\Domain\Checkout\Models\TaxRate;
use App\Domain\Checkout\Policies\CommercePolicy;
use App\Domain\Marketing\Models\Coupon;
use App\Domain\Orders\Models\Order;
use App\Domain\Settings\Models\Currency;
use App\Domain\Settings\Models\Setting;
use App\Domain\Settings\Policies\SettingPolicy;
use App\Domain\Shipping\Models\Location;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Models\ShippingZone;
use Filament\Forms\Components\Field;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->useLangPath(resource_path('lang'));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Setting::class, SettingPolicy::class);
        Gate::policy(Cart::class, CartPolicy::class);
        Gate::policy(CartItem::class, CartItemPolicy::class);
        foreach ([Location::class, ShippingZone::class, ShippingMethod::class, TaxClass::class, TaxRate::class, Currency::class, Coupon::class, Order::class] as $model) {
            Gate::policy($model, CommercePolicy::class);
        }
        Field::configureUsing(function (Field $field): void {
            $key = 'checkout.'.$field->getName();
            if (Lang::has($key)) {
                $field->label(fn () => __($key));
            }
        });
        Field::configureUsing(function (Field $field): void {
            $key = 'catalog.'.$field->getName();
            if (Lang::has($key)) {
                $field->label(fn () => __($key));
            }
        });
        foreach (glob(app_path('Domain/Catalog/Models/*.php')) as $path) {
            $model = 'App\\Domain\\Catalog\\Models\\'.basename($path, '.php');
            Gate::policy($model, CatalogPolicy::class);
        }
    }
}
