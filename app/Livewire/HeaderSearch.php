<?php

namespace App\Livewire;

use App\Domain\Catalog\Actions\CatalogSearch;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class HeaderSearch extends Component
{
    public string $term = '';

    public string $category = '';

    public function render(): View
    {
        $query = app(CatalogSearch::class)->apply(Product::published(), $this->term);
        if ($this->category !== '') {
            $query->whereHas('categories', fn ($q) => $q->where('categories.id', (int) $this->category));
        }

        return view('livewire.header-search', ['suggestions' => mb_strlen(trim($this->term)) >= 2 ? $query->limit(5)->get() : collect(), 'categories' => Category::where('is_active', true)->get(), 'categorySuggestions' => mb_strlen(trim($this->term)) >= 2 ? Category::where('is_active', true)->when($this->category !== '', fn ($q) => $q->whereKey((int) $this->category))->get()->filter(fn ($c) => mb_stripos($c->name, trim($this->term)) !== false)->take(3) : collect(), 'popular' => DB::table('search_logs')->select('term')->selectRaw('COUNT(*) as frequency')->groupBy('term')->orderByDesc('frequency')->limit(5)->pluck('term')]);
    }
}
