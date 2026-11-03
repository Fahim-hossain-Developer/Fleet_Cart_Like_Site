<?php

namespace Database\Seeders;

use App\Domain\Settings\Models\ThemePreset;
use App\Domain\Settings\Theme\ThemeConfig;
use Illuminate\Database\Seeder;

class ThemePresetSeeder extends Seeder
{
    public function run(): void
    {
        $recipes = [
            'original-sage' => ['Original sage', 'মূল সেজ', ['primary' => '#065f46']],
            'quiet-linen' => ['Quiet linen', 'শান্ত লিনেন', ['primary' => '#7c2d12', 'background' => '#fffaf3', 'font' => 'lora_noto', 'header' => 'centered', 'footer' => 'columns', 'card' => 'minimal', 'product' => 'thumbs_bottom', 'radius' => 4]],
            'market-day' => ['Market day', 'বাজারের দিন', ['primary' => '#166534', 'font' => 'nunito_hind', 'header' => 'mega', 'card' => 'food', 'listing' => 'top', 'footer' => 'newsletter', 'section_style' => 'soft']],
            'clear-sky' => ['Clear sky', 'পরিষ্কার আকাশ', ['primary' => '#075985', 'font' => 'manrope_noto', 'header' => 'minimal', 'card' => 'overlay', 'product' => 'sticky_info', 'listing' => 'simple']],
            'soft-petal' => ['Soft petal', 'কোমল পাপড়ি', ['primary' => '#9d174d', 'font' => 'dm_hind', 'header' => 'centered', 'card' => 'second_image', 'radius' => 24, 'footer' => 'columns', 'section_style' => 'framed']],
            'studio-ink' => ['Studio ink', 'স্টুডিও কালি', ['primary' => '#312e81', 'font' => 'source_tiro', 'header' => 'minimal', 'card' => 'boxed', 'product' => 'long_page', 'radius' => 0, 'shadow' => 'none', 'section_style' => 'accent']]];
        foreach ($recipes as $slug => [$en,$bn,$overrides]) {
            ThemePreset::firstOrCreate(['slug' => $slug], ['name' => ['en' => $en, 'bn' => $bn], 'config' => array_replace(app(ThemeConfig::class)->defaults(), $overrides), 'is_system' => true]);
        }
    }
}
