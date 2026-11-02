<?php

namespace App\Providers;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Models\CartItem;
use App\Domain\Cart\Policies\CartItemPolicy;
use App\Domain\Cart\Policies\CartPolicy;
use App\Domain\Catalog\Events\CatalogChanged;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Catalog\Policies\CatalogPolicy;
use App\Domain\Checkout\Models\TaxClass;
use App\Domain\Checkout\Models\TaxRate;
use App\Domain\Checkout\Policies\CommercePolicy;
use App\Domain\Content\Models\ContactMessage;
use App\Domain\Content\Policies\ContactMessagePolicy;
use App\Domain\Content\Policies\ContentPolicy;
use App\Domain\Customers\Models\Address;
use App\Domain\Customers\Models\BlacklistedPhone;
use App\Domain\Customers\Models\ShoppingList;
use App\Domain\Customers\Models\ShoppingListItem;
use App\Domain\Customers\Policies\AddressPolicy;
use App\Domain\Customers\Policies\BlacklistPolicy;
use App\Domain\Customers\Policies\CustomerPolicy;
use App\Domain\Customers\Policies\ShoppingListItemPolicy;
use App\Domain\Customers\Policies\ShoppingListPolicy;
use App\Domain\Marketing\Models\Coupon;
use App\Domain\Marketing\Policies\MarketingPolicy;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Policies\OrderChildPolicy;
use App\Domain\Orders\Policies\OrderPolicy;
use App\Domain\Settings\Models\Currency;
use App\Domain\Settings\Models\Setting;
use App\Domain\Settings\Policies\SettingPolicy;
use App\Domain\Shipping\Models\Location;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Models\ShippingZone;
use App\Jobs\CheckProductWatches;
use App\Models\User;
use Filament\Forms\Components\Field;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
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
        foreach (glob(app_path('Domain/Content/Models/*.php')) as $path) {
            Gate::policy('App\\Domain\\Content\\Models\\'.basename($path, '.php'), ContentPolicy::class);
        }
        foreach (['FlashSale', 'FlashSaleItem', 'FlashAllocation', 'CartRule'] as $model) {
            Gate::policy('App\\Domain\\Marketing\\Models\\'.$model, MarketingPolicy::class);
        }
        Gate::policy(ContactMessage::class, ContactMessagePolicy::class);
        Gate::policy(ShoppingList::class, ShoppingListPolicy::class);
        Gate::policy(ShoppingListItem::class, ShoppingListItemPolicy::class);
        foreach (['StoreNotification', 'NoticeDelivery', 'NotificationPreference', 'ProductWatch'] as $model) {
            Gate::policy('App\\Domain\\Customers\\Models\\'.$model, 'App\\Domain\\Customers\\Policies\\'.$model.'Policy');
        }
        Event::listen(CatalogChanged::class, function ($event): void {
            $id = $event->record instanceof Product ? $event->record->id : ($event->record instanceof ProductVariant ? $event->record->product_id : null);
            if ($id) {
                try {
                    CheckProductWatches::dispatch((int) $id);
                } catch (\Throwable $e) {
                    Log::warning('Product watch check pending', ['product_id' => $id]);
                }
            }
        });
        foreach (['Review', 'Question', 'ReviewVote', 'ReviewRequest', 'SavedCoupon'] as $model) {
            Gate::policy('App\\Domain\\Marketing\\Models\\'.$model, 'App\\Domain\\Marketing\\Policies\\'.$model.'Policy');
        }
        foreach (['NewsletterSubscriber', 'NewsletterCampaign', 'NewsletterMessage'] as $model) {
            Gate::policy('App\\Domain\\Marketing\\Models\\'.$model, 'App\\Domain\\Marketing\\Policies\\'.$model.'Policy');
        }
        Gate::policy(Setting::class, SettingPolicy::class);
        Gate::policy(Cart::class, CartPolicy::class);
        Gate::policy(CartItem::class, CartItemPolicy::class);
        foreach ([Location::class, ShippingZone::class, ShippingMethod::class, TaxClass::class, TaxRate::class, Currency::class, Coupon::class, Order::class] as $model) {
            Gate::policy($model, CommercePolicy::class);
        }
        Gate::policy(Address::class, AddressPolicy::class);
        foreach (['OrderEvent', 'OrderPayment', 'OrderRefund', 'OrderReturn', 'Shipment', 'DownloadEntitlement'] as $child) {
            Gate::policy('App\\Domain\\Orders\\Models\\'.$child, OrderChildPolicy::class);
        }
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(User::class, CustomerPolicy::class);
        Gate::policy(BlacklistedPhone::class, BlacklistPolicy::class);
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
