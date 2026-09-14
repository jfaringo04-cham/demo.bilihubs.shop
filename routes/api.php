<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ShipmentController;
use App\Http\Controllers\Api\AddressApiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public routes
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/social/google', [AuthController::class, 'googleLogin']);
Route::post('/auth/social/facebook', [AuthController::class, 'facebookLogin']);
Route::post('/auth/refresh', [AuthController::class, 'refresh']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

// Public product browsing
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/categories', [ProductController::class, 'categories']);
Route::get('/sizes', [ProductController::class, 'sizes']);

// Public address API (Philippine geographic data)
Route::prefix('addresses')->name('api.addresses.')->group(function () {
    Route::get('/regions', [AddressApiController::class, 'regions'])->name('regions');
    Route::get('/regions/{regionCode}', [AddressApiController::class, 'region'])->name('region');
    Route::get('/regions/{regionCode}/provinces', [AddressApiController::class, 'regionProvinces'])->name('regionProvinces');
    Route::get('/regions/{regionCode}/cities', [AddressApiController::class, 'regionCities'])->name('regionCities');
    Route::get('/provinces', [AddressApiController::class, 'provinces'])->name('provinces');
    Route::get('/provinces/{provinceCode}', [AddressApiController::class, 'province'])->name('province');
    Route::get('/provinces/{provinceCode}/municipalities', [AddressApiController::class, 'provinceMunicipalities'])->name('provinceMunicipalities');
    Route::get('/provinces/{provinceCode}/cities', [AddressApiController::class, 'provinceCities'])->name('provinceCities');
    Route::get('/cities', [AddressApiController::class, 'cities'])->name('cities');
    Route::get('/cities/{cityCode}', [AddressApiController::class, 'city'])->name('city');
    Route::get('/cities/{cityCode}/barangays', [AddressApiController::class, 'cityBarangays'])->name('cityBarangays');
    Route::get('/municipalities', [AddressApiController::class, 'municipalities'])->name('municipalities');
    Route::get('/municipalities/{municipalityCode}', [AddressApiController::class, 'municipality'])->name('municipality');
    Route::get('/municipalities/{municipalityCode}/barangays', [AddressApiController::class, 'municipalityBarangays'])->name('municipalityBarangays');
    Route::get('/barangays', [AddressApiController::class, 'barangays'])->name('barangays');
    Route::get('/barangays/{barangayCode}', [AddressApiController::class, 'barangay'])->name('barangay');
    Route::get('/districts', [AddressApiController::class, 'districts'])->name('districts');
    Route::get('/districts/{districtCode}', [AddressApiController::class, 'district'])->name('district');
    Route::get('/sub-municipalities', [AddressApiController::class, 'subMunicipalities'])->name('subMunicipalities');
    Route::get('/sub-municipalities/{subMunicipalityCode}', [AddressApiController::class, 'subMunicipality'])->name('subMunicipality');
});

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password', [AuthController::class, 'updatePassword']);
    Route::post('/auth/device', [AuthController::class, 'registerDevice']);

    // Addresses (user's saved addresses)
    Route::apiResource('addresses', AddressController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::post('/addresses/{address}/set-default', [AddressController::class, 'setDefault']);

    // Cart
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::put('/cart/{cartItem}', [CartController::class, 'update']);
    Route::delete('/cart/{cartItem}', [CartController::class, 'destroy']);
    Route::delete('/cart', [CartController::class, 'clear']);
    Route::post('/buy-now', [CartController::class, 'buyNow']);

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);
    Route::post('/orders/{order}/rate', [OrderController::class, 'rate']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Shipments/Tracking
    Route::get('/shipments/{shipment}/track', [ShipmentController::class, 'track']);
    Route::get('/orders/{order}/shipment', [ShipmentController::class, 'showForOrder']);
});

// Seller routes (TODO: Create controllers)
// Route::middleware(['auth:sanctum', 'role:seller'])->prefix('seller')->name('seller.')->group(function () {
//     Route::get('/dashboard', [App\Http\Controllers\Api\Seller\DashboardController::class, 'index']);
//     Route::apiResource('products', App\Http\Controllers\Api\Seller\ProductController::class);
//     Route::get('/orders', [App\Http\Controllers\Api\Seller\OrderController::class, 'index']);
//     Route::get('/orders/{order}', [App\Http\Controllers\Api\Seller\OrderController::class, 'show']);
//     Route::put('/orders/{order}/status', [App\Http\Controllers\Api\Seller\OrderController::class, 'updateStatus']);
//     Route::get('/reports', [App\Http\Controllers\Api\Seller\ReportController::class, 'index']);
// });

// Rider routes (TODO: Create controllers)
// Route::middleware(['auth:sanctum', 'role:rider'])->prefix('rider')->name('rider.')->group(function () {
//     Route::get('/dashboard', [App\Http\Controllers\Api\Rider\DashboardController::class, 'index']);
//     Route::get('/orders', [App\Http\Controllers\Api\Rider\OrderController::class, 'index']);
//     Route::get('/orders/{order}', [App\Http\Controllers\Api\Rider\OrderController::class, 'show']);
//     Route::put('/orders/{order}/status', [App\Http\Controllers\Api\Rider\OrderController::class, 'updateStatus']);
//     Route::post('/orders/{order}/proof', [App\Http\Controllers\Api\Rider\OrderController::class, 'uploadProof']);
//     Route::get('/earnings', [App\Http\Controllers\Api\Rider\EarningsController::class, 'index']);
//     Route::get('/notifications', [App\Http\Controllers\Api\Rider\NotificationController::class, 'index']);
// });

// Logistic routes (TODO: Create controllers)
// Route::middleware(['auth:sanctum', 'role:logistic_owner'])->prefix('logistic')->name('logistic.')->group(function () {
//     Route::get('/dashboard', [App\Http\Controllers\Api\Logistic\DashboardController::class, 'index']);
//     Route::get('/shipments', [App\Http\Controllers\Api\Logistic\ShipmentController::class, 'index']);
//     Route::get('/shipments/{shipment}', [App\Http\Controllers\Api\Logistic\ShipmentController::class, 'show']);
//     Route::post('/shipments/{shipment}/receive', [App\Http\Controllers\Api\Logistic\ShipmentController::class, 'receive']);
//     Route::post('/shipments/{shipment}/scan', [App\Http\Controllers\Api\Logistic\ShipmentController::class, 'scan']);
//     Route::post('/shipments/{shipment}/sort', [App\Http\Controllers\Api\Logistic\ShipmentController::class, 'sort']);
//     Route::post('/shipments/{shipment}/stage', [App\Http\Controllers\Api\Logistic\ShipmentController::class, 'stage']);
//     Route::post('/shipments/{shipment}/assign', [App\Http\Controllers\Api\Logistic\ShipmentController::class, 'assignRider']);
//     Route::get('/riders', [App\Http\Controllers\Api\Logistic\RiderController::class, 'index']);
//     Route::get('/hubs', [App\Http\Controllers\Api\Logistic\HubController::class, 'index']);
//     Route::post('/hubs', [App\Http\Controllers\Api\Logistic\HubController::class, 'store']);
//     Route::put('/hubs/{hub}', [App\Http\Controllers\Api\Logistic\HubController::class, 'update']);
//     Route::delete('/hubs/{hub}', [App\Http\Controllers\Api\Logistic\HubController::class, 'destroy']);
//     Route::get('/notifications', [App\Http\Controllers\Api\Logistic\NotificationController::class, 'index']);
// });

// Admin routes (TODO: Create controllers)
// Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
//     Route::get('/dashboard', [App\Http\Controllers\Api\Admin\DashboardController::class, 'index']);
//     Route::get('/users', [App\Http\Controllers\Api\Admin\UserController::class, 'index']);
//     Route::get('/users/{user}', [App\Http\Controllers\Api\Admin\UserController::class, 'show']);
//     Route::put('/users/{user}/status', [App\Http\Controllers\Api\Admin\UserController::class, 'updateStatus']);
//     Route::get('/products', [App\Http\Controllers\Api\Admin\ProductController::class, 'index']);
//     Route::get('/orders', [App\Http\Controllers\Api\Admin\OrderController::class, 'index']);
//     Route::get('/logistics', [App\Http\Controllers\Api\Admin\LogisticController::class, 'index']);
//     Route::get('/reports', [App\Http\Controllers\Api\Admin\ReportController::class, 'index']);
// });