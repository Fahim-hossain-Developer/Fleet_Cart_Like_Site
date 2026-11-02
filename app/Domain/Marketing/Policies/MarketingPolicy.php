<?php

namespace App\Domain\Marketing\Policies;

use App\Domain\Marketing\Models\FlashAllocation;
use App\Domain\Marketing\Models\FlashSale;
use App\Domain\Marketing\Models\FlashSaleItem;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Model;

final class MarketingPolicy
{
    public function viewAny(Admin $a): bool
    {
        return $a->is_active && $a->hasPermissionTo('marketing.manage', 'admin');
    }

    public function view(Admin $a, Model $m): bool
    {
        return $this->viewAny($a);
    }

    public function create(Admin $a): bool
    {
        return $this->viewAny($a);
    }

    public function update(Admin $a, Model $m): bool
    {
        return $this->viewAny($a);
    }

    public function delete(Admin $a, Model $m): bool
    {
        if (! $this->viewAny($a)) {
            return false;
        }
        if ($m instanceof FlashSaleItem) {
            return ! FlashAllocation::where('flash_sale_item_id', $m->id)->exists();
        }
        if ($m instanceof FlashSale) {
            return ! FlashAllocation::whereIn('flash_sale_item_id', $m->items()->pluck('id'))->exists();
        }

        return true;
    }

    public function deleteAny(Admin $a): bool
    {
        return $this->viewAny($a);
    }

    public function reorder(Admin $a): bool
    {
        return $this->viewAny($a);
    }
}
