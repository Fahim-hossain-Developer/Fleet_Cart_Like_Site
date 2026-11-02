<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,string> */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100', 'phone' => 'required|string|max:30', 'email' => 'nullable|email:rfc|max:255', 'country' => 'required|in:BD', 'division_id' => 'required|integer|exists:locations,id', 'district_id' => 'required|integer|exists:locations,id', 'upazila_id' => 'required|integer|exists:locations,id', 'area_id' => 'nullable|integer|exists:locations,id', 'area' => 'nullable|string|max:100', 'address' => 'required|string|min:5|max:500', 'shipping_method_id' => 'required|integer|min:0', 'payment_method' => 'required|in:cod,bank,manual_mobile', 'payment_reference' => 'nullable|string|max:100', 'note' => 'nullable|string|max:1000', 'terms' => 'accepted',
            'is_gift' => 'boolean', 'gift_message' => 'nullable|string|max:500', 'recipient_name' => 'nullable|string|max:100', 'recipient_phone' => 'nullable|string|max:30', 'hide_prices' => 'boolean', 'create_account' => 'boolean', 'password' => 'nullable|string|min:12|max:128|confirmed', 'idempotency_key' => 'required|uuid', 'website' => 'nullable|size:0', 'payment_proof' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ];
    }
}
