<?php

namespace App\Domain\Content\Landing;

use App\Domain\Content\Models\LandingPage;
use App\Domain\Content\Models\LandingTemplate;
use App\Models\Admin;
use Illuminate\Support\Str;

final class LandingTemplates
{
    public function staff(): Admin
    {
        $a = auth('admin')->user();
        abort_unless($a instanceof Admin && $a->is_active && $a->hasPermissionTo('landing.manage', 'admin'), 403);

        return $a;
    }

    /** @return array<int,array<string,mixed>> */
    public function starters(): array
    {
        $recipes = [
            ['general', 'General product', 'সাধারণ পণ্য', ['hero', 'benefits', 'gallery', 'specs', 'reviews', 'faq', 'order_form', 'footer']],
            ['health-herbal', 'Ingredients and care', 'উপকরণ ও যত্ন', ['hero', 'problem_solution', 'specs', 'gallery', 'delivery', 'faq', 'order_form', 'footer']],
            ['gadget', 'Gadget details', 'গ্যাজেটের বিবরণ', ['hero', 'video', 'benefits', 'specs', 'comparison', 'faq', 'order_form', 'footer']],
            ['fashion', 'Fashion story', 'পোশাকের গল্প', ['hero', 'gallery', 'benefits', 'specs', 'delivery', 'reviews', 'order_form', 'footer']],
            ['food', 'Food and preparation', 'খাবার ও প্রস্তুতি', ['hero', 'specs', 'gallery', 'delivery', 'faq', 'order_form', 'footer']],
            ['service', 'Service-style information', 'সেবা ধরনের তথ্য', ['hero', 'benefits', 'comparison', 'delivery', 'faq', 'order_form', 'footer']]];
        $result = [];
        foreach ($recipes as [$slug,$en,$bn,$types]) {
            $sections = [];
            foreach ($types as $index => $type) {
                $sections[] = ['key' => $type.'-'.$index, 'type' => $type, 'title' => ['en' => __('landing.section_'.$type, [], 'en'), 'bn' => __('landing.section_'.$type, [], 'bn')], 'body' => ['en' => $type === 'hero' ? '<p>Original product information. Replace this sample with accurate details before publishing.</p>' : '', 'bn' => $type === 'hero' ? '<p>মূল পণ্যের তথ্য। প্রকাশের আগে সঠিক তথ্য দিয়ে এই নমুনা বদলে দিন।</p>' : ''], 'config' => []];
            }
            $result[] = ['slug' => $slug, 'name' => ['en' => $en, 'bn' => $bn], 'sections' => $sections, 'form_config' => app(LandingRules::class)->formDefaults(), 'is_system' => true];
        }

        return $result;
    }

    public function seed(): void
    {
        foreach ($this->starters() as $template) {
            LandingTemplate::firstOrCreate(['slug' => $template['slug']], $template);
        }
    }

    public function save(LandingPage $page, string $name): LandingTemplate
    {
        $a = $this->staff();
        validator(compact('name'), ['name' => 'required|string|max:100'])->validate();
        $config = $page->form_config;
        foreach (['bump_package_id', 'upsell_package_id', 'downsell_package_id'] as $key) {
            unset($config[$key]);
        }
        $template = LandingTemplate::create(['slug' => Str::slug($name).'-'.strtolower(Str::random(8)), 'name' => ['en' => $name, 'bn' => $name], 'sections' => $page->sections, 'form_config' => $config]);
        activity()->causedBy($a)->performedOn($template)->log('landing_template_created');

        return $template;
    }

    public function apply(LandingPage $page, LandingTemplate $template): void
    {
        $a = $this->staff();
        $page->update(['sections' => $template->sections, 'form_config' => $template->form_config]);
        activity()->causedBy($a)->performedOn($page)->log('landing_template_applied');
    }

    public function export(LandingTemplate $template): string
    {
        $a = $this->staff();
        activity()->causedBy($a)->performedOn($template)->log('landing_template_exported');

        return json_encode(['format' => 'commerce-landing-template', 'version' => 1, 'name' => $template->name, 'sections' => $template->sections, 'form_config' => $template->form_config], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    public function import(string $json): LandingTemplate
    {
        $a = $this->staff();
        abort_if(strlen($json) > 262144, 422);
        try {
            $d = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            abort(422, __('content.invalid_content'));
        }
        validator(['t' => $d], ['t' => 'required|array:format,version,name,sections,form_config', 't.format' => 'required|in:commerce-landing-template', 't.version' => 'required|integer|in:1', 't.name' => 'required|array:en,bn', 't.name.en' => 'required|string|max:100', 't.name.bn' => 'nullable|string|max:100', 't.sections' => 'required|array', 't.form_config' => 'required|array'])->validate();
        $forbidden = ['bump_package_id', 'upsell_package_id', 'downsell_package_id'];
        foreach ($forbidden as $key) {
            abort_if(isset($d['form_config'][$key]), 422);
        }
        $template = LandingTemplate::create(['slug' => 'imported-'.strtolower(Str::random(16)), 'name' => $d['name'], 'sections' => $d['sections'], 'form_config' => $d['form_config']]);
        activity()->causedBy($a)->performedOn($template)->log('landing_template_imported');

        return $template;
    }
}
