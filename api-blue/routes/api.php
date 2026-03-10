<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\StoreBalanceController;
use App\Http\Controllers\StoreBalanceHistoryController;
use App\Http\Controllers\WithdrawalController;
use App\Http\Controllers\BuyerController;
use App\Http\Controllers\MidtransController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes(["middleware" => ["auth:sanctum"]]);

// Public Routes that must take precedence
Route::get("store/locations", [StoreController::class, "getLocations"]);

// Public routes (no authentication required)
Route::get("product-category", [ProductCategoryController::class, "index"]);
Route::get("product-category/all/paginated", [
    ProductCategoryController::class,
    "getAllPaginated",
]);
Route::get("product-category/slug/{slug}", [
    ProductCategoryController::class,
    "showBySlug",
]);
Route::get("product-category/{id}", [ProductCategoryController::class, "show"]);

Route::get("product", [ProductController::class, "index"]);
Route::get("product/all/paginated", [
    ProductController::class,
    "getAllPaginated",
]);
Route::get("product/slug/{slug}", [ProductController::class, "showBySlug"]);
Route::get("product/{id}", [ProductController::class, "show"]);

Route::get("store", [StoreController::class, "index"]);
Route::get("store/username/{store}", [
    StoreController::class,
    "showByUsername",
]);
Route::get("store/username/{username}/categories", [
    StoreController::class,
    "getCategories",
]);
Route::get("store/username/{username}/reviews", [
    StoreController::class,
    "getReviews",
]);
Route::get("store/{id}", [StoreController::class, "show"]);

Route::middleware("auth:sanctum")->group(function () {
    Route::get("me", [AuthController::class, "me"]);
    Route::put("profile", [AuthController::class, "updateProfile"]);
    Route::post("logout", [AuthController::class, "logout"]);

    // Register Store (Upgrade Role)
    Route::post("register-store", [
        StoreController::class,
        "registerStore",
    ])->middleware("verified");

    // User routes - custom routes BEFORE resource
    Route::get("user/all/paginated", [
        UserController::class,
        "getAllPaginated",
    ]);
    Route::apiResource("user", UserController::class);

    // Store routes - custom routes BEFORE resource
    // Route::get('store/all/paginated', [StoreController::class, 'getAllPaginated']); // Commented out to potentially avoid conflict or keep inside auth if intended for admin
    Route::get("store/all/paginated", [
        StoreController::class,
        "getAllPaginated",
    ]);
    Route::post("store/{id}/verified", [
        StoreController::class,
        "updateVerifiedStatus",
    ]);
    // Route::get('store/username/{store}', [StoreController::class, 'showByUsername']); // Moved to public
    Route::get("my-store", [StoreController::class, "showByUser"]);
    Route::post("store/{id}/follow", [StoreController::class, "followStore"]);
    Route::post("store/{id}/unfollow", [
        StoreController::class,
        "unfollowStore",
    ]);
    Route::get("store/{id}/follow-status", [
        StoreController::class,
        "checkFollowStatus",
    ]);
    Route::apiResource("store", StoreController::class)->except([
        "index",
        "show",
    ]);

    // Store Balance routes - custom routes BEFORE resource
    Route::get("store-balance/all/paginated", [
        StoreBalanceController::class,
        "getAllPaginated",
    ]);
    Route::get("my-store-balance", [
        StoreBalanceController::class,
        "showByStore",
    ]);
    Route::apiResource("store-balance", StoreBalanceController::class)->except([
        "store",
        "update",
        "delete",
    ]);

    // Store Balance History routes - custom routes BEFORE resource
    Route::get("store-balance-history/all/paginated", [
        StoreBalanceHistoryController::class,
        "getAllPaginated",
    ]);
    Route::apiResource(
        "store-balance-history",
        StoreBalanceHistoryController::class,
    )->except(["store", "update", "delete"]);

    // Withdrawal routes - custom routes BEFORE resource
    Route::get("withdrawal/all/paginated", [
        WithdrawalController::class,
        "getAllPaginated",
    ]);
    Route::post("withdrawal/{id}/approve", [
        WithdrawalController::class,
        "approve",
    ]);
    Route::apiResource("withdrawal", WithdrawalController::class)->except(
        "update",
        "delete",
    );

    // Buyer routes - custom routes BEFORE resource
    Route::get("buyer/all/paginated", [
        BuyerController::class,
        "getAllPaginated",
    ]);
    Route::apiResource("buyer", BuyerController::class);

    // Address routes
    Route::apiResource(
        "address",
        \App\Http\Controllers\AddressController::class,
    );

    // Product Category routes - custom routes BEFORE resource
    // Route::get('product-category/all/paginated', [ProductCategoryController::class, 'getAllPaginated']); // Moved to public
    // Route::get('product-category/slug/{slug}', [ProductCategoryController::class, 'showBySlug']); // Moved to public
    Route::apiResource(
        "product-category",
        ProductCategoryController::class,
    )->except(["index", "show"]);

    // Product routes - custom routes BEFORE resource
    // Route::get('product/all/paginated', [ProductController::class, 'getAllPaginated']); // Moved to public
    // Route::get('product/slug/{slug}', [ProductController::class, 'showBySlug']); // Moved to public
    Route::apiResource("product", ProductController::class)->except([
        "index",
        "show",
    ]);

    // Transaction routes - custom routes BEFORE resource
    Route::get("transaction/chart-data", [
        TransactionController::class,
        "getChartData",
    ]);
    Route::get("transaction/all/paginated", [
        TransactionController::class,
        "getAllPaginated",
    ]);
    Route::get("transaction/code/{code}", [
        TransactionController::class,
        "showByCode",
    ]);
    Route::post("transaction/{id}/complete", [
        TransactionController::class,
        "complete",
    ]);
    Route::post("transaction/{id}/check-status", [
        TransactionController::class,
        "checkPaymentStatus",
    ]);
    // Route::middleware(['throttle:10,1', 'verified'])->post('transaction', [TransactionController::class, 'store']);
    Route::middleware(["throttle:10,1", "idempotent"])->post("transaction", [
        TransactionController::class,
        "store",
    ]);
    Route::apiResource("transaction", TransactionController::class)->except([
        "store",
    ]);

    // Product Review
    Route::get("product-review/all/paginated", [
        ProductReviewController::class,
        "getAllPaginated",
    ]);
    Route::post("product-review", [ProductReviewController::class, "store"]);

    // Wishlist
    Route::get("wishlist", [
        App\Http\Controllers\WishlistController::class,
        "index",
    ]);
    Route::post("wishlist", [
        App\Http\Controllers\WishlistController::class,
        "store",
    ]);
    // Chat Routes
    Route::get("chat/contacts", [
        App\Http\Controllers\ChatController::class,
        "getContacts",
    ]);
    Route::get("chat/user/{id}", [
        App\Http\Controllers\ChatController::class,
        "getUserInfo",
    ]);
    Route::get("chat/{user}", [
        App\Http\Controllers\ChatController::class,
        "getMessages",
    ]);
    Route::post("chat/send", [
        App\Http\Controllers\ChatController::class,
        "sendMessage",
    ]);
});

// Auth routes
Route::middleware("throttle:6,1")->group(function () {
    Route::post("register", [AuthController::class, "register"]);
    Route::post("login", [AuthController::class, "login"]);
});

// Verification Routes
Route::get("/email/verify/{id}/{hash}", [
    \App\Http\Controllers\VerificationController::class,
    "verify",
])
    ->middleware(["signed", "throttle:6,1"])
    ->name("verification.verify");

Route::post("/email/resend", [
    \App\Http\Controllers\VerificationController::class,
    "resend",
])
    ->middleware(["auth:sanctum", "throttle:6,1"])
    ->name("verification.send");
Route::middleware(["web"])->group(function () {
    Route::get("auth/google/redirect", [
        \App\Http\Controllers\GoogleAuthController::class,
        "redirect",
    ]);
    Route::get("auth/google/callback", [
        \App\Http\Controllers\GoogleAuthController::class,
        "callback",
    ]);
});

// Password Reset Routes (Public, throttled)
Route::middleware("throttle:5,1")->group(function () {
    Route::post("password/forgot", [
        \App\Http\Controllers\ForgotPasswordController::class,
        "sendResetLink",
    ]);
    Route::post("password/reset", [
        \App\Http\Controllers\ForgotPasswordController::class,
        "resetPassword",
    ]);
});

// Midtrans callback
Route::post("/midtrans-callback", [MidtransController::class, "callback"]);

// Logistics Webhook (Simulation - RajaOngkir/Komerce)
Route::post("/logistics/webhook", [
    \App\Http\Controllers\LogisticsController::class,
    "webhook",
]);
