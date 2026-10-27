<?php

return [

    /*
    |--------------------------------------------------------------------------
    | BiliHub Footer
    |--------------------------------------------------------------------------
    |
    | Shared footer content used by every BiliHub layout (buyer, seller,
    | admin and rider) through the <x-footer /> Blade component.
    |
    */

    'tagline' => env('BILIHUB_TAGLINE', 'Your marketplace, delivered.'),


    /*
    |--------------------------------------------------------------------------
    | Checkout Shipping
    |--------------------------------------------------------------------------
    |
    | Shipping is charged per seller/shipment. A cart containing products from
    | two different sellers creates two SellerOrders/shipments, so the buyer is
    | charged this amount twice. 5000 = PHP 50.00 per seller/shipment.
    |
    */
    'shipping_fee_per_seller_minor' => 5000,

    /*
    | Social links are only rendered when a URL is configured, so the footer
    | never shows an empty or non-functional button. Leave a value blank to
    | hide that network.
    */
    'social' => [
        'facebook' => env('BILIHUB_SOCIAL_FACEBOOK'),
        'instagram' => env('BILIHUB_SOCIAL_INSTAGRAM'),
    ],

];
