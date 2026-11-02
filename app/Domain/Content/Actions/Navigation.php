<?php

namespace App\Domain\Content\Actions;

use App\Domain\Content\Models\Menu;

final class Navigation
{
    /** @return array<int,array<string,mixed>> */
    public function tree(string $key): array
    {
        $menu = Menu::published()->where('key', $key)->first();
        if (! $menu) {
            return [];
        }
        $items = $menu->items()->published()->get();
        $build = function (?int $parent, int $depth) use (&$build, $items): array {
            if ($depth > 3) {
                return [];
            }

            return $items->where('parent_id', $parent)->map(fn ($i) => ['id' => $i->id, 'title' => $i->title, 'url' => $i->url, 'new_tab' => $i->new_tab, 'children' => $build($i->id, $depth + 1)])->values()->all();
        };

        return $build(null, 1);
    }
}
