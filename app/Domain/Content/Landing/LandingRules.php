<?php

namespace App\Domain\Content\Landing;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Content\Actions\SafeContent;
use App\Domain\Content\Actions\SectionCatalog;
use App\Domain\Content\Models\LandingPage;
use App\Domain\Content\Models\ProductPackage;
use Illuminate\Validation\ValidationException;

final class LandingRules
{
    public const TYPES = ['hero', 'benefits', 'problem_solution', 'specs', 'gallery', 'before_after', 'reviews', 'faq', 'delivery', 'order_form', 'footer'];

    /** @param array<int,array<string,mixed>> $sections */
    public function sections(array $sections): void
    {
        validator(['sections' => $sections], ['sections' => 'required|array|min:1|max:40', 'sections.*' => 'array:key,type,title,body,config', 'sections.*.key' => 'required|string|max:64|regex:/^[a-z][a-z0-9_-]*$/D|distinct', 'sections.*.type' => 'required|in:'.implode(',', array_unique(array_merge(self::TYPES, SectionCatalog::TYPES))), 'sections.*.title' => 'nullable|array:en,bn', 'sections.*.title.*' => 'nullable|string|max:200', 'sections.*.body' => 'nullable|array:en,bn', 'sections.*.body.*' => 'nullable|string|max:30000', 'sections.*.config' => 'nullable|array'])->validate();
        if (collect($sections)->where('type', 'hero')->count() !== 1) {
            throw ValidationException::withMessages(['sections' => __('landing.one_hero')]);
        }
        if (collect($sections)->where('type', 'order_form')->count() !== 1) {
            throw ValidationException::withMessages(['sections' => __('landing.one_form')]);
        }
        foreach ($sections as $section) {
            $type = $section['type'];
            $config = $section['config'] ?? [];
            if (in_array($type, SectionCatalog::TYPES, true)) {
                app(SectionCatalog::class)->validate($type, $config);

                continue;
            }
            validator(['c' => $config], ['c' => 'array:image,items,url,button_label,style,video_url,ends_at', 'c.video_url' => 'nullable|string|max:2048', 'c.ends_at' => 'nullable|date', 'c.image' => 'nullable|string|max:512', 'c.url' => 'nullable|string|max:2048', 'c.style' => 'nullable|in:plain,soft,framed,accent', 'c.button_label' => 'nullable|array:en,bn', 'c.button_label.*' => 'nullable|string|max:80', 'c.items' => 'nullable|array|max:20', 'c.items.*' => 'array:title,body,image,url', 'c.items.*.title' => 'nullable|array:en,bn', 'c.items.*.title.*' => 'nullable|string|max:200', 'c.items.*.body' => 'nullable|array:en,bn', 'c.items.*.body.*' => 'nullable|string|max:3000', 'c.items.*.image' => 'nullable|string|max:512', 'c.items.*.url' => 'nullable|string|max:2048'])->validate();
            if (! SafeContent::image($config['image'] ?? null) || ! SafeContent::link($config['url'] ?? null) || (! empty($config['video_url']) && ! SafeContent::video($config['video_url']))) {
                $this->invalid();
            }
            foreach ($config['items'] ?? [] as $item) {
                if (! SafeContent::image($item['image'] ?? null) || ! SafeContent::link($item['url'] ?? null)) {
                    $this->invalid();
                }
            }
        }
    }

    /** @param array<string,mixed> $config */
    public function form(array $config): void
    {
        validator(['f' => $config], ['f' => 'array:mode,email,alternate_phone,delivery_note,preferred_time,custom_label,custom_type,custom_choices,phone_otp,bump_package_id,upsell_package_id,downsell_package_id,whatsapp', 'f.mode' => 'required|in:inline,popup,sticky', 'f.email' => 'boolean', 'f.alternate_phone' => 'boolean', 'f.delivery_note' => 'boolean', 'f.preferred_time' => 'boolean', 'f.custom_label' => 'nullable|array:en,bn', 'f.custom_label.*' => 'nullable|string|max:100', 'f.custom_type' => 'nullable|in:text,dropdown', 'f.custom_choices' => 'nullable|array|max:20', 'f.custom_choices.*' => 'string|max:100', 'f.phone_otp' => 'boolean', 'f.bump_package_id' => 'nullable|integer|exists:product_packages,id', 'f.upsell_package_id' => 'nullable|integer|exists:product_packages,id', 'f.downsell_package_id' => 'nullable|integer|exists:product_packages,id', 'f.whatsapp' => 'nullable|regex:/^\+[1-9][0-9]{7,14}$/D'])->validate();
    }

    /** @return array<string,mixed> */
    public function formDefaults(): array
    {
        return ['mode' => 'inline', 'email' => true, 'alternate_phone' => false, 'delivery_note' => true, 'preferred_time' => false, 'custom_type' => 'text', 'custom_choices' => [], 'phone_otp' => false];
    }

    public function package(ProductPackage $package): void
    {
        validator($package->attributesToArray(), ['role' => 'required|in:primary,bump,upsell,downsell', 'label' => 'required|array:en,bn', 'label.en' => 'required|string|max:100', 'label.bn' => 'nullable|string|max:100', 'qty' => 'required|integer|min:1|max:100', 'price' => 'required|integer|min:0|max:100000000000', 'free_shipping' => 'boolean', 'is_active' => 'boolean', 'sort' => 'integer|min:0|max:100000'])->validate();
        $p = Product::findOrFail($package->product_id);
        $page = LandingPage::findOrFail($package->landing_page_id);
        if (($package->role === 'primary' && $page->product_id !== $p->id) || ($p->type === 'variable') !== ($package->variant_id !== null) || ($package->variant_id && ! ProductVariant::where('product_id', $p->id)->whereKey($package->variant_id)->exists())) {
            $this->invalid();
        }
    }

    private function invalid(): never
    {
        throw ValidationException::withMessages(['config' => __('content.invalid_content')]);
    }
}
