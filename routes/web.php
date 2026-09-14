<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Seller\DashboardController as SellerDashboardController;
use App\Http\Controllers\Rider\DashboardController as RiderDashboardController;
use App\Http\Controllers\Buyer\AddressController;
use App\Http\Controllers\Buyer\ReviewController;
use App\Http\Controllers\Buyer\SupportController;
use App\Http\Controllers\Buyer\NotificationController;
use App\Http\Controllers\Api\AddressApiController;
use App\Http\Controllers\Shared\MessageController as SharedMessageController;
use App\Http\Controllers\Admin\RegistrationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ComplianceController;
use App\Http\Controllers\Admin\ComplaintController;
use App\Http\Controllers\Admin\CommissionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\MessageController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\LogisticController as AdminLogisticController;
use App\Http\Controllers\Admin\ShipmentController;
use App\Http\Controllers\LogisticController;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/about', [HomeController::class, 'about'])->name('about');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::prefix('cart')->name('cart.')->middleware('customer')->group(function () {
        Route::get('/', [CartController::class, 'index'])->name('index');
        Route::post('/{product}', [CartController::class, 'store'])->name('store');
        Route::put('/{cartItem}', [CartController::class, 'update'])->name('update');
        Route::delete('/{cartItem}', [CartController::class, 'destroy'])->name('destroy');
    });

    Route::post('/buy-now/{product}', [CartController::class, 'buyNow'])->name('buyNow')->middleware('customer');

    Route::prefix('checkout')->name('checkout.')->middleware('customer')->group(function () {
        Route::get('/', [CheckoutController::class, 'index'])->name('index');
        Route::post('/', [CheckoutController::class, 'store'])->name('store');
    });

    Route::prefix('orders')->name('orders.')->middleware('customer')->group(function () {
        Route::get('/', [OrderController::class, 'index'])->name('index');
        Route::get('/{order}', [OrderController::class, 'show'])->name('show');
        Route::post('/{order}/return', [OrderController::class, 'requestReturn'])->name('return');
        Route::post('/{order}/reschedule', [OrderController::class, 'requestReschedule'])->name('reschedule');
        Route::post('/{order}/cancel', [OrderController::class, 'requestCancellation'])->name('cancel');
        Route::post('/{order}/confirm-received', [OrderController::class, 'confirmReceived'])->name('confirm-received');
    });

    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/sellers', [DashboardController::class, 'sellers'])->name('sellers');
        Route::get('/products', [DashboardController::class, 'products'])->name('products');
        Route::get('/riders', [DashboardController::class, 'riders'])->name('riders');

        Route::prefix('registrations')->name('registrations.')->group(function () {
            Route::get('/', [RegistrationController::class, 'index'])->name('index');
            Route::get('/{user}', [RegistrationController::class, 'show'])->name('show');
            Route::post('/{user}/approve', [RegistrationController::class, 'approve'])->name('approve');
            Route::post('/{user}/reject', [RegistrationController::class, 'reject'])->name('reject');
        });

        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::get('/{user}', [UserController::class, 'show'])->name('show');
            Route::post('/{user}/activate', [UserController::class, 'activate'])->name('activate');
            Route::post('/{user}/suspend', [UserController::class, 'suspend'])->name('suspend');
            Route::post('/{user}/deactivate', [UserController::class, 'deactivate'])->name('deactivate');
        });

        Route::prefix('compliance')->name('compliance.')->group(function () {
            Route::get('/', [ComplianceController::class, 'index'])->name('index');
            Route::get('/{product}', [ComplianceController::class, 'show'])->name('show');
            Route::post('/{product}/approve', [ComplianceController::class, 'approve'])->name('approve');
            Route::post('/{product}/flag', [ComplianceController::class, 'flag'])->name('flag');
            Route::post('/{product}/warn', [ComplianceController::class, 'issueWarning'])->name('warn');
            Route::post('/{product}/suspend-seller', [ComplianceController::class, 'suspendSeller'])->name('suspendSeller');
            Route::post('/{product}/rescan', [ComplianceController::class, 'rescan'])->name('rescan');
            Route::post('/{product}/blacklist-image', [ComplianceController::class, 'blacklistImage'])->name('blacklistImage');
        });

        Route::prefix('complaints')->name('complaints.')->group(function () {
            Route::get('/', [ComplaintController::class, 'index'])->name('index');
            Route::get('/{ticket}', [ComplaintController::class, 'show'])->name('show');
            Route::post('/{ticket}/respond', [ComplaintController::class, 'respond'])->name('respond');
            Route::post('/{ticket}/message', [ComplaintController::class, 'messageParty'])->name('message');
            Route::post('/{ticket}/resolve', [ComplaintController::class, 'resolve'])->name('resolve');
        });

        Route::prefix('logistics')->name('logistics.')->group(function () {
            Route::get('/', [AdminLogisticController::class, 'index'])->name('index');
            Route::get('/{logistic}', [AdminLogisticController::class, 'show'])->name('show');
            Route::post('/{logistic}/approve', [AdminLogisticController::class, 'approve'])->name('approve');
            Route::post('/{logistic}/reject', [AdminLogisticController::class, 'reject'])->name('reject');
            Route::post('/{logistic}/suspend', [AdminLogisticController::class, 'suspend'])->name('suspend');
            Route::post('/{logistic}/activate', [AdminLogisticController::class, 'activate'])->name('activate');
            Route::post('/riders/{rider}/approve', [AdminLogisticController::class, 'approveRider'])->name('riders.approve');
            Route::post('/riders/{rider}/reject', [AdminLogisticController::class, 'rejectRider'])->name('riders.reject');
        });

        Route::prefix('shipments')->name('shipments.')->group(function () {
            Route::get('/', [ShipmentController::class, 'index'])->name('index');
            Route::get('/{shipment}', [ShipmentController::class, 'show'])->name('show');
            Route::post('/{shipment}/status', [ShipmentController::class, 'updateStatus'])->name('status');
            Route::post('/{shipment}/assign-rider', [ShipmentController::class, 'assignRider'])->name('assignRider');
        });

        Route::prefix('commissions')->name('commissions.')->group(function () {
            Route::get('/', [CommissionController::class, 'index'])->name('index');
            Route::post('/{commission}/pay', [CommissionController::class, 'markPaid'])->name('pay');
        });

        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/sales', [ReportController::class, 'sales'])->name('sales');
            Route::get('/commission', [ReportController::class, 'commission'])->name('commission');
            Route::get('/sales/export', [ReportController::class, 'exportSales'])->name('sales.export');
            Route::get('/commission/export', [ReportController::class, 'exportCommission'])->name('commission.export');
            Route::get('/logistics', [ShipmentController::class, 'reports'])->name('logistics');
        });

        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/', [SettingsController::class, 'index'])->name('index');
            Route::get('/announcements/create', [SettingsController::class, 'createAnnouncement'])->name('announcements.create');
            Route::post('/announcements', [SettingsController::class, 'storeAnnouncement'])->name('announcements.store');
            Route::post('/announcements/{announcement}/toggle', [SettingsController::class, 'toggleAnnouncement'])->name('announcements.toggle');
            Route::delete('/announcements/{announcement}', [SettingsController::class, 'deleteAnnouncement'])->name('announcements.delete');
            Route::post('/policies', [SettingsController::class, 'updatePolicies'])->name('policies.update');
        });

        Route::prefix('messages')->name('messages.')->group(function () {
            Route::get('/', [MessageController::class, 'index'])->name('index');
            Route::get('/{user}', [MessageController::class, 'show'])->name('show');
            Route::post('/{user}/reply', [MessageController::class, 'reply'])->name('reply');
        });

        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [AdminNotificationController::class, 'index'])->name('index');
            Route::post('/{notification}/read', [AdminNotificationController::class, 'markRead'])->name('read');
            Route::post('/read-all', [AdminNotificationController::class, 'markAllRead'])->name('readAll');
        });
    });

    Route::prefix('seller')->name('seller.')->middleware('seller')->group(function () {
        Route::get('/dashboard', [SellerDashboardController::class, 'index'])->name('dashboard');
        Route::get('/products', [SellerDashboardController::class, 'products'])->name('products');
        Route::get('/products/create', [SellerDashboardController::class, 'create'])->name('products.create');
        Route::post('/products', [SellerDashboardController::class, 'store'])->name('products.store');
        Route::get('/products/{product}/edit', [SellerDashboardController::class, 'edit'])->name('products.edit');
        Route::put('/products/{product}', [SellerDashboardController::class, 'update'])->name('products.update');
        Route::post('/products/{product}/resubmit', [SellerDashboardController::class, 'resubmit'])->name('products.resubmit');
        Route::delete('/products/{product}', [SellerDashboardController::class, 'destroy'])->name('products.destroy');
        Route::get('/orders', [SellerDashboardController::class, 'orders'])->name('orders');
        Route::get('/orders/{order}', [SellerDashboardController::class, 'showOrder'])->name('orders.show');
        Route::post('/orders/{order}/status', [SellerDashboardController::class, 'updateOrderStatus'])->name('orders.updateStatus');
        Route::post('/orders/{order}/approve-return', [SellerDashboardController::class, 'approveReturn'])->name('orders.approveReturn');
        Route::post('/orders/{order}/reject-return', [SellerDashboardController::class, 'rejectReturn'])->name('orders.rejectReturn');
        Route::post('/orders/{order}/approve-reschedule', [SellerDashboardController::class, 'approveReschedule'])->name('orders.approveReschedule');
        Route::post('/orders/{order}/reject-reschedule', [SellerDashboardController::class, 'rejectReschedule'])->name('orders.rejectReschedule');
        Route::post('/orders/{order}/ready', [SellerDashboardController::class, 'markReadyForPickup'])->name('orders.ready');
        Route::get('/notifications', [SellerDashboardController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/read-all', [SellerDashboardController::class, 'markAllNotificationsRead'])->name('notifications.readAll');
        Route::post('/notifications/{notification}/read', [SellerDashboardController::class, 'markNotificationRead'])->name('notifications.read');
        Route::get('/reports', [SellerDashboardController::class, 'reports'])->name('reports');
        Route::get('/messages', [SharedMessageController::class, 'index'])->name('messages.index');
        Route::get('/messages/{user}', [SharedMessageController::class, 'show'])->name('messages.show');
        Route::post('/messages/{user}', [SharedMessageController::class, 'reply'])->name('messages.reply');
        Route::post('/account/appeal', [SellerDashboardController::class, 'submitAppeal'])->name('account.appeal');
        Route::get('/account', [SellerDashboardController::class, 'account'])->name('account');
        Route::put('/account', [SellerDashboardController::class, 'updateAccount'])->name('account.update');
    });

    Route::prefix('rider')->name('rider.')->middleware('rider')->group(function () {
        Route::get('/dashboard', [RiderDashboardController::class, 'index'])->name('dashboard');
        Route::get('/orders', [RiderDashboardController::class, 'orders'])->name('orders');
        Route::get('/orders/{order}', [RiderDashboardController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/status', [RiderDashboardController::class, 'updateStatus'])->name('orders.updateStatus');
        Route::post('/orders/{order}/proof', [RiderDashboardController::class, 'uploadProof'])->name('orders.proof');
        Route::post('/shipments/{order}/scan', [App\Http\Controllers\ShipmentQrController::class, 'scanByOrder'])->name('shipments.scan');
        Route::post('/orders/{order}/confirm-cod', [RiderDashboardController::class, 'confirmCOD'])->name('orders.confirmCOD');
        Route::post('/orders/{order}/collect', [RiderDashboardController::class, 'collectPayment'])->name('orders.collect');
        Route::get('/cash-report', [RiderDashboardController::class, 'reportCash'])->name('cash.report');
        Route::post('/location', [RiderDashboardController::class, 'updateLocation'])->name('location.update');
        Route::get('/location', [RiderDashboardController::class, 'track'])->name('track');

        Route::get('/deliveries', [RiderDashboardController::class, 'deliveries'])->name('deliveries');
        Route::post('/deliveries/{order}/accept', [RiderDashboardController::class, 'accept'])->name('deliveries.accept');
        Route::get('/pickups', [RiderDashboardController::class, 'pickups'])->name('pickups');
        Route::post('/pickups/{order}/confirm', [RiderDashboardController::class, 'confirmPickup'])->name('pickups.confirm');
        Route::post('/pickups/{order}/deliver-to-sorting-center', [RiderDashboardController::class, 'deliverToSortingCenter'])->name('pickups.deliver-to-sorting-center');
        Route::post('/pickups/{order}/pickup-from-sorting-center', [RiderDashboardController::class, 'pickupFromSortingCenter'])->name('pickups.pickup-from-sorting-center');
        Route::get('/addresses', [RiderDashboardController::class, 'addresses'])->name('addresses');
        Route::get('/history', [RiderDashboardController::class, 'history'])->name('history');
        Route::get('/profit', [RiderDashboardController::class, 'profit'])->name('profit');
        Route::get('/messages', [SharedMessageController::class, 'index'])->name('messages.index');
        Route::get('/messages/{user}', [SharedMessageController::class, 'show'])->name('messages.show');
        Route::post('/messages/{user}', [SharedMessageController::class, 'reply'])->name('messages.reply');
        Route::get('/account', [RiderDashboardController::class, 'account'])->name('account');
        Route::put('/account', [RiderDashboardController::class, 'updateAccount'])->name('account.update');
        Route::get('/notifications', [RiderDashboardController::class, 'notifications'])->name('notifications');
    });

    Route::prefix('logistic')->name('logistic.')->middleware('logistic_owner')->group(function () {
        Route::get('/', [LogisticController::class, 'home'])->name('home');
        Route::get('/dashboard', [LogisticController::class, 'index'])->name('dashboard');
        Route::get('/riders', [LogisticController::class, 'riders'])->name('riders');
        Route::get('/riders/{rider}', [LogisticController::class, 'showRider'])->name('riders.show');
        Route::post('/riders/{rider}/approve', [LogisticController::class, 'approveRiderApplication'])->name('riders.approve');
        Route::post('/riders/{rider}/reject', [LogisticController::class, 'rejectRiderApplication'])->name('riders.reject');
        Route::post('/riders/{rider}/submit', [LogisticController::class, 'submitRiderToAdmin'])->name('riders.submit');
        Route::post('/riders/{rider}/location', [LogisticController::class, 'updateRiderLocation'])->name('riders.location');
        Route::get('/applications', [LogisticController::class, 'pendingApplications'])->name('applications');
        Route::get('/riders/register', [LogisticController::class, 'showRiderRegistration'])->name('riders.register');
        Route::post('/riders/register', [LogisticController::class, 'storeRider'])->name('riders.register.store');
        Route::get('/shipments', [LogisticController::class, 'shipments'])->name('shipments');
        Route::get('/sorting-area', [LogisticController::class, 'sortingArea'])->name('sorting-area');
        Route::post('/shipments/{shipment}/receive', [LogisticController::class, 'receiveShipment'])->name('shipments.receive');
        Route::post('/shipments/{shipment}/scan', [LogisticController::class, 'scanShipment'])->name('shipments.scan');
        Route::post('/shipments/{shipment}/scan-at-hub', [App\Http\Controllers\ShipmentQrController::class, 'scanAtHub'])->name('shipments.scan-at-hub');
        Route::post('/shipments/{shipment}/sort', [LogisticController::class, 'sortShipment'])->name('shipments.sort');
        Route::post('/shipments/{shipment}/stage', [LogisticController::class, 'stageShipment'])->name('shipments.stage');
        Route::post('/shipments/{shipment}/assign-rider', [LogisticController::class, 'assignRider'])->name('shipments.assign-rider');
        Route::post('/shipments/auto-assign', [LogisticController::class, 'autoAssignByZone'])->name('shipments.auto-assign');
        Route::post('/shipments/{shipment}/auto-scan', [LogisticController::class, 'autoScanZone'])->name('shipments.auto-scan');
        Route::get('/available-riders', [LogisticController::class, 'availableRiders'])->name('available-riders');
        Route::get('/shipments/create', [LogisticController::class, 'createShipment'])->name('shipments.create');
        Route::post('/shipments', [LogisticController::class, 'storeShipment'])->name('shipments.store');
        Route::get('/shipments/{shipment}', [LogisticController::class, 'showShipment'])->name('shipments.show');
        Route::post('/shipments/{shipment}/status', [LogisticController::class, 'updateShipmentStatus'])->name('shipments.status');
        Route::get('/shipments/{shipment}/track', [LogisticController::class, 'trackShipment'])->name('shipments.track');
        Route::get('/shipments/{shipment}/chat', [LogisticController::class, 'shipmentChat'])->name('shipments.chat');
        Route::post('/shipments/{shipment}/chat', [LogisticController::class, 'shipmentChatStore'])->name('shipments.chat.store');
        Route::get('/reports', [LogisticController::class, 'reports'])->name('reports');
        Route::get('/account', [LogisticController::class, 'account'])->name('account');
        Route::put('/account', [LogisticController::class, 'updateAccount'])->name('account.update');
        Route::get('/hubs', [LogisticController::class, 'hubs'])->name('hubs');
        Route::get('/hubs/create', [LogisticController::class, 'createHub'])->name('hubs.create');
        Route::post('/hubs', [LogisticController::class, 'storeHub'])->name('hubs.store');
        Route::get('/hubs/{hub}', [LogisticController::class, 'showHub'])->name('hubs.show');
        Route::put('/hubs/{hub}', [LogisticController::class, 'updateHub'])->name('hubs.update');
        Route::delete('/hubs/{hub}', [LogisticController::class, 'destroyHub'])->name('hubs.destroy');
        Route::get('/hubs/{hub}/riders', [LogisticController::class, 'hubRiders'])->name('hubs.riders');
        Route::post('/hubs/{hub}/riders/assign', [LogisticController::class, 'assignRiderToHub'])->name('hubs.riders.assign');
        Route::post('/hubs/{hub}/riders/unassign', [LogisticController::class, 'unassignRiderFromHub'])->name('hubs.riders.unassign');
        Route::get('/notifications', [LogisticController::class, 'notifications'])->name('notifications');

        Route::get('/messages', [SharedMessageController::class, 'index'])->name('messages.index');
        Route::get('/messages/{user}', [SharedMessageController::class, 'show'])->name('messages.show');
        Route::post('/messages/{user}', [SharedMessageController::class, 'reply'])->name('messages.reply');
    });

    Route::prefix('buyer')->name('buyer.')->middleware('customer')->group(function () {
        Route::get('/addresses', [AddressController::class, 'index'])->name('addresses.index');
        Route::get('/addresses/create', [AddressController::class, 'create'])->name('addresses.create');
        Route::post('/addresses', [AddressController::class, 'store'])->name('addresses.store');
        Route::post('/addresses/use-current', [AddressController::class, 'useCurrentAddress'])->name('addresses.useCurrent');
        Route::get('/addresses/{address}/edit', [AddressController::class, 'edit'])->name('addresses.edit');
        Route::put('/addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
        Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');

        Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
        Route::get('/reviews/{order}/create', [ReviewController::class, 'create'])->name('reviews.create');
        Route::post('/reviews/{order}', [ReviewController::class, 'store'])->name('reviews.store');

        Route::get('/support', [SupportController::class, 'index'])->name('support.index');
        Route::get('/support/create', [SupportController::class, 'create'])->name('support.create');
        Route::post('/support', [SupportController::class, 'store'])->name('support.store');
        Route::get('/support/{ticket}', [SupportController::class, 'show'])->name('support.show');

        Route::get('/report-product/{product}', [App\Http\Controllers\Buyer\ReportProductController::class, 'create'])->name('report-product.create');
        Route::post('/report-product/{product}', [App\Http\Controllers\Buyer\ReportProductController::class, 'store'])->name('report-product.store');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

        Route::get('/messages', [SharedMessageController::class, 'index'])->name('messages.index');
        Route::get('/messages/{user}', [SharedMessageController::class, 'show'])->name('messages.show');
        Route::post('/messages/{user}', [SharedMessageController::class, 'reply'])->name('messages.reply');

        Route::get('/help', function () {
            return view('buyer.help.index');
        })->name('help');
    });
});

    // Universal notification routes (work for all user types)
    Route::post('/notifications/{notification}/read', [\App\Http\Controllers\Buyer\NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [\App\Http\Controllers\Buyer\NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');

    Route::prefix('api/addresses')->name('api.addresses.')->group(function () {
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

Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
Route::post('/products/{product}/reviews', [ProductController::class, 'storeReview'])->name('products.reviews.store');
Route::get('/seller/{seller}', [SellerDashboardController::class, 'storefront'])->name('seller.storefront');

Route::get('/apply/rider', [App\Http\Controllers\Auth\RegisteredUserController::class, 'createRiderApplication'])->name('apply.rider');
Route::post('/apply/rider', [App\Http\Controllers\Auth\RegisteredUserController::class, 'storeRiderApplication'])->name('apply.rider.store');

Route::get('/auth/google', [SocialAuthController::class, 'redirectToGoogle'])->name('auth.google');
Route::get('/auth/google/callback', [SocialAuthController::class, 'handleGoogleCallback'])->name('auth.google.callback');
Route::get('/auth/facebook', [SocialAuthController::class, 'redirectToFacebook'])->name('auth.facebook');
Route::get('/auth/facebook/callback', [SocialAuthController::class, 'handleFacebookCallback'])->name('auth.facebook.callback');
Route::get('/social/register', [SocialAuthController::class, 'showRegistrationForm'])->name('social.register');
Route::post('/social/register', [SocialAuthController::class, 'storeRegistration'])->name('social.register.store');

Route::get('/shipments/{shipment}/qr', [App\Http\Controllers\ShipmentQrController::class, 'image'])->name('shipments.qr.image');
Route::get('/shipments/{shipment}/qr.png', [App\Http\Controllers\ShipmentQrController::class, 'png'])->name('shipments.qr.png');
Route::get('/shipments/{shipment}/label', [App\Http\Controllers\ShipmentQrController::class, 'label'])->name('shipments.qr.label');
Route::post('/shipments/{shipment}/scan', [App\Http\Controllers\ShipmentQrController::class, 'scan'])->name('shipments.qr.scan');

require __DIR__.'/auth.php';
