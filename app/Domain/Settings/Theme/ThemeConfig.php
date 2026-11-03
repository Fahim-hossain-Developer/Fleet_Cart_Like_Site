<?php

namespace App\Domain\Settings\Theme;

use Illuminate\Validation\ValidationException;

final class ThemeConfig
{
    public const HEADERS = ['classic', 'centered', 'minimal', 'mega'];

    public const FOOTERS = ['columns', 'minimal', 'newsletter'];

    public const CARDS = ['minimal', 'second_image', 'overlay', 'boxed', 'food'];

    public const PRODUCTS = ['gallery_left', 'thumbs_bottom', 'sticky_info', 'long_page'];

    public const LISTINGS = ['sidebar', 'top', 'simple'];

    public const FONTS = ['inter_noto', 'manrope_noto', 'lora_noto', 'nunito_hind', 'dm_hind', 'source_tiro'];

    public const STYLES = ['plain', 'soft', 'framed', 'accent'];

    /** @return array<string,mixed> */
    public function defaults(): array
    {
        return ['logo' => null, 'logo_width' => 160, 'primary' => '#065f46', 'secondary' => '#0f172a', 'accent' => '#b45309', 'background' => '#fafaf9', 'radius' => 16, 'shadow' => 'soft', 'font' => 'inter_noto', 'header' => 'classic', 'footer' => 'minimal', 'card' => 'boxed', 'product' => 'gallery_left', 'listing' => 'sidebar', 'section_style' => 'plain'];
    }

    /** @param array<string,mixed> $input
     * @return array<string,mixed> */
    public function validate(array $input): array
    {
        $input = array_replace($this->defaults(), $input);
        $d = validator(['c' => $input], ['c' => 'required|array:primary,secondary,accent,background,radius,shadow,font,header,footer,card,product,listing,section_style,logo,logo_width', 'c.logo' => 'nullable|string|regex:~^branding/[a-zA-Z0-9/_-]+\.(jpg|jpeg|png|webp|avif)$~D', 'c.logo_width' => 'required|integer|min:80|max:300', 'c.primary' => 'required|regex:/^#[a-fA-F0-9]{6}$/D', 'c.secondary' => 'required|regex:/^#[a-fA-F0-9]{6}$/D', 'c.accent' => 'required|regex:/^#[a-fA-F0-9]{6}$/D', 'c.background' => 'required|regex:/^#[a-fA-F0-9]{6}$/D', 'c.radius' => 'required|integer|min:0|max:32', 'c.shadow' => 'required|in:none,soft,lifted', 'c.font' => 'required|in:'.implode(',', self::FONTS), 'c.header' => 'required|in:'.implode(',', self::HEADERS), 'c.footer' => 'required|in:'.implode(',', self::FOOTERS), 'c.card' => 'required|in:'.implode(',', self::CARDS), 'c.product' => 'required|in:'.implode(',', self::PRODUCTS), 'c.listing' => 'required|in:'.implode(',', self::LISTINGS), 'c.section_style' => 'required|in:'.implode(',', self::STYLES)])->validate()['c'];
        if ($this->luminance($d['background']) < 0.65) {
            throw ValidationException::withMessages(['background' => __('appearance.light_background')]);
        }
        foreach (['primary', 'secondary', 'accent', 'background'] as $color) {
            $d[$color] = strtolower($d[$color]);
        }$d['radius'] = (int) $d['radius'];

        return $d;
    }

    public function luminance(string $hex): float
    {
        $parts = [];
        foreach ([1, 3, 5] as $start) {
            $v = hexdec(substr($hex, $start, 2)) / 255;
            $parts[] = $v <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }

        return $parts[0] * 0.2126 + $parts[1] * 0.7152 + $parts[2] * 0.0722;
    }

    /** @param array<string,mixed> $config */
    public function css(array $config): string
    {
        $c = $this->validate($config);
        $on = ($this->luminance($c['primary']) + 0.05) / 0.05 >= 4.5 ? '#000000' : '#ffffff';
        $onSecondary = ($this->luminance($c['secondary']) + 0.05) / 0.05 >= 4.5 ? '#000000' : '#ffffff';
        $link = 1.05 / ($this->luminance($c['primary']) + 0.05) >= 4.5 ? $c['primary'] : '#111827';
        $fonts = match ($c['font']) {
            'manrope_noto' => ['Manrope', 'Noto Sans Bengali'],'lora_noto' => ['Lora', 'Noto Sans Bengali'],'nunito_hind' => ['Nunito Sans', 'Hind Siliguri'],'dm_hind' => ['DM Sans', 'Hind Siliguri'],'source_tiro' => ['Source Sans 3', 'Tiro Bangla'],default => ['Inter', 'Noto Sans Bengali']
        };
        if (app()->getLocale() === 'bn') {
            $fonts = array_reverse($fonts);
        }
        $family = implode(',', array_map(fn ($f) => '"'.$f.'"', $fonts)).',sans-serif';
        $shadow = match ($c['shadow']) {
            'none' => 'none','lifted' => '0 14px 36px rgb(15 23 42 / .12)',default => '0 4px 18px rgb(15 23 42 / .06)'
        };

        return '--theme-link:'.$link.';--theme-on-secondary:'.$onSecondary.';--theme-primary:'.$c['primary'].';--theme-secondary:'.$c['secondary'].';--theme-accent:'.$c['accent'].';--theme-bg:'.$c['background'].';--theme-on-primary:'.$on.';--theme-radius:'.$c['radius'].'px;--theme-shadow:'.$shadow.';--theme-font:'.$family.';';
    }
}
