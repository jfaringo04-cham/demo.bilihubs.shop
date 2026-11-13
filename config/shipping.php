<?php

return [
    /*
    |--------------------------------------------------------------------------
    | BiliHub Distance-Based Shipping
    |--------------------------------------------------------------------------
    |
    | Base fee: PHP 50
    | First 3 km: included
    | Additional distance: PHP 4 per started 3-km block
    |
    */

    'base_fee_minor' => 5000,

    'included_distance_km' => 3,

    'additional_block_km' => 3,

    'additional_per_block_minor' => 400,

    // New payout split applies only to orders created on/after this instant.
    // Deploy only ON OR AFTER this timestamp to prevent a pricing/payout gap.
    // 2026-11-10 00:00 Asia/Manila = 2026-11-09 16:00 UTC.
    'bonus_effective_at' => '2026-11-10T00:00:00+08:00',

    // Set to the rollout instant (ISO 8601 with timezone) to opt in new orders.
    'linehaul_bonus_effective_at' => env('LINEHAUL_BONUS_EFFECTIVE_AT'),
];
