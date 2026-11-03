<?php

return [
    'defaults' => ['name' => 'Commerce', 'currency' => 'BDT', 'locale' => 'en', 'timezone' => 'Asia/Dhaka'],
    'rules' => [
        'store_mode' => 'required|in:multi,single', 'primary_landing_page' => 'nullable|integer|exists:landing_pages,id', 'single_show_catalog' => 'boolean',
        'tracking_enabled' => 'boolean', 'tracking_capi' => 'boolean', 'tracking_ids' => 'array:ga4,gtm,meta,tiktok',
        'social_login_google' => 'boolean', 'social_login_facebook' => 'boolean',
        'seo_defaults' => 'array:title,description,schema',
        'floating_controls' => 'array:position,paths,exclude,whatsapp,messenger,call,back_to_top,chat',
        'social_proof' => 'array:enabled,frequency,exclude',
        'recovery_enabled' => 'boolean',
        'reviews_moderate' => 'boolean', 'reviews_require_verified' => 'boolean', 'reviews_min_length' => 'integer|min:10|max:1000', 'reviews_allow_photos' => 'boolean', 'reviews_max_photos' => 'integer|min:0|max:5', 'reviews_blocked_words' => 'array|max:100', 'reviews_request_enabled' => 'boolean', 'reviews_request_days' => 'integer|min:1|max:30',
        'sms_driver' => 'required|in:sms_log,sms_twilio,sms_custom',
        'admin_otp_roles' => 'array', 'cod_advance_mode' => 'in:off,fixed,percent,delivery', 'cod_advance_value' => 'integer|min:0|max:100000000000', 'orders_stock_policy' => 'required|in:placement,confirmation,payment', 'orders_high_value_cod' => 'required|integer|min:1|max:100000000000', 'checkout_order_prefix' => 'required|string|max:12|regex:/^[A-Z0-9-]+$/D', 'checkout_mode' => 'in:one_page,steps', 'checkout_guest_enabled' => 'boolean', 'checkout_gift_enabled' => 'boolean', 'checkout_gift_fee' => 'integer|min:0|max:100000000', 'checkout_gift_tax_bps' => 'integer|min:0|max:10000', 'checkout_auto_account' => 'boolean', 'checkout_payment_methods' => 'array', 'catalog_top_bar' => 'boolean', 'catalog_max_badges' => 'integer|min:0|max:10', 'catalog_new_days' => 'integer|min:0|max:365', 'catalog_clone_keep_stock' => 'boolean', 'catalog_sidebar' => 'array', 'name' => 'required|string|max:100', 'currency' => 'required|in:BDT,USD,EUR,GBP', 'locale' => 'required|in:en,bn', 'timezone' => 'required|timezone'],
    'sms_driver' => env('SMS_DRIVER', 'log'),
    'courier_driver' => env('COURIER_DRIVER', 'fake'),
    'payment_driver' => env('PAYMENT_DRIVER', 'fake'),
];
