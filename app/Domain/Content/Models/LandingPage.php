<?php

namespace App\Domain\Content\Models;

use App\Domain\Content\Landing\LandingRules;
use App\Domain\Settings\Theme\ThemeConfig;
use Database\Factories\LandingPageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class LandingPage extends ContentModel
{
    /** @use HasFactory<LandingPageFactory> */
    use HasFactory;

    protected $casts = ['title' => 'array', 'sections' => 'array', 'form_config' => 'array', 'theme_config' => 'array', 'seo' => 'array', 'is_active' => 'boolean', 'published_at' => 'datetime', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'views' => 'integer', 'orders_count' => 'integer', 'sort' => 'integer'];

    protected $attributes = ['status' => 'draft', 'is_active' => true, 'sort' => 0, 'sections' => '[]', 'form_config' => '{}'];

    protected static function newFactory(): LandingPageFactory
    {
        return LandingPageFactory::new();
    }

    protected static function booted(): void
    {
        parent::booted();
        static::saving(function (self $p) {
            app(LandingRules::class)->sections($p->sections);
            $p->form_config = array_replace(app(LandingRules::class)->formDefaults(), $p->form_config ?? []);
            app(LandingRules::class)->form($p->form_config);
            foreach (['bump', 'upsell', 'downsell'] as $role) {
                $id = $p->form_config[$role.'_package_id'] ?? null;
                if ($id && ! ProductPackage::where('landing_page_id', $p->id)->where('role', $role)->whereKey($id)->exists()) {
                    throw ValidationException::withMessages(['form_config' => __('landing.package_unavailable')]);
                }
            }if ($p->theme_config) {
                $p->theme_config = app(ThemeConfig::class)->validate($p->theme_config);
            }
        });
    }

    /** @return HasMany<ProductPackage,$this> */
    public function packages(): HasMany
    {
        return $this->hasMany(ProductPackage::class)->orderBy('sort')->orderBy('id');
    }
}
