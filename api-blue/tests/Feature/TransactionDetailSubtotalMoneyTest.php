<?php

namespace Tests\Feature;

use App\Http\Resources\TransactionDetailResource;
use App\Interfaces\TransactionDetailRepositoryInterface;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\TransactionDetail;
use App\ValueObjects\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * B3.1 persistence pilot: transaction_details.subtotal is the one column
 * cast to Money. This test pins the boundary behaviour — round-trip,
 * legacy int bridge, rejection of imprecise writes, rejection of a
 * fractional value already in the DB — and characterises the resulting
 * API contract change (float -> integer JSON).
 *
 * See docs/money-contract.md.
 */
class TransactionDetailSubtotalMoneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // ProductFactory requires at least one category to exist.
        ProductCategory::firstOrCreate(
            ['slug' => 'money-pilot'],
            ['name' => 'Money Pilot', 'description' => 'fixture']
        );
    }

    public function test_subtotal_round_trips_as_money(): void
    {
        $detail = TransactionDetail::factory()->create([
            'subtotal' => Money::rupiah(31_500_000),
        ]);

        $this->assertInstanceOf(Money::class, $detail->subtotal);
        $this->assertSame(31_500_000, $detail->subtotal->minor());

        $reloaded = $detail->fresh();
        $this->assertInstanceOf(Money::class, $reloaded->subtotal);
        $this->assertSame(31_500_000, $reloaded->subtotal->minor());

        // stored as a whole-rupiah value in the decimal column (the exact
        // textual form — "31500000" vs "31500000.00" — is engine-specific)
        $raw = (string) DB::table('transaction_details')->where('id', $detail->id)->value('subtotal');
        $this->assertSame(31_500_000, Money::fromDecimalString($raw)->minor());
    }

    public function test_int_is_accepted_as_a_legacy_bridge(): void
    {
        $detail = TransactionDetail::factory()->create(['subtotal' => 12_345]);

        $this->assertSame(12_345, $detail->fresh()->subtotal->minor());
    }

    public function test_float_write_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TransactionDetail::factory()->create(['subtotal' => 12_345.67]);
    }

    public function test_string_write_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TransactionDetail::factory()->create(['subtotal' => '12345']);
    }

    public function test_a_fractional_value_already_in_the_column_throws_on_read(): void
    {
        $detail = TransactionDetail::factory()->create(['subtotal' => Money::rupiah(1_000)]);

        // bypass the cast to plant a value that violates the invariant
        DB::table('transaction_details')->where('id', $detail->id)->update(['subtotal' => '1000.50']);

        $this->expectException(InvalidArgumentException::class);
        TransactionDetail::find($detail->id)->subtotal;
    }

    public function test_resource_emits_subtotal_as_an_integer_not_a_float(): void
    {
        $detail = TransactionDetail::factory()->create([
            'subtotal' => Money::rupiah(31_500_000),
        ]);

        $array = (new TransactionDetailResource($detail))->toArray(request());

        $this->assertSame(31_500_000, $array['subtotal']);

        // characterise the JSON wire form: integer, no ".0"
        $json = json_encode($array);
        $this->assertStringContainsString('"subtotal":31500000', $json);
        $this->assertStringNotContainsString('31500000.0', $json);
    }

    public function test_writer_multiplies_unit_price_by_quantity_exactly(): void
    {
        $product = Product::factory()->create(['price' => 10_500_000]);

        $detail = app(TransactionDetailRepositoryInterface::class)->create([
            'transaction_id' => TransactionDetail::factory()->create()->transaction_id,
            'product_id' => $product->id,
            'qty' => 3,
            'unit_price' => (string) $product->price,
        ]);

        $this->assertSame(31_500_000, $detail->fresh()->subtotal->minor());
    }

    public function test_writer_rejects_a_fractional_unit_price(): void
    {
        $product = Product::factory()->create(['price' => 10_500_000]);

        // TransactionDetailRepository::create() rewraps every failure as a
        // plain Exception carrying the original message.
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('rupiah bulat');

        app(TransactionDetailRepositoryInterface::class)->create([
            'transaction_id' => TransactionDetail::factory()->create()->transaction_id,
            'product_id' => $product->id,
            'qty' => 1,
            'unit_price' => '10500000.50',
        ]);
    }
}
