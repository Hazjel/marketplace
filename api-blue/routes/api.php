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
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function(){
    Route::get('me', [AuthController::class, 'me']);
    Route::put('profile', [AuthController::class, 'updateProfile']);
    Route::post('logout', [AuthController::class, 'logout']);

    // User routes - custom routes BEFORE resource
    Route::get('user/all/paginated', [UserController::class, 'getAllPaginated']);
    Route::apiResource('user', UserController::class);

    // Store routes - custom routes BEFORE resource
    Route::get('store/all/paginated', [StoreController::class, 'getAllPaginated']);
    Route::post('store/{id}/verified', [StoreController::class, 'updateVerifiedStatus']);
    Route::get('store/username/{store}', [StoreController::class, 'showByUsername']);
    Route::get('my-store', [StoreController::class, 'showByUser']);
    Route::apiResource('store', StoreController::class);

    // Store Balance routes - custom routes BEFORE resource
    Route::get('store-balance/all/paginated', [StoreBalanceController::class, 'getAllPaginated']);
    Route::get('my-store-balance', [StoreBalanceController::class, 'showByStore']);
    Route::apiResource('store-balance', StoreBalanceController::class)->except(['store', 'update', 'delete']);

    // Store Balance History routes - custom routes BEFORE resource
    Route::get('store-balance-history/all/paginated', [StoreBalanceHistoryController::class, 'getAllPaginated']);
    Route::apiResource('store-balance-history', StoreBalanceHistoryController::class)->except(['store', 'update', 'delete']);

    // Withdrawal routes - custom routes BEFORE resource
    Route::get('withdrawal/all/paginated', [WithdrawalController::class, 'getAllPaginated']);
    Route::post('withdrawal/{id}/approve', [WithdrawalController::class, 'approve']);
    Route::apiResource('withdrawal', WithdrawalController::class)->except('update', 'delete');

    // Buyer routes - custom routes BEFORE resource
    Route::get('buyer/all/paginated', [BuyerController::class, 'getAllPaginated']);
    Route::apiResource('buyer', BuyerController::class);

    // Product Category routes - custom routes BEFORE resource
    Route::get('product-category/all/paginated', [ProductCategoryController::class, 'getAllPaginated']);
    Route::get('product-category/slug/{slug}', [ProductCategoryController::class, 'showBySlug']);
    Route::apiResource('product-category', ProductCategoryController::class);

    // Product routes - custom routes BEFORE resource
    Route::get('product/all/paginated', [ProductController::class, 'getAllPaginated']);
    Route::get('product/slug/{slug}', [ProductController::class, 'showBySlug']);
    Route::apiResource('product', ProductController::class);

    // Transaction routes - custom routes BEFORE resource
    Route::get('transaction/all/paginated', [TransactionController::class, 'getAllPaginated']);
    Route::get('transaction/code/{code}', [TransactionController::class, 'showByCode']);
    Route::apiResource('transaction', TransactionController::class);

    // Product Review
    Route::post('product-review', [ProductReviewController::class, 'store']);

    // Wishlist
    Route::get('wishlist', [App\Http\Controllers\WishlistController::class, 'index']);
    Route::post('wishlist', [App\Http\Controllers\WishlistController::class, 'store']);
});

// Public routes (no authentication required)
Route::get('product-category', [ProductCategoryController::class, 'index']);
Route::get('product-category/all/paginated', [ProductCategoryController::class, 'getAllPaginated']);
Route::get('product-category/slug/{slug}', [ProductCategoryController::class, 'showBySlug']);

Route::get('product', [ProductController::class, 'index']);
Route::get('product/all/paginated', [ProductController::class, 'getAllPaginated']);
Route::get('product/slug/{slug}', [ProductController::class, 'showBySlug']);

Route::get('store', [StoreController::class, 'index']);
Route::get('store/username/{store}', [StoreController::class, 'showByUsername']);

// Auth routes
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

// Midtrans callback
Route::post('/midtrans-callback', [MidtransController::class, 'callback']);