<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Rider Shipping-Fee Earnings
    |--------------------------------------------------------------------------
    |
    | BiliHub rider earnings are funded only from the shipping fee allocated
    | to the seller/shipment. Seller merchandise earnings and platform/admin
    | commission are not touched by this wallet split.
    |
    | Default ₱50 shipment split:
    | - Pickup rider:       ₱15.00
    | - Final delivery:     ₱25.00
    | - Logistics provider:  ₱10.00
    |
    | Credits are still released only after the buyer confirms the order as
    | received (manual or automatic). The combined rider payout is always
    | capped by the SellerOrder shipping fee.
    |
    */
    'pickup_earning_minor' => 1500,
    'delivery_earning_minor' => 2500,
    'logistics_earning_minor' => 1000,
    'delivery_earning_source' => 'shipping_fee',

    // Minimum rider withdrawal, in centavos.
    'minimum_withdrawal_minor' => 10000,

    // Prevent a rider from creating another request while one is pending.
    'single_pending_withdrawal' => true,
];
