<?php

namespace Database\Seeders;

use App\Domain\Checkout\Models\TaxClass;
use App\Domain\Settings\Models\Currency;
use App\Domain\Shipping\Models\Location;
use App\Domain\Shipping\Models\ShippingMethod;
use App\Domain\Shipping\Models\ShippingZone;
use Illuminate\Database\Seeder;

final class CheckoutSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode(file_get_contents(database_path('data/bangladesh-locations.json')), true, 512, JSON_THROW_ON_ERROR);
        $divisionIds = [];
        $districtIds = [];
        foreach ($data['divisions'] as $row) {
            $divisionIds[$row['id']] = Location::firstOrCreate(['code' => 'BD-DIV-'.$row['id']], ['type' => 'division', 'country' => 'BD', 'name' => ['en' => $row['name'], 'bn' => $row['bn_name']]])->id;
        }
        foreach ($data['districts'] as $row) {
            $districtIds[$row['id']] = Location::firstOrCreate(['code' => 'BD-DIST-'.$row['id']], ['type' => 'district', 'country' => 'BD', 'parent_id' => $divisionIds[$row['division_id']], 'name' => ['en' => $row['name'], 'bn' => $row['bn_name']]])->id;
        }
        foreach ($data['upazilas'] as $row) {
            Location::firstOrCreate(['code' => 'BD-UPZ-'.$row['id']], ['type' => 'upazila', 'country' => 'BD', 'parent_id' => $districtIds[$row['district_id']], 'name' => ['en' => $row['name'], 'bn' => $row['bn_name']]]);
        }
        Currency::firstOrCreate(['code' => 'BDT'], ['symbol' => '৳', 'rate_ppm' => 1000000, 'decimals' => 2, 'is_active' => true]);
        TaxClass::firstOrCreate(['name->en' => 'Standard'], ['name' => ['en' => 'Standard', 'bn' => 'সাধারণ']]);
        // Example rates are local-only, not real courier quotes or production pricing.
        if (app()->environment('local')) {
            $dhaka = Location::where('type', 'district')->where('name->en', 'Dhaka')->first();
            $inside = ShippingZone::firstOrCreate(['name->en' => 'Inside Dhaka'], ['name' => ['en' => 'Inside Dhaka', 'bn' => 'ঢাকার ভিতরে'], 'country' => 'BD', 'district_ids' => $dhaka ? [$dhaka->id] : [], 'is_active' => true]);
            $outside = ShippingZone::firstOrCreate(['name->en' => 'Outside Dhaka'], ['name' => ['en' => 'Outside Dhaka', 'bn' => 'ঢাকার বাইরে'], 'country' => 'BD', 'district_ids' => array_values(array_filter($districtIds, fn ($id) => $id !== $dhaka?->id)), 'is_active' => true]);
            foreach ([[$inside, 8000], [$outside, 15000]] as [$zone,$amount]) {
                ShippingMethod::firstOrCreate(['shipping_zone_id' => $zone->id, 'type' => 'flat'], ['name' => ['en' => 'Standard delivery', 'bn' => 'সাধারণ ডেলিভারি'], 'amount' => $amount, 'is_active' => true]);
            }
        }
    }
}
