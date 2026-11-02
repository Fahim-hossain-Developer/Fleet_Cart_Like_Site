<?php

return [
    'defaults' => ['name' => 'Commerce', 'currency' => 'BDT', 'locale' => 'en', 'timezone' => 'Asia/Dhaka'],
    'rules' => ['catalog_top_bar' => 'boolean', 'catalog_max_badges' => 'integer|min:0|max:10', 'catalog_new_days' => 'integer|min:0|max:365', 'catalog_clone_keep_stock' => 'boolean', 'catalog_sidebar' => 'array', 'name' => 'required|string|max:100', 'currency' => 'required|in:BDT,USD,EUR,GBP', 'locale' => 'required|in:en,bn', 'timezone' => 'required|timezone'],
    'sms_driver' => env('SMS_DRIVER', 'log'),
    'courier_driver' => env('COURIER_DRIVER', 'fake'),
    'payment_driver' => env('PAYMENT_DRIVER', 'fake'),
];
