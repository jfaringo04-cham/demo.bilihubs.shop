<?php

use Illuminate\Support\Facades\Route;

// ============================================================
// GENERAL MOBILE API CONTROLLERS
// ============================================================

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\AddressApiController;
use App\Http\Controllers\Api\Buyer\VoucherController as BuyerVoucherController;
use App\Http\Controllers\Api\Buyer\WishlistController as BuyerWishlistController;

// ============================================================
// RIDER MOBILE API CONTROLLERS
// ============================================================

use App\Http\Controllers\Api\Rider\DashboardController as RiderDashboardController;
use App\Http\Controllers\Api\Rider\OrderController as RiderOrderController;
use App\Http\Controllers\Api\Rider\ScannerController as RiderScannerController;
use App\Http\Controllers\Api\Rider\EarningsController as RiderEarningsController;
use App\Http\Controllers\Api\Rider\NotificationController as RiderNotificationController;
use App\Http\Controllers\Api\Rider\PickupOfferController as RiderPickupOfferController;
use App\Http\Controllers\Api\Rider\CodCollectionController as RiderCodCollectionController;

// ============================================================
// LOGISTICS MOBILE API CONTROLLERS
// ============================================================

use App\Http\Controllers\Api\Logistic\DashboardController as LogisticDashboardController;
use App\Http\Controllers\Api\Logistic\ShipmentController as LogisticShipmentController;

/*
|--------------------------------------------------------------------------
| BILIHUB MOBILE API ROUTES
|--------------------------------------------------------------------------
|
| General API routes for:
| - Authentication
| - Buyer
| - Rider
| - Logistics
|
| COD collection is restricted to authenticated riders.
|
*/

// ============================================================
// PUBLIC API TEST
// ============================================================

Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'BiliHub Flutter successfully connected to Laravel API.',
        'app' => 'BiliHub',
    ]);
});

// ============================================================
// PUBLIC AUTH ROUTES
// ============================================================

Route::post(
    '/auth/register',
    [AuthController::class, 'register']
);

Route::post(
    '/auth/login',
    [AuthController::class, 'login']
);

Route::post(
    '/auth/social/google',
    [AuthController::class, 'googleLogin']
);

Route::post(
    '/auth/social/facebook',
    [AuthController::class, 'facebookLogin']
);

Route::post(
    '/auth/forgot-password',
    [AuthController::class, 'forgotPassword']
);

Route::post(
    '/auth/reset-password',
    [AuthController::class, 'resetPassword']
);

// ============================================================
// PUBLIC PRODUCT ROUTES
// ============================================================

Route::get(
    '/products',
    [ProductController::class, 'index']
);

Route::get(
    '/products/categories',
    [ProductController::class, 'categories']
);

Route::get(
    '/products/sizes',
    [ProductController::class, 'sizes']
);

Route::get(
    '/products/{product}',
    [ProductController::class, 'show']
);

// ============================================================
// PUBLIC PHILIPPINE ADDRESS API
// ============================================================

Route::prefix('addresses')
    ->name('api.addresses.')
    ->group(function () {

        Route::get(
            '/regions',
            [AddressApiController::class, 'regions']
        )->name('regions');

        Route::get(
            '/regions/{regionCode}',
            [AddressApiController::class, 'region']
        )->name('region');

        Route::get(
            '/regions/{regionCode}/provinces',
            [AddressApiController::class, 'regionProvinces']
        )->name('regionProvinces');

        Route::get(
            '/regions/{regionCode}/cities',
            [AddressApiController::class, 'regionCities']
        )->name('regionCities');

        Route::get(
            '/provinces',
            [AddressApiController::class, 'provinces']
        )->name('provinces');

        Route::get(
            '/provinces/{provinceCode}',
            [AddressApiController::class, 'province']
        )->name('province');

        Route::get(
            '/provinces/{provinceCode}/municipalities',
            [AddressApiController::class, 'provinceMunicipalities']
        )->name('provinceMunicipalities');

        Route::get(
            '/provinces/{provinceCode}/cities',
            [AddressApiController::class, 'provinceCities']
        )->name('provinceCities');

        Route::get(
            '/cities',
            [AddressApiController::class, 'cities']
        )->name('cities');

        Route::get(
            '/cities/{cityCode}',
            [AddressApiController::class, 'city']
        )->name('city');

        Route::get(
            '/cities/{cityCode}/barangays',
            [AddressApiController::class, 'cityBarangays']
        )->name('cityBarangays');

        Route::get(
            '/municipalities',
            [AddressApiController::class, 'municipalities']
        )->name('municipalities');

        Route::get(
            '/municipalities/{municipalityCode}',
            [AddressApiController::class, 'municipality']
        )->name('municipality');

        Route::get(
            '/municipalities/{municipalityCode}/barangays',
            [AddressApiController::class, 'municipalityBarangays']
        )->name('municipalityBarangays');

        Route::get(
            '/barangays',
            [AddressApiController::class, 'barangays']
        )->name('barangays');

        Route::get(
            '/barangays/{barangayCode}',
            [AddressApiController::class, 'barangay']
        )->name('barangay');

        Route::get(
            '/districts',
            [AddressApiController::class, 'districts']
        )->name('districts');

        Route::get(
            '/districts/{districtCode}',
            [AddressApiController::class, 'district']
        )->name('district');

        Route::get(
            '/sub-municipalities',
            [AddressApiController::class, 'subMunicipalities']
        )->name('subMunicipalities');

        Route::get(
            '/sub-municipalities/{subMunicipalityCode}',
            [AddressApiController::class, 'subMunicipality']
        )->name('subMunicipality');
    });

// ============================================================
// PROTECTED GENERAL MOBILE ROUTES
// ============================================================

Route::middleware('auth:sanctum')
    ->group(function () {

        // ====================================================
        // AUTHENTICATED USER
        // ====================================================

        Route::post(
            '/auth/logout',
            [AuthController::class, 'logout']
        );

        Route::post(
            '/auth/logout-all',
            [AuthController::class, 'logoutAll']
        );

        Route::post(
            '/auth/refresh',
            [AuthController::class, 'refresh']
        );

        Route::get(
            '/auth/user',
            [AuthController::class, 'user']
        );

        Route::put(
            '/auth/profile',
            [AuthController::class, 'updateProfile']
        );

        Route::put(
            '/auth/password',
            [AuthController::class, 'updatePassword']
        );

        Route::post(
            '/auth/device',
            [AuthController::class, 'registerDevice']
        );

        // ====================================================
        // BUYER ADDRESSES
        // ====================================================

        Route::apiResource(
            'addresses',
            AddressController::class
        )->only([
            'index',
            'store',
            'show',
            'update',
            'destroy',
        ]);

        Route::post(
            '/addresses/{address}/set-default',
            [AddressController::class, 'setDefault']
        );

        // ====================================================
        // BUYER CART
        // ====================================================

        Route::get(
            '/cart',
            [CartController::class, 'index']
        );

        Route::post(
            '/cart',
            [CartController::class, 'store']
        );

        Route::put(
            '/cart/{cartItem}',
            [CartController::class, 'update']
        );

        Route::delete(
            '/cart/{cartItem}',
            [CartController::class, 'destroy']
        );

        Route::delete(
            '/cart',
            [CartController::class, 'clear']
        );

        Route::post(
            '/buy-now',
            [CartController::class, 'buyNow']
        );

        // ====================================================
        // BUYER WISHLIST - MOBILE APP
        // ====================================================

        Route::get(
            '/buyer/wishlist',
            [BuyerWishlistController::class, 'index']
        );

        Route::post(
            '/buyer/wishlist/{product}',
            [BuyerWishlistController::class, 'store']
        );

        Route::delete(
            '/buyer/wishlist/{product}',
            [BuyerWishlistController::class, 'destroy']
        );

        // ====================================================
        // BUYER ORDERS
        // ====================================================

        Route::get(
            '/orders',
            [OrderController::class, 'index']
        );

        Route::post(
            '/orders',
            [OrderController::class, 'store']
        );

        Route::get(
            '/orders/{order}',
            [OrderController::class, 'show']
        );

        Route::post(
            '/orders/{order}/cancel',
            [OrderController::class, 'cancel']
        );

        Route::post(
            '/orders/{order}/rate',
            [OrderController::class, 'rate']
        );

        // ====================================================
        // BUYER VOUCHERS - MOBILE APP
        // ====================================================

        Route::prefix('buyer/vouchers')
            ->name('buyer.vouchers.')
            ->group(function () {

                // Retrieve available vouchers
                Route::get(
                    '/',
                    [BuyerVoucherController::class, 'index']
                )->name('index');

                // Retrieve vouchers claimed by authenticated buyer
                Route::get(
                    '/claimed',
                    [BuyerVoucherController::class, 'claimed']
                )->name('claimed');

                // Claim a voucher
                Route::post(
                    '/{voucher}/claim',
                    [BuyerVoucherController::class, 'claim']
                )->name('claim');

                // Preview voucher discount
                Route::post(
                    '/preview',
                    [BuyerVoucherController::class, 'preview']
                )->name('preview');
            });

        // ====================================================
        // GENERAL NOTIFICATIONS
        // ====================================================

        Route::get(
            '/notifications',
            [NotificationController::class, 'index']
        );

        Route::post(
            '/notifications/{notification}/read',
            [NotificationController::class, 'markAsRead']
        );

        Route::post(
            '/notifications/read-all',
            [NotificationController::class, 'markAllAsRead']
        );

        // ====================================================
        // SHIPMENT TRACKING
        // ====================================================

        Route::get(
            '/shipments/{shipment}/track',
            [ShipmentController::class, 'track']
        );

        Route::get(
            '/orders/{order}/shipment',
            [ShipmentController::class, 'showForOrder']
        );
    });

// ============================================================
// RIDER MOBILE API
// AUTHENTICATION + RIDER ROLE REQUIRED
// ============================================================

Route::middleware([
    'auth:sanctum',
    'rider',
])
    ->prefix('rider')
    ->name('rider.api.')
    ->group(function () {

        // ====================================================
        // RIDER DASHBOARD
        // ====================================================

        Route::get(
            '/dashboard',
            [RiderDashboardController::class, 'index']
        )->name('dashboard');

        // ====================================================
        // RIDER PICKUP OFFERS
        // ====================================================

        Route::get(
            '/pickup-offers',
            [RiderPickupOfferController::class, 'index']
        )->name('pickup-offers.index');

        Route::post(
            '/pickup-offers/{pickupRequest}/accept',
            [RiderPickupOfferController::class, 'accept']
        )->name('pickup-offers.accept');

        Route::post(
            '/pickup-offers/{pickupRequest}/decline',
            [RiderPickupOfferController::class, 'decline']
        )->name('pickup-offers.decline');

        // ====================================================
        // RIDER DELIVERIES
        // ====================================================

        Route::get(
            '/orders',
            [RiderOrderController::class, 'index']
        )->name('orders.index');

        Route::get(
            '/orders/{shipment}',
            [RiderOrderController::class, 'show']
        )->name('orders.show');

        Route::put(
            '/orders/{shipment}/status',
            [RiderOrderController::class, 'updateStatus']
        )->name('orders.update-status');

        // ====================================================
        // RIDER PROOF OF DELIVERY
        // ====================================================

        Route::post(
            '/orders/{shipment}/proof',
            [RiderOrderController::class, 'uploadProof']
        )->name('orders.proof');

        // ====================================================
        // SELLER PICKUP WORKFLOW
        // ====================================================
        //
        // The picked-up endpoint is retained for
        // compatibility, but the revised controller
        // rejects manual pickup confirmation.
        //
        // Actual seller pickup is completed using
        // the authenticated QR scanner.
        //
        // ====================================================

        Route::post(
            '/pickups/{shipment}/picked-up',
            [RiderOrderController::class, 'markSellerPickupInTransit']
        )->name('pickups.picked-up');

        Route::post(
            '/pickups/{shipment}/deliver-to-sorting-center',
            [RiderOrderController::class, 'deliverSellerPickupToSortingCenter']
        )->name('pickups.deliver-to-sorting-center');

        // ====================================================
        // SELLER / HUB QR SCANNER
        // ====================================================

        Route::post(
            '/scan',
            [RiderScannerController::class, 'scan']
        )->name('scan');

        // ====================================================
        // RIDER COD COLLECTION
        // ====================================================
        //
        // IMPORTANT:
        //
        // These endpoints MUST stay inside the
        // protected Rider middleware group.
        //
        // GET: Retrieve COD payment details
        // POST: Record physically collected cash
        //
        // The controller validates:
        // - Authenticated rider
        // - Final-mile assignment
        // - Shipment status
        // - Hub QR confirmation
        // - Exact shipment amount
        // - Existing payments / duplicates
        //
        // ====================================================

        Route::get(
            '/shipments/{shipment}/cod',
            [RiderCodCollectionController::class, 'show']
        )->name('shipments.cod.show');

        Route::post(
            '/shipments/{shipment}/collect',
            [RiderCodCollectionController::class, 'collect']
        )->name('shipments.cod.collect');

        // ====================================================
        // RIDER EARNINGS
        // ====================================================

        Route::get(
            '/earnings',
            [RiderEarningsController::class, 'index']
        )->name('earnings');

        // ====================================================
        // RIDER NOTIFICATIONS
        // ====================================================

        Route::get(
            '/notifications',
            [RiderNotificationController::class, 'index']
        )->name('notifications.index');

        Route::post(
            '/notifications/read-all',
            [RiderNotificationController::class, 'markAllAsRead']
        )->name('notifications.read-all');

        Route::post(
            '/notifications/{notification}/read',
            [RiderNotificationController::class, 'markAsRead']
        )->name('notifications.read');
    });

// ============================================================
// SELLER MOBILE API
// NOT YET IMPLEMENTED
// ============================================================

// Route::middleware([
//     'auth:sanctum',
//     'role:seller',
// ])
//     ->prefix('seller')
//     ->name('seller.api.')
//     ->group(function () {
//
//         Route::get(
//             '/dashboard',
//             [
//                 App\Http\Controllers\Api\Seller\DashboardController::class,
//                 'index',
//             ]
//         );
//
//         Route::apiResource(
//             'products',
//             App\Http\Controllers\Api\Seller\ProductController::class
//         );
//
//         Route::get(
//             '/orders',
//             [
//                 App\Http\Controllers\Api\Seller\OrderController::class,
//                 'index',
//             ]
//         );
//
//         Route::get(
//             '/orders/{order}',
//             [
//                 App\Http\Controllers\Api\Seller\OrderController::class,
//                 'show',
//             ]
//         );
//
//         Route::put(
//             '/orders/{order}/status',
//             [
//                 App\Http\Controllers\Api\Seller\OrderController::class,
//                 'updateStatus',
//             ]
//         );
//
//         Route::get(
//             '/reports',
//             [
//                 App\Http\Controllers\Api\Seller\ReportController::class,
//                 'index',
//             ]
//         );
//     });

// ============================================================
// LOGISTICS MOBILE API
// ============================================================

Route::middleware([
    'auth:sanctum',
    'logistic_owner',
])
    ->prefix('logistic')
    ->name('logistic.api.')
    ->group(function () {

        // ====================================================
        // LOGISTICS DASHBOARD
        // ====================================================

        Route::get(
            '/dashboard',
            [LogisticDashboardController::class, 'index']
        )->name('dashboard');

        // ====================================================
        // LOGISTICS SHIPMENTS
        // ====================================================

        Route::get(
            '/shipments',
            [LogisticShipmentController::class, 'index']
        )->name('shipments.index');

        Route::get(
            '/shipments/{shipment}',
            [LogisticShipmentController::class, 'show']
        )->name('shipments.show');

        // ====================================================
        // RECEIVE SHIPMENT
        // ====================================================

        Route::post(
            '/shipments/{shipment}/receive',
            [LogisticShipmentController::class, 'receive']
        )->name('shipments.receive');

        // ====================================================
        // SCAN SHIPMENT
        // ====================================================

        Route::post(
            '/shipments/{shipment}/scan',
            [LogisticShipmentController::class, 'scan']
        )->name('shipments.scan');

        // ====================================================
        // SORT SHIPMENT
        // ====================================================

        Route::post(
            '/shipments/{shipment}/sort',
            [LogisticShipmentController::class, 'sort']
        )->name('shipments.sort');

        // ====================================================
        // STAGE SHIPMENT
        // ====================================================

        Route::post(
            '/shipments/{shipment}/stage',
            [LogisticShipmentController::class, 'stage']
        )->name('shipments.stage');

        // ====================================================
        // ASSIGN FINAL-MILE RIDER
        // ====================================================

        Route::post(
            '/shipments/{shipment}/assign',
            [LogisticShipmentController::class, 'assignRider']
        )->name('shipments.assign');
    });

// ============================================================
// ADMIN MOBILE API
// NOT YET IMPLEMENTED
// ============================================================

// Route::middleware([
//     'auth:sanctum',
//     'role:admin',
// ])
//     ->prefix('admin')
//     ->name('admin.api.')
//     ->group(function () {
//
//         Route::get(
//             '/dashboard',
//             [
//                 App\Http\Controllers\Api\Admin\DashboardController::class,
//                 'index',
//             ]
//         );
//
//         Route::get(
//             '/users',
//             [
//                 App\Http\Controllers\Api\Admin\UserController::class,
//                 'index',
//             ]
//         );
//
//         Route::get(
//             '/users/{user}',
//             [
//                 App\Http\Controllers\Api\Admin\UserController::class,
//                 'show',
//             ]
//         );
//
//         Route::put(
//             '/users/{user}/status',
//             [
//                 App\Http\Controllers\Api\Admin\UserController::class,
//                 'updateStatus',
//             ]
//         );
//
//         Route::get(
//             '/products',
//             [
//                 App\Http\Controllers\Api\Admin\ProductController::class,
//                 'index',
//             ]
//         );
//
//         Route::get(
//             '/orders',
//             [
//                 App\Http\Controllers\Api\Admin\OrderController::class,
//                 'index',
//             ]
//         );
//
//         Route::get(
//             '/logistics',
//             [
//                 App\Http\Controllers\Api\Admin\LogisticController::class,
//                 'index',
//             ]
//         );
//
//         Route::get(
//             '/reports',
//             [
//                 App\Http\Controllers\Api\Admin\ReportController::class,
//                 'index',
//             ]
//         );
//     });

// ============================================================
// FREE CRON ROUTES
// KEEP EXISTING SCHEDULED TASK ROUTES INTACT
// ============================================================

require __DIR__ . '/free_cron_routes.php';
