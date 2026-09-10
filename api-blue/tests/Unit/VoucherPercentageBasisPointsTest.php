<?php

namespace Tests\Unit;

use App\Models\Voucher;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RangeException;

/**
 * B3.2 review fix: the percentage-rate -> basis-points parser is exact
 * and overflow-guarded. vouchers.value is decimal(26,2), far wider than a
 * PHP int worth of basis points, so a huge rate must throw rather than
 * silently truncate or TypeError.
 */
class VoucherPercentageBasisPointsTest extends TestCase
{
    #[DataProvider('validRates')]
    public function test_parses_exactly(string $rate, int $expected): void
    {
        $this->assertSame($expected, Voucher::parsePercentageBasisPoints($rate));
    }

    public static function validRates(): array
    {
        return [
            'whole' => ['11', 1100],
            'one decimal' => ['10.5', 1050],
            'two decimals' => ['10.50', 1050],
            'smallest' => ['0.01', 1],
            'zero' => ['0', 0],
            'leading zeros' => ['007.50', 750],
            // PHP_INT_MAX = 9223372036854775807 basis points
            'largest representable' => ['92233720368547758.07', PHP_INT_MAX],
        ];
    }

    #[DataProvider('badFormat')]
    public function test_rejects_bad_format(string $rate): void
    {
        $this->expectException(InvalidArgumentException::class);
        Voucher::parsePercentageBasisPoints($rate);
    }

    public static function badFormat(): array
    {
        return [
            'three decimals' => ['10.555'],
            'trailing dot' => ['10.'],
            'leading dot' => ['.5'],
            'negative' => ['-5'],
            'letters' => ['abc'],
            'empty' => [''],
            'scientific' => ['1e3'],
        ];
    }

    #[DataProvider('outOfRange')]
    public function test_rejects_out_of_range(string $rate): void
    {
        $this->expectException(RangeException::class);
        Voucher::parsePercentageBasisPoints($rate);
    }

    public static function outOfRange(): array
    {
        return [
            'one over the max' => ['92233720368547758.08'],
            'whole too large' => ['99999999999999999999'],
            'huge decimal(26,2)' => ['999999999999999999999999.99'],
        ];
    }
}
