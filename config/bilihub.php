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
    | Social links are only rendered when a URL is configured, so the footer
    | never shows an empty or non-functional button. Leave a value blank to
    | hide that network.
    */
    'social' => [
        'facebook' => env('BILIHUB_SOCIAL_FACEBOOK'),
        'instagram' => env('BILIHUB_SOCIAL_INSTAGRAM'),
    ],

];
