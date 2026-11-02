<?php

namespace App\Domain\Customers\Actions;

use App\Domain\Customers\Models\Address;
use App\Domain\Shipping\Models\Location;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveAddress
{
    /** @param array<string,mixed> $data */
    public function execute(User $user, array $data, ?Address $address = null): Address
    {
        abort_if($user->is_blocked, 403);
        if ($address) {
            abort_unless($address->user_id === $user->id, 403);
        }
        $d = validator($data, ['name' => 'required|string|max:100', 'phone' => 'required|string|max:32', 'country' => 'required|in:BD', 'division_id' => 'required|integer', 'district_id' => 'required|integer', 'upazila_id' => 'required|integer', 'area_id' => 'nullable|integer', 'area' => 'nullable|string|max:100', 'line1' => 'required|string|max:500', 'is_default' => 'sometimes|boolean'])->validate();
        $d['phone'] = app(NormalizePhone::class)->execute($d['phone']);
        $parent = null;
        foreach (['division', 'district', 'upazila'] as $type) {
            $q = Location::where('type', $type)->where('country', 'BD');
            if ($parent) {
                $q->where('parent_id', $parent);
            }$location = $q->find($d[$type.'_id']);
            if (! $location) {
                throw ValidationException::withMessages(['address' => __('checkout.invalid_address')]);
            }$parent = $location->id;
        }
        if (! empty($d['area_id']) && ! Location::where('type', 'area')->where('parent_id', $parent)->whereKey($d['area_id'])->exists()) {
            throw ValidationException::withMessages(['address' => __('checkout.invalid_address')]);
        }

        return DB::transaction(function () use ($user, $address, $d) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if ($d['is_default'] ?? false) {
                Address::where('user_id', $user->id)->update(['is_default' => false]);
            }$a = $address ?? new Address;
            $a->fill($d + ['user_id' => $user->id]);
            $a->save();

            return $a;
        }, 3);
    }
}
