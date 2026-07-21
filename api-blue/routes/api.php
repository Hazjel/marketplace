<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\InternalController;
use App\Http\Controllers\LogisticsController;
use App\Http\Controllers\MidtransController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\ProductViewController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\StoreBalanceController;
use App\Http\Controllers\StoreBalanceHistoryController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\WithdrawalController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

// Health Check
Route::get('health', HealthController::class);

// Internal — cuma diakses service internal (recommendation-service dkk),
// bukan lewat auth token user
Route::middleware('internal')->get('internal/interactions', [InternalController::class, 'interactions']);

// Public Routes that must take precedence
Route::get('store/locations', [StoreController::class, 'getLocations']);

// Public routes (no authentication required)
Route::get('product-category', [ProductCategoryController::class, 'index']);
Route::get('product-category/all/paginated', [
    ProductCategoryController::class,
    'getAllPaginated',
]);
Route::get('product-category/slug/{slug}', [
    ProductCategoryController::class,
    'showBySlug',
]);
Route::get('product-category/{id}', [ProductCategoryController::class, 'show']);

Route::get('product', [ProductController::class, 'index']);
Route::get('product/all/paginated', [
    ProductController::class,
    'getAllPaginated',
]);
Route::get('product/slug/{slug}', [ProductController::class, 'showBySlug']);
Route::get('product/{id}', [ProductController::class, 'show']);
Route::middleware('throttle:60,1')->post('product/{id}/view', [ProductViewController::class, 'store']);

Route::get('store', [StoreController::class, 'index']);
Route::get('store/username/{store}', [
    StoreController::class,
    'showByUsername',
]);
Route::get('store/username/{username}/categories', [
    StoreController::class,
    'getCategories',
]);
Route::get('store/username/{username}/reviews', [
    StoreController::class,
    'getReviews',
]);
Route::get('store/{id}', [StoreController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::put('profile', [AuthController::class, 'updateProfile']);
    Route::put('profile/settings', [AuthController::class, 'updateSettings']);
    Route::post('logout', [AuthController::class, 'logout']);

    // Register Store (Upgrade Role)
    Route::post('register-store', [
        StoreController::class,
        'registerStore',
    ])->middleware('verified');

    // User routes - custom routes BEFORE resource
    Route::get('user/all/paginated', [
        UserController::class,
        'getAllPaginated',
    ]);
    Route::apiResource('user', UserController::class);

    // Store routes - custom routes BEFORE resource
    // Route::get('store/all/paginated', [StoreController::class, 'getAllPaginated']); // Commented out to potentially avoid conflict or keep inside auth if intended for admin
    Route::get('store/all/paginated', [
        StoreController::class,
        'getAllPaginated',
    ]);
    Route::post('store/{id}/verified', [
        StoreController::class,
        'updateVerifiedStatus',
    ]);
    // Route::get('store/username/{store}', [StoreController::class, 'showByUsername']); // Moved to public
    Route::get('my-store', [StoreController::class, 'showByUser']);
    Route::post('store/{id}/follow', [StoreController::class, 'followStore']);
    Route::post('store/{id}/unfollow', [
        StoreController::class,
        'unfollowStore',
    ]);
    Route::get('store/{id}/follow-status', [
        StoreController::class,
        'checkFollowStatus',
    ]);
    Route::apiResource('store', StoreController::class)->except([
        'index',
        'show',
    ]);

    // Store Balance routes - custom routes BEFORE resource
    Route::get('store-balance/all/paginated', [
        StoreBalanceController::class,
        'getAllPaginated',
    ]);
    Route::get('my-store-balance', [
        StoreBalanceController::class,
        'showByStore',
    ]);
    Route::apiResource('store-balance', StoreBalanceController::class)->except([
        'store',
        'update',
        'delete',
    ]);

    // Store Balance History routes - custom routes BEFORE resource
    Route::get('store-balance-history/all/paginated', [
        StoreBalanceHistoryController::class,
        'getAllPaginated',
    ]);
    Route::apiResource(
        'store-balance-history',
        StoreBalanceHistoryController::class,
    )->except(['store', 'update', 'delete']);

    // Withdrawal routes - custom routes BEFORE resource
    Route::get('withdrawal/all/paginated', [
        WithdrawalController::class,
        'getAllPaginated',
    ]);
    Route::post('withdrawal/{id}/approve', [
        WithdrawalController::class,
        'approve',
    ]);
    Route::post('withdrawal/{id}/reject', [
        WithdrawalController::class,
        'reject',
    ]);
    Route::middleware('throttle:5,1')->post('withdrawal', [WithdrawalController::class, 'store']);
    Route::apiResource('withdrawal', WithdrawalController::class)->except(
        'store',
        'update',
        'delete',
    );

    // Buyer routes - custom routes BEFORE resource
    Route::get('buyer/all/paginated', [
        BuyerController::class,
        'getAllPaginated',
    ]);
    Route::apiResource('buyer', BuyerController::class);

    // Address routes
    Route::apiResource(
        'address',
        AddressController::class,
    );

    // Product Category routes - custom routes BEFORE resource
    // Route::get('product-category/all/paginated', [ProductCategoryController::class, 'getAllPaginated']); // Moved to public
    // Route::get('product-category/slug/{slug}', [ProductCategoryController::class, 'showBySlug']); // Moved to public
    Route::apiResource(
        'product-category',
        ProductCategoryController::class,
    )->except(['index', 'show']);

    // Product routes - custom routes BEFORE resource
    // Route::get('product/all/paginated', [ProductController::class, 'getAllPaginated']); // Moved to public
    // Route::get('product/slug/{slug}', [ProductController::class, 'showBySlug']); // Moved to public
    Route::apiResource('product', ProductController::class)->except([
        'index',
        'show',
    ]);

    // Dashboard summary routes
    Route::get('seller/dashboard/summary', [DashboardController::class, 'sellerSummary']);
    Route::get('buyer/dashboard/summary', [DashboardController::class, 'buyerSummary']);

    // Transaction routes - custom routes BEFORE resource
    Route::get('transaction/chart-data', [
        TransactionController::class,
        'getChartData',
    ]);
    Route::get('transaction/all/paginated', [
        TransactionController::class,
        'getAllPaginated',
    ]);
    Route::get('transaction/code/{code}', [
        TransactionController::class,
        'showByCode',
    ]);
    Route::post('transaction/{id}/complete', [
        TransactionController::class,
        'complete',
    ]);
    Route::post('transaction/{id}/check-status', [
        TransactionController::class,
        'checkPaymentStatus',
    ]);
    // Route::middleware(['throttle:10,1', 'verified'])->post('transaction', [TransactionController::class, 'store']);
    Route::middleware(['throttle:10,1', 'idempotent'])->post('transaction', [
        TransactionController::class,
        'store',
    ]);
    Route::apiResource('transaction', TransactionController::class)->except([
        'store',
    ]);

    // Product Review
    Route::get('product-review/all/paginated', [
        ProductReviewController::class,
        'getAllPaginated',
    ]);
    Route::post('product-review', [ProductReviewController::class, 'store']);

    // Wishlist
    Route::get('wishlist', [
        WishlistController::class,
        'index',
    ]);
    Route::post('wishlist', [
        WishlistController::class,
        'store',
    ]);

    // Cart Routes
    Route::get('cart', [CartController::class, 'index']);
    Route::post('cart', [CartController::class, 'store']);
    Route::put('cart/{productId}', [CartController::class, 'update']);
    Route::delete('cart/clear', [CartController::class, 'clear']);
    Route::delete('cart/{productId}', [CartController::class, 'destroy']);
    Route::post('cart/sync', [CartController::class, 'sync']);
    Route::post('cart/validate-stock', [CartController::class, 'validateStock']);
    // Chat Routes
    Route::get('chat/contacts', [
        ChatController::class,
        'getContacts',
    ]);
    Route::get('chat/user/{id}', [
        ChatController::class,
        'getUserInfo',
    ]);
    Route::get('chat/{user}', [
        ChatController::class,
        'getMessages',
    ]);
    Route::post('chat/send', [
        ChatController::class,
        'sendMessage',
    ]);
});

// Auth routes
Route::middleware('throttle:6,1')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Verification Routes
Route::get('/email/verify/{id}/{hash}', [
    VerificationController::class,
    'verify',
])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::post('/email/resend', [
    VerificationController::class,
    'resend',
])
    ->middleware(['auth:sanctum', 'throttle:6,1'])
    ->name('verification.send');
Route::middleware(['web'])->group(function () {
    Route::get('auth/google/redirect', [
        GoogleAuthController::class,
        'redirect',
    ]);
    Route::get('auth/google/callback', [
        GoogleAuthController::class,
        'callback',
    ]);
});

// Password Reset Routes (Public, throttled)
Route::middleware('throttle:5,1')->group(function () {
    Route::post('password/forgot', [
        ForgotPasswordController::class,
        'sendResetLink',
    ]);
    Route::post('password/reset', [
        ForgotPasswordController::class,
        'resetPassword',
    ]);
});

// Shipment proxy — hides Komerce API key from frontend
Route::middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
    Route::get('/shipment/destination', [ShipmentController::class, 'destination']);
    Route::get('/shipment/calculate', [ShipmentController::class, 'calculate']);
    Route::get('/shipment/geocode', [ShipmentController::class, 'geocode']);
    Route::get('/shipment/reverse-geocode', [ShipmentController::class, 'reverseGeocode']);
});

// Midtrans callback
Route::post('/midtrans-callback', [MidtransController::class, 'callback'])->middleware('throttle:60,1');

// Logistics Webhook (Simulation - RajaOngkir/Komerce)
Route::post('/logistics/webhook', [
    LogisticsController::class,
    'webhook',
])->middleware('throttle:60,1');
