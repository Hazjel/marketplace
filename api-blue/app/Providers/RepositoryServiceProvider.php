<?php

namespace App\Providers;

use App\Interfaces\AuthRepositoryInterface;
use App\Interfaces\BuyerRepositoryInterface;
use App\Interfaces\CartRepositoryInterface;
use App\Interfaces\ChatAssistantInterface;
use App\Interfaces\ChatRepositoryInterface;
use App\Interfaces\EscrowRepositoryInterface;
use App\Interfaces\PaymentGatewayInterface;
use App\Interfaces\ProductCategoryRepositoryInterface;
use App\Interfaces\ProductRepositoryInterface;
use App\Interfaces\ProductReviewRepositoryInterface;
use App\Interfaces\ProductViewRepositoryInterface;
use App\Interfaces\StoreBalanceHistoryRepositoryInterface;
use App\Interfaces\StoreBalanceRepositoryInterface;
use App\Interfaces\StoreRepositoryInterface;
use App\Interfaces\TransactionAnalyticsRepositoryInterface;
use App\Interfaces\TransactionDetailRepositoryInterface;
use App\Interfaces\TransactionRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Interfaces\WishlistRepositoryInterface;
use App\Interfaces\WithdrawalRepositoryInterface;
use App\Repositories\AuthRepository;
use App\Repositories\BuyerRepository;
use App\Repositories\CartRepository;
use App\Repositories\ChatRepository;
use App\Repositories\EscrowRepository;
use App\Repositories\ProductCategoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\ProductReviewRepository;
use App\Repositories\ProductViewRepository;
use App\Repositories\StoreBalanceHistoryRepository;
use App\Repositories\StoreBalanceRepository;
use App\Repositories\StoreRepository;
use App\Repositories\TransactionAnalyticsRepository;
use App\Repositories\TransactionDetailRepository;
use App\Repositories\TransactionRepository;
use App\Repositories\UserRepository;
use App\Repositories\WishlistRepository;
use App\Repositories\WithdrawalRepository;
use App\Services\HttpChatAssistant;
use App\Services\MidtransPaymentGateway;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(StoreRepositoryInterface::class, StoreRepository::class);
        $this->app->bind(StoreBalanceRepositoryInterface::class, StoreBalanceRepository::class);
        $this->app->bind(StoreBalanceHistoryRepositoryInterface::class, StoreBalanceHistoryRepository::class);
        $this->app->bind(WithdrawalRepositoryInterface::class, WithdrawalRepository::class);
        $this->app->bind(BuyerRepositoryInterface::class, BuyerRepository::class);
        $this->app->bind(ProductCategoryRepositoryInterface::class, ProductCategoryRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(TransactionRepositoryInterface::class, TransactionRepository::class);
        $this->app->bind(TransactionDetailRepositoryInterface::class, TransactionDetailRepository::class);
        $this->app->bind(ProductReviewRepositoryInterface::class, ProductReviewRepository::class);
        $this->app->bind(ProductViewRepositoryInterface::class, ProductViewRepository::class);
        $this->app->bind(AuthRepositoryInterface::class, AuthRepository::class);
        $this->app->bind(WishlistRepositoryInterface::class, WishlistRepository::class);
        $this->app->bind(CartRepositoryInterface::class, CartRepository::class);
        $this->app->bind(ChatRepositoryInterface::class, ChatRepository::class);
        $this->app->bind(EscrowRepositoryInterface::class, EscrowRepository::class);
        $this->app->bind(TransactionAnalyticsRepositoryInterface::class, TransactionAnalyticsRepository::class);
        $this->app->bind(PaymentGatewayInterface::class, MidtransPaymentGateway::class);
        $this->app->bind(ChatAssistantInterface::class, HttpChatAssistant::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
