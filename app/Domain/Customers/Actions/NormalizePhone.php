<?php

namespace App\Domain\Customers\Actions;

use Illuminate\Validation\ValidationException;

final class NormalizePhone
{
    public function execute(string $phone): string
    {
        $phone = preg_replace('/[\s()-]+/', '', $phone);
        if (str_starts_with($phone, '00880')) {
            $phone = '+'.substr($phone, 2);
        }
        if (preg_match('/^01[3-9][0-9]{8}$/D', $phone)) {
            $phone = '+88'.$phone;
        }
        if (! preg_match('/^\+8801[3-9][0-9]{8}$/D', $phone)) {
            throw ValidationException::withMessages(['phone' => __('checkout.invalid_phone')]);
        }

        return $phone;
    }
}
