<?php

namespace App\Domain\Content\Actions;

use App\Domain\Content\Models\Popup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class StorefrontOverlay
{
    public function allowed(Request $r): bool
    {
        return ! $r->has('theme_preview') && in_array($r->route()?->getName(), ['home', 'shop', 'search', 'category', 'brand', 'product', 'blog', 'blog.post', 'page', 'faq', 'contact', 'offers'], true);
    }

    /** @param array<int,string>|null $paths */
    public function matches(?array $paths, Request $r): bool
    {
        if (! $paths) {
            return true;
        }
        foreach ($paths as $path) {
            if (Str::is($path, '/'.ltrim($r->path(), '/'))) {
                return true;
            }
        }

        return false;
    }

    public function popup(Request $r): ?Popup
    {
        if (! $this->allowed($r)) {
            return null;
        }

        return Popup::published()->orderBy('sort')->orderBy('id')->get()->first(fn ($p) => $this->matches($p->paths, $r));
    }
}
