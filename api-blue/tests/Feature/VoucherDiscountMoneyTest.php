<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Models\Voucher;
use App\ValueObjects\Money;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * B3.2c: Voucher::validateFor() takes and returns Money. Percentage rates
 * are parsed to exact basis points ("10.50" -> 1050), the discount is
 * `Money->percentage()` (HALF_UP), fixed value and max_discount are
 * whole-rupiah Money, and a fixed value is capped at the subtotal.
 */
class VoucherDiscountMoneyTest extends TestCase
{
    use RefreshDatabase;

    private function voucher(array $attrs): Voucher
    {
        return Voucher::create(array_merge([
            'code' => 'V'.uniqid(),
            'store_id' => null,
            'is_active' => true,
        ], $attrs));
    }

    private function discount(Voucher $v, int $subtotal): Money
    {
        $result = $v->validateFor('buyer-x', 'store-x', Money::rupiah($subtotal));
        $this->assertTrue($result['valid'], $result['message'] ?? '');
        $this->assertInstanceOf(Money::class, $result['discount_amount']);

        return $result['discount_amount'];
    }

    public function test_percentage_rate_with_two_decimals_is_exact(): void
    {
        // 10.50% of 100_000 = 10_500
        $v = $this->voucher(['type' => 'percentage', 'value' => 10.50]);
        $this->assertSame(10_500, $this->discount($v, 100_000)->minor());
    }

    public function test_percentage_discount_rounds_half_up(): void
    {
        // 10% of 100_005 = 10_000.5 -> HALF_UP -> 10_001
        $v = $this->voucher(['type' => 'percentage', 'value' => 10]);
        $this->assertSame(10_001, $this->discount($v, 100_005)->minor());
    }

    public function test_percentage_discount_is_capped_by_max_discount(): void
    {
        // 10% of 100_000 = 10_000, capped at 5_000
        $v = $this->voucher(['type' => 'percentage', 'value' => 10, 'max_discount' => 5_000]);
        $this->assertSame(5_000, $this->discount($v, 100_000)->minor());
    }

    public function test_fixed_discount_is_the_value(): void
    {
        $v = $this->voucher(['type' => 'fixed', 'value' => 20_000]);
        $this->assertSame(20_000, $this->discount($v, 100_000)->minor());
    }

    public function test_fixed_discount_is_capped_at_the_subtotal(): void
    {
        // a Rp150k voucher on a Rp100k cart discounts only Rp100k
        $v = $this->voucher(['type' => 'fixed', 'value' => 150_000]);
        $this->assertSame(100_000, $this->discount($v, 100_000)->minor());
    }

    public function test_min_purchase_is_compared_as_money(): void
    {
        $v = $this->voucher(['type' => 'fixed', 'value' => 5_000, 'min_purchase' => 50_000]);

        $below = $v->validateFor('b', 's', Money::rupiah(49_999));
        $this->assertFalse($below['valid']);
        $this->assertNull($below['discount_amount']);

        $atThreshold = $v->validateFor('b', 's', Money::rupiah(50_000));
        $this->assertTrue($atThreshold['valid']);
        $this->assertSame(5_000, $atThreshold['discount_amount']->minor());
    }

    public function test_validate_endpoint_rejects_a_fractional_subtotal(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $buyerUser = User::factory()->create();
        $buyerUser->assignRole('buyer');
        $buyerUser->buyer()->create(['phone_number' => '08', 'city' => 'B', 'address' => 'A']);
        $store = Store::factory()->create();

        $this->voucher(['code' => 'FRAC', 'type' => 'fixed', 'value' => 1_000]);

        $this->actingAs($buyerUser, 'sanctum')
            ->postJson('/api/voucher/validate', [
                'code' => 'FRAC', 'store_id' => $store->id, 'subtotal' => 10000.50,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('subtotal');
    }
}
