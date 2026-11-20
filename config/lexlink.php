<?php

return [
    // Prices (so'm) — demo, to be validated with advocates before pitch
    'prices' => [
        'ai_extra' => 5000, // AI add-ons (claim draft, compare) beyond plan quota
    ],

    // Full response payload retention (days); summary + audit are kept
    'response_retention_days' => (int) env('LEXLINK_RESPONSE_RETENTION_DAYS', 30),

    // Per-user request rate limit (per minute)
    'rate_limit_per_minute' => 10,

    // Alert admin when same subject PINFL is requested more than N times in M hours
    'subject_alert' => ['max' => 3, 'hours' => 24],

    'mock_gov' => [
        'base_url' => env('MOCK_GOV_BASE_URL', env('APP_URL').'/mock-gov/v1'),
        'api_key' => env('MOCK_GOV_API_KEY', 'mock-gov-demo-key'),
        'delay_min' => 2, // seconds until a request is "ready"
        'delay_max' => 8,
        'reject_rate' => 0.0, // random rejection probability (demo uses deterministic rejections via seed data)
        'oneid_client_id' => env('ONEID_CLIENT_ID', 'lexlink-demo'),
        'oneid_client_secret' => env('ONEID_CLIENT_SECRET', 'lexlink-demo-secret'),
    ],

    'ai' => [
        'provider' => env('AI_PROVIDER', 'gemini'),
        'gemini_key' => env('GEMINI_API_KEY'),
        'gemini_model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'gemini_embedding_model' => env('GEMINI_EMBEDDING_MODEL', 'gemini-embedding-001'),
        'disclaimer' => "Bu yuridik maslahat emas — yordamchi tahlil. Yakuniy qaror advokat zimmasida.",
    ],
];
