<?php

namespace App\Providers;

use App\Domain\Catalog\Policies\CatalogPolicy;
use App\Domain\Settings\Models\Setting;
use App\Domain\Settings\Policies\SettingPolicy;
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
