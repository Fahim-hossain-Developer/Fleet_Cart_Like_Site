<?php

return [
    'defaults' => ['name' => 'Commerce', 'currency' => 'BDT', 'locale' => 'en', 'timezone' => 'Asia/Dhaka'],
    'rules' => ['name' => 'required|string|max:100', 'currency' => 'required|in:BDT,USD,EUR,GBP', 'locale' => 'required|in:en,bn', 'timezone' => 'required|timezone'],
    'sms_driver' => env('SMS_DRIVER', 'log'),
    'courier_driver' => env('COURIER_DRIVER', 'fake'),
    'payment_driver' => env('PAYMENT_DRIVER', 'fake'),
];
