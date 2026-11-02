<?php

namespace App\Domain\Content\Actions;

use App\Domain\Catalog\Models\Product;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Models\Redirect;
use Illuminate\Http\RedirectResponse;

final class ResolveRedirect
{
    public function execute(string $path): ?RedirectResponse
    {
        $seen = [$path];
        $code = null;
        $to = $path;
        for ($i = 0; $i < 6; $i++) {
            $r = Redirect::where('from', $to)->first();
            if (! $r) {
                break;
            }
            if (! SafeContent::link($r->to) || in_array($r->to, $seen, true)) {
                return null;
            }
            $code ??= (int) $r->code;
            $to = $r->to;
            $seen[] = $to;
        }
        if (! $code || Redirect::where('from', $to)->exists()) {
            return null;
        }
        if (preg_match('~^/(product|page|blog)/([^/?#]+)$~', $to, $m)) {
            $published = match ($m[1]) {
                'product' => Product::published()->where('slug', $m[2])->exists(),'page' => Page::published()->where('slug', $m[2])->exists(),'blog' => Post::published()->where('slug', $m[2])->where(fn ($q) => $q->whereNull('post_category_id')->orWhereHas('category', fn ($q) => $q->published()))->exists()
            };
            if (! $published) {
                return null;
            }
        }

        return redirect($to, in_array($code, [301, 302], true) ? $code : 301);
    }
}
