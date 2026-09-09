<?php

namespace Tests\Unit;

use App\Enums\RoundingMode;
use App\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RangeException;

/**
 * Contract: docs/money-contract.md. Money is a pure value object with no
 * framework dependency, so this extends PHPUnit's TestCase directly.
 */
class MoneyTest extends TestCase
{
    public function test_rupiah_and_zero_factories(): void
    {
        $this->assertSame(10_500_000, Money::rupiah(10_500_000)->minor());
        $this->assertSame(0, Money::zero()->minor());
        $this->assertTrue(Money::zero()->isZero());
        $this->assertSame(-250, Money::rupiah(-250)->minor());
    }

    // --- fromDecimalString: valid syntax -----------------------------------

    #[DataProvider('validDecimalStrings')]
    public function test_from_decimal_string_accepts_whole_rupiah(string $input, int $expected): void
    {
        $this->assertSame($expected, Money::fromDecimalString($input)->minor());
    }

    public static function validDecimalStrings(): array
    {
        return [
            'no fraction' => ['10500000', 10_500_000],
            'one zero decimal' => ['10500000.0', 10_500_000],
            'two zero decimals' => ['10500000.00', 10_500_000],
            'negative no fraction' => ['-10500000', -10_500_000],
            'negative zero decimals' => ['-10500000.00', -10_500_000],
            'zero' => ['0', 0],
            'negative zero normalises' => ['-0.00', 0],
            'leading zeros' => ['007', 7],
            'leading zeros negative' => ['-007.0', -7],
        ];
    }

    // --- fromDecimalString: bad syntax ------------------------------------

    #[DataProvider('syntacticallyInvalidStrings')]
    public function test_from_decimal_string_rejects_bad_syntax(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromDecimalString($input);
    }

    public static function syntacticallyInvalidStrings(): array
    {
        return [
            'empty' => [''],
            'scientific' => ['1e5'],
            'explicit plus' => ['+100'],
            'thousands separator' => ['10,500'],
            'surrounding spaces' => [' 100 '],
            'leading dot' => ['.50'],
            'trailing dot' => ['100.'],
            'letters' => ['abc'],
            'three decimals' => ['100.000'],
            'double minus' => ['--100'],
        ];
    }

    // --- fromDecimalString: fractional rupiah is a domain violation -------

    #[DataProvider('fractionalRupiahStrings')]
    public function test_from_decimal_string_rejects_fractional_rupiah(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::fromDecimalString($input);
    }

    public static function fractionalRupiahStrings(): array
    {
        return [
            ['10.5'],
            ['10.50'],
            ['10.01'],
            ['-10.50'],
            ['0.01'],
        ];
    }

    public function test_from_decimal_string_rejects_value_above_int_range(): void
    {
        $this->expectException(RangeException::class);
        // PHP_INT_MAX is 9223372036854775807
        Money::fromDecimalString('9223372036854775808');
    }

    public function test_from_decimal_string_accepts_exact_int_max(): void
    {
        $this->assertSame(PHP_INT_MAX, Money::fromDecimalString('9223372036854775807.00')->minor());
        $this->assertSame(PHP_INT_MIN, Money::fromDecimalString('-9223372036854775808')->minor());
    }

    // --- add / subtract --------------------------------------------------

    public function test_add_and_subtract_are_exact(): void
    {
        $a = Money::rupiah(10_500_000);
        $b = Money::rupiah(1_155_000);

        $this->assertSame(11_655_000, $a->add($b)->minor());
        $this->assertSame(9_345_000, $a->subtract($b)->minor());
        $this->assertSame(-1_155_000, Money::zero()->subtract($b)->minor());
    }

    public function test_add_is_immutable(): void
    {
        $a = Money::rupiah(100);
        $a->add(Money::rupiah(50));
        $this->assertSame(100, $a->minor());
    }

    public function test_add_overflow_throws(): void
    {
        $this->expectException(RangeException::class);
        Money::rupiah(PHP_INT_MAX)->add(Money::rupiah(1));
    }

    public function test_subtract_overflow_throws(): void
    {
        $this->expectException(RangeException::class);
        Money::rupiah(PHP_INT_MIN)->subtract(Money::rupiah(1));
    }

    // --- multiplyByQty -------------------------------------------------

    public function test_multiply_by_qty_is_exact(): void
    {
        $this->assertSame(31_500_000, Money::rupiah(10_500_000)->multiplyByQty(3)->minor());
        $this->assertSame(10_500_000, Money::rupiah(10_500_000)->multiplyByQty(1)->minor());
    }

    #[DataProvider('nonPositiveQuantities')]
    public function test_multiply_by_qty_rejects_non_positive_quantity(int $qty): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::rupiah(1_000)->multiplyByQty($qty);
    }

    public static function nonPositiveQuantities(): array
    {
        return [[0], [-1], [-100]];
    }

    public function test_multiply_by_qty_overflow_throws(): void
    {
        $this->expectException(RangeException::class);
        Money::rupiah(PHP_INT_MAX)->multiplyByQty(2);
    }

    // --- percentage: HALF_UP semantics --------------------------------

    #[DataProvider('percentageCases')]
    public function test_percentage_rounds_half_up(int $amount, int $basisPoints, int $expected): void
    {
        $this->assertSame(
            $expected,
            Money::rupiah($amount)->percentage($basisPoints)->minor()
        );
    }

    public static function percentageCases(): array
    {
        return [
            // production case: 11% PPN of a typical order
            '11% of 10,500,000' => [10_500_000, 1100, 1_155_000],
            // production case: 10% admin fee
            '10% of 9_345_000' => [9_345_000, 1000, 934_500],
            // exact, no rounding
            '1% of 100' => [100, 100, 1],
            // tie -> away from zero (up)
            '1% of 150 = 1.5 -> 2' => [150, 100, 2],
            'just under tie: 1% of 149 = 1.49 -> 1' => [149, 100, 1],
            'just over tie: 1% of 151 = 1.51 -> 2' => [151, 100, 2],
            // negative amounts: tie away from zero (down)
            '1% of -150 = -1.5 -> -2' => [-150, 100, -2],
            '1% of -149 = -1.49 -> -1' => [-149, 100, -1],
            // zero rate / zero amount
            '0% of anything' => [123_456, 0, 0],
            'any% of zero' => [0, 5000, 0],
            // rate above 100%
            '150% of 200' => [200, 15_000, 300],
        ];
    }

    public function test_percentage_rejects_negative_basis_points(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::rupiah(1_000)->percentage(-1);
    }

    public function test_percentage_mode_argument_is_accepted(): void
    {
        $this->assertSame(
            1_155_000,
            Money::rupiah(10_500_000)->percentage(1100, RoundingMode::HALF_UP)->minor()
        );
    }

    public function test_percentage_is_overflow_safe_for_large_amounts(): void
    {
        // Naive `amount * basisPoints` (9223372036854775807 * 1100)
        // overflows; the q*10_000+r decomposition keeps every
        // intermediate in int range. Exact maths:
        //   PHP_INT_MAX * 11 / 100 = 1014570924054025338.77 -> 1014570924054025339
        $this->assertSame(
            1_014_570_924_054_025_339,
            Money::rupiah(PHP_INT_MAX)->percentage(1100)->minor()
        );
    }

    // --- clampMin -----------------------------------------------------

    public function test_clamp_min(): void
    {
        $floor = Money::zero();
        $this->assertSame(0, Money::rupiah(-500)->clampMin($floor)->minor());
        $this->assertSame(500, Money::rupiah(500)->clampMin($floor)->minor());
        $this->assertSame(0, Money::zero()->clampMin($floor)->minor());
    }

    // --- comparisons -------------------------------------------------

    public function test_comparisons(): void
    {
        $a = Money::rupiah(100);
        $b = Money::rupiah(250);
        $c = Money::rupiah(100);

        $this->assertTrue($a->equals($c));
        $this->assertFalse($a->equals($b));

        $this->assertTrue($a->lessThan($b));
        $this->assertFalse($b->lessThan($a));
        $this->assertTrue($b->greaterThan($a));
        $this->assertFalse($a->greaterThan($b));

        $this->assertSame(-1, $a->compareTo($b));
        $this->assertSame(1, $b->compareTo($a));
        $this->assertSame(0, $a->compareTo($c));

        $this->assertTrue(Money::rupiah(-1)->isNegative());
        $this->assertFalse(Money::zero()->isNegative());
    }

    // --- JSON -------------------------------------------------------

    public function test_json_serialises_as_integer(): void
    {
        $this->assertSame('10500000', json_encode(Money::rupiah(10_500_000)));
        $this->assertSame('0', json_encode(Money::zero()));
        $this->assertSame('{"subtotal":31500000}', json_encode(['subtotal' => Money::rupiah(31_500_000)]));
    }

    public function test_no_to_string_magic_method(): void
    {
        // Money must not slip into a string context silently.
        $this->assertFalse(method_exists(Money::class, '__toString'));
    }
}
