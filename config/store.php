<?php

return [
    'defaults' => ['name' => 'Commerce', 'currency' => 'BDT', 'locale' => 'en', 'timezone' => 'Asia/Dhaka'],
    'rules' => ['orders_stock_policy' => 'required|in:placement,confirmation,payment', 'orders_high_value_cod' => 'required|integer|min:1|max:100000000000', 'checkout_order_prefix' => 'required|string|max:12|regex:/^[A-Z0-9-]+$/D', 'checkout_mode' => 'in:one_page,steps', 'checkout_guest_enabled' => 'boolean', 'checkout_gift_enabled' => 'boolean', 'checkout_gift_fee' => 'integer|min:0|max:100000000', 'checkout_gift_tax_bps' => 'integer|min:0|max:10000', 'checkout_auto_account' => 'boolean', 'checkout_payment_methods' => 'array', 'catalog_top_bar' => 'boolean', 'catalog_max_badges' => 'integer|min:0|max:10', 'catalog_new_days' => 'integer|min:0|max:365', 'catalog_clone_keep_stock' => 'boolean', 'catalog_sidebar' => 'array', 'name' => 'required|string|max:100', 'currency' => 'required|in:BDT,USD,EUR,GBP', 'locale' => 'required|in:en,bn', 'timezone' => 'required|timezone'],
    'sms_driver' => env('SMS_DRIVER', 'log'),
    'courier_driver' => env('COURIER_DRIVER', 'fake'),
    'payment_driver' => env('PAYMENT_DRIVER', 'fake'),
];
