<?php

namespace App\Domain\Content\Actions;

use App\Domain\Content\Models\ContentModel;
use App\Domain\Content\Models\HomeSection;
use App\Domain\Content\Models\MenuItem;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class ValidateContent
{
    public function execute(ContentModel $m): void
    {
        validator(['title' => $m->getTranslations('title'), 'status' => $m->status, 'sort' => $m->sort, 'published_at' => $m->published_at, 'starts_at' => $m->starts_at, 'ends_at' => $m->ends_at], ['title.en' => 'required|string|max:200', 'title.bn' => 'nullable|string|max:200', 'status' => 'required|in:draft,published', 'sort' => 'required|integer|min:0|max:100000', 'published_at' => 'nullable|date', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after:starts_at'])->validate();
        foreach (['title', 'body', 'excerpt'] as $key) {
            $v = $m->getTranslations($key);
            validator(['v' => $v], ['v' => 'array:en,bn', 'v.*' => 'nullable|string|max:100000'])->validate();
        }
        if ($m->getAttribute('slug') !== null) {
            validator(['slug' => $m->slug], ['slug' => 'required|string|max:160|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D'])->validate();
        }
        foreach (['url'] as $key) {
            if (! SafeContent::link($m->getAttribute($key))) {
                $this->fail($key);
            }
        }
        foreach (['image', 'mobile_image'] as $key) {
            if (! SafeContent::image($m->getAttribute($key))) {
                $this->fail($key);
            }
        }
        $seo = $m->getAttribute('seo');
        if ($seo) {
            validator(['seo' => $seo], ['seo' => 'array:en,bn,canonical,image', 'seo.en' => 'array:title,description', 'seo.bn' => 'array:title,description', 'seo.*.title' => 'nullable|string|max:200', 'seo.*.description' => 'nullable|string|max:500'])->validate();
            if (! SafeContent::link($seo['canonical'] ?? null) || ! SafeContent::image($seo['image'] ?? null)) {
                $this->fail('seo');
            }
        }
        if ($m instanceof MenuItem) {
            $seen = [$m->id];
            $parent = $m->parent_id;
            $depth = 1;
            while ($parent) {
                $p = MenuItem::find($parent);
                if (! $p || $p->menu_id !== $m->menu_id || in_array($parent, $seen, true) || ++$depth > 3) {
                    $this->fail('parent_id');
                }$seen[] = $parent;
                $parent = $p->parent_id;
            }
            $children = $m->exists ? MenuItem::where('parent_id', $m->id)->pluck('id')->all() : [];
            while ($children) {
                if (++$depth > 3) {
                    $this->fail('parent_id');
                }$children = MenuItem::whereIn('parent_id', $children)->pluck('id')->all();
            }
        }
        if ($m instanceof HomeSection) {
            $config = $m->config ?? [];
            if (! empty($config['ends_at'])) {
                $config['ends_at'] = Carbon::parse($config['ends_at'])->toIso8601String();
                $m->config = $config;
            }
            app(SectionCatalog::class)->validate($m->type, $m->config ?? []);
        }
    }

    private function fail(string $key): never
    {
        throw ValidationException::withMessages([$key => __('content.invalid_content')]);
    }
}
