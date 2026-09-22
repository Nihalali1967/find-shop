<?php

return [
    'brand' => env('MARKETPLACE_BRAND', 'Material / Space'),

    'currency' => [
        'code' => 'INR',
        'symbol' => '₹',
    ],

    'otp' => [
        'length' => (int) env('OTP_LENGTH', 6),
        'ttl_seconds' => (int) env('OTP_TTL', 300),
        'resend_seconds' => (int) env('OTP_RESEND', 60),
        'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
        'max_per_phone_per_hour' => (int) env('OTP_MAX_PER_PHONE', 5),
        'max_per_ip_per_hour' => (int) env('OTP_MAX_PER_IP', 20),
        'global_hourly_cap' => (int) env('OTP_GLOBAL_CAP', 2000),
    ],

    'purposes' => [
        'client_login',
        'shop_login',
        'shop_registration',
        'phone_change',
        'shop_claim',
    ],

    'products' => [
        'max_images' => 10,
        'image_max_kb' => 5120,
        'description_max' => 10000,
    ],

    'search' => [
        'max_query_length' => 120,
        'max_tokens' => 8,
        'max_typo_distance' => 1,
        'min_typo_word_length' => 4,
    ],

    'chat' => [
        'max_message_length' => 2000,
        'poll_interval_ms' => 5000,
    ],
];
