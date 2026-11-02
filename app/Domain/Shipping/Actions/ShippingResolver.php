<?php

namespace App\Domain\Shipping\Actions;

use App\Domain\Shipping\Models\Location;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Models\ShippingZone;
use Illuminate\Validation\ValidationException;

final class ShippingResolver
{
    /**
     * @return array<int,array{id:int,name:string,amount:int}> */
    public function methods(string $country, int $district, int $subtotal, int $weight, bool $free = false): array
    {
        $location = Location::where('type', 'district')->where('country', $country)->find($district);
        if (! $location) {
            return [];
        }
        $zones = ShippingZone::where('country', $country)->where('is_active', true)->get()->filter(fn ($z) => in_array($district, array_map('intval', $z->district_ids), true))->modelKeys();
        $result = [];
        foreach (ShippingMethod::whereIn('shipping_zone_id', $zones)->where('is_active', true)->get() as $method) {
            $amount = ($free || $method->type === 'pickup' || ($method->free_above !== null && $subtotal >= $method->free_above)) ? 0 : $method->amount;
            if ($amount && $method->type === 'weight') {
                $amount += max(0, (int) ceil(($weight - $method->base_weight_grams) / max(1, $method->step_weight_grams))) * $method->step_amount;
            }
            if ($amount > 100000000000) {
                throw ValidationException::withMessages(['shipping_method_id' => __('checkout.amount_limit')]);
            }
            $result[$method->id] = ['id' => $method->id, 'name' => $method->name[app()->getLocale()] ?? $method->name['en'], 'amount' => $amount];
        }

        return $result;
    }

    /** @return array{id:int,name:string,amount:int} */
    public function resolve(string $country, int $district, int $method, int $subtotal, int $weight, bool $free = false): array
    {
        $methods = $this->methods($country, $district, $subtotal, $weight, $free);
        if (! isset($methods[$method])) {
            throw ValidationException::withMessages(['shipping_method_id' => __('checkout.unsupported_shipping')]);
        }

        return $methods[$method];
    }
}
