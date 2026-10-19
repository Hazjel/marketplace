<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Buyer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductReview;
use App\Models\ProductReviewAttachment;
use App\Models\Store;
use App\Models\Transaction;
use App\Models\TransactionDetail;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityPiiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $reviewer;

    private Product $product;

    private ProductReview $review;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $seller = User::factory()->create();
        $seller->assignRole('store');
        $store = Store::factory()->create(['user_id' => $seller->id]);
        $category = ProductCategory::create([
            'name' => 'Security Category',
            'slug' => 'security-category',
            'description' => 'security',
        ]);
        $this->product = Product::factory()->create([
            'store_id' => $store->id,
            'product_category_id' => $category->id,
            'name' => 'Security Product',
            'slug' => 'security-product',
            'has_variants' => false,
        ]);

        $this->reviewer = User::factory()->create([
            'name' => 'Alice Reviewer',
            'email' => 'alice@example.test',
            'username' => 'alice-reviewer',
        ]);
        $this->reviewer->assignRole('buyer');
        $buyer = Buyer::factory()->create(['user_id' => $this->reviewer->id]);
        $transaction = Transaction::create([
            'code' => 'SEC_REVIEW_001',
            'buyer_id' => $buyer->id,
            'store_id' => $store->id,
            'address_id' => 1,
            'address' => 'Secret Buyer Address',
            'city' => 'Jakarta',
            'postal_code' => '12345',
            'shipping' => 'JNE',
            'shipping_type' => 'REG',
            'shipping_cost' => 10000,
            'tax' => 0,
            'grand_total' => 110000,
            'payment_status' => 'paid',
            'delivery_status' => 'completed',
            'payment_code' => 'SEC_PAYMENT_001',
            'snap_token' => 'SEC_SNAP_TOKEN',
            'tracking_number' => 'SEC_TRACKING',
        ]);
        TransactionDetail::create([
            'transaction_id' => $transaction->id,
            'product_id' => $this->product->id,
            'qty' => 1,
            'subtotal' => 100000,
        ]);
        $this->review = ProductReview::create([
            'transaction_id' => $transaction->id,
            'product_id' => $this->product->id,
            'user_id' => $this->reviewer->id,
            'rating' => 5,
            'review' => 'Great product',
            'is_anonymous' => true,
        ]);
        ProductReviewAttachment::create([
            'product_review_id' => $this->review->id,
            'file_path' => 'storage/reviews/review.jpg',
            'file_type' => 'image',
        ]);
    }

    public function test_public_product_details_do_not_expose_review_transaction_data(): void
    {
        foreach ([
            "/api/product/{$this->product->id}",
            "/api/product/slug/{$this->product->slug}",
        ] as $url) {
            $review = $this->getJson($url)
                ->assertOk()
                ->json('data.product_reviews.0');

            $this->assertNotNull($review);
            foreach ([
                'transaction', 'address', 'dest_latitude', 'dest_longitude',
                'phone_number', 'email', 'payment_code', 'snap_token', 'tracking_number',
            ] as $key) {
                $this->assertArrayNotHasKey($key, $review);
            }
            $this->assertSame(5, $review['rating']);
            $this->assertSame('Great product', $review['review']);
            $this->assertArrayHasKey('attachments', $review);
            $this->assertArrayHasKey('created_at', $review);
        }
    }

    public function test_only_admin_can_list_reviews_and_listing_is_pii_free(): void
    {
        $buyerResponse = $this->actingAs($this->reviewer, 'sanctum')
            ->getJson('/api/product-review/all/paginated');
        $buyerResponse->assertForbidden();

        $seller = User::factory()->create();
        $seller->assignRole('store');
        $this->actingAs($seller, 'sanctum')
            ->getJson('/api/product-review/all/paginated')
            ->assertForbidden();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/product-review/all/paginated');
        $response->assertOk();
        $review = collect($response->json('data'))->first();
        $this->assertNotNull($review);
        $this->assertArrayNotHasKey('transaction', $review);
        $this->assertArrayNotHasKey('email', $review);
        $this->assertArrayNotHasKey('phone_number', $review);
        $this->assertArrayNotHasKey('payment_code', $review);
        $this->assertArrayNotHasKey('snap_token', $review);
        $this->assertArrayNotHasKey('address', $review);
        $this->assertArrayNotHasKey('dest_latitude', $review);
        $this->assertArrayNotHasKey('dest_longitude', $review);
    }

    public function test_review_listing_rejects_unapproved_sort_and_page_size(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/product-review/all/paginated?sort_by=payment_code')
            ->assertStatus(422);
        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/product-review/all/paginated?row_per_page=101')
            ->assertStatus(422);
    }

    public function test_anonymous_review_does_not_expose_reviewer_identity(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        foreach ([
            $this->getJson("/api/product/{$this->product->id}")->json('data.product_reviews.0'),
            $this->actingAs($admin, 'sanctum')
                ->getJson('/api/product-review/all/paginated')->json('data.0'),
        ] as $review) {
            $this->assertArrayNotHasKey('email', $review['user'] ?? []);
            $this->assertArrayNotHasKey('username', $review['user'] ?? []);
            $this->assertArrayNotHasKey('id', $review['user'] ?? []);
            $this->assertStringNotContainsString('Alice', (string) ($review['user']['name'] ?? ''));
            $this->assertStringNotContainsString('Reviewer', (string) ($review['user']['name'] ?? ''));
        }
    }

    public function test_address_owner_cannot_transfer_address_to_another_user(): void
    {
        $other = User::factory()->create();
        $address = Address::create([
            'user_id' => $this->reviewer->id,
            'label' => 'Home',
            'recipient_name' => 'Alice Reviewer',
            'phone' => '08123456789',
            'address' => 'Old Address',
            'city' => 'Jakarta',
            'city_id' => '1',
            'postal_code' => '12345',
        ]);
        $this->actingAs($this->reviewer, 'sanctum')
            ->putJson("/api/address/{$address->id}", [
                'user_id' => $other->id,
                'label' => 'Home',
                'recipient_name' => 'Alice Reviewer',
                'phone' => '08123456789',
                'address' => 'New Address',
                'city' => 'Jakarta',
                'city_id' => '1',
                'postal_code' => '12345',
            ])
            ->assertOk();

        $this->assertSame($this->reviewer->id, $address->fresh()->user_id);
        $this->assertDatabaseMissing('addresses', [
            'user_id' => $other->id,
            'id' => $address->id,
        ]);
    }
}
