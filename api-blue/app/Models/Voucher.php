<?php

namespace App\Models;

use App\Traits\UUID;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RangeException;

class Voucher extends Model
{
    use HasFactory, UUID;

    protected $fillable = [
        'code',
        'store_id',
        'type',
        'value',
        'min_purchase',
        'max_discount',
        'usage_limit',
        'usage_limit_per_buyer',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_purchase' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function redemptions()
    {
        return $this->hasMany(VoucherRedemption::class);
    }

    /**
     * Single source of truth for redemption eligibility — used by both the
     * validate-only preview endpoint (VoucherController::validate) and the
     * actual checkout (TransactionRepository::create). Both MUST call this
     * same method rather than re-implementing the rules, otherwise the two
     * call sites can disagree and a discount-bypass bug becomes possible.
     *
     * Returns ['valid' => bool, 'message' => ?string, 'discount_amount' => ?Money].
     * On failure, 'message' explains which specific rule failed (shown
     * directly to the buyer) rather than a generic "invalid voucher".
     *
     * B3.2c: `$subtotal` and the returned discount are Money. Percentage
     * rates are parsed to exact basis points (no float); fixed value and
     * max_discount are whole-rupiah Money (a fractional legacy value
     * throws — see api-blue/docs/money-contract.md).
     */
    public function validateFor(string $buyerId, string $storeId, Money $subtotal): array
    {
        if (! $this->is_active) {
            return ['valid' => false, 'message' => 'Voucher tidak aktif', 'discount_amount' => null];
        }

        $now = now();
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return ['valid' => false, 'message' => 'Voucher belum berlaku', 'discount_amount' => null];
        }
        if ($this->expires_at && $now->gt($this->expires_at)) {
            return ['valid' => false, 'message' => 'Voucher sudah kedaluwarsa', 'discount_amount' => null];
        }

        if ($this->store_id !== null && (string) $this->store_id !== $storeId) {
            return ['valid' => false, 'message' => 'Voucher tidak berlaku untuk toko ini', 'discount_amount' => null];
        }

        if ($this->min_purchase !== null
            && $subtotal->lessThan(Money::fromDecimalString((string) $this->min_purchase))) {
            return [
                'valid' => false,
                'message' => 'Minimal belanja Rp'.number_format((float) $this->min_purchase, 0, ',', '.').' untuk memakai voucher ini',
                'discount_amount' => null,
            ];
        }

        if ($this->usage_limit !== null) {
            $totalUsed = $this->redemptions()->count();
            if ($totalUsed >= $this->usage_limit) {
                return ['valid' => false, 'message' => 'Voucher sudah mencapai batas penggunaan', 'discount_amount' => null];
            }
        }

        if ($this->usage_limit_per_buyer !== null) {
            $usedByBuyer = $this->redemptions()->where('buyer_id', $buyerId)->count();
            if ($usedByBuyer >= $this->usage_limit_per_buyer) {
                return ['valid' => false, 'message' => 'Anda sudah mencapai batas pemakaian voucher ini', 'discount_amount' => null];
            }
        }

        $discount = $this->type === 'percentage'
            ? $this->percentageDiscount($subtotal)
            : $this->fixedDiscount($subtotal);

        return ['valid' => true, 'message' => null, 'discount_amount' => $discount];
    }

    private function percentageDiscount(Money $subtotal): Money
    {
        $discount = $subtotal->percentage($this->percentageBasisPoints());

        if ($this->max_discount !== null) {
            $cap = Money::fromDecimalString((string) $this->max_discount);
            if ($discount->greaterThan($cap)) {
                return $cap;
            }
        }

        return $discount;
    }

    private function fixedDiscount(Money $subtotal): Money
    {
        $value = Money::fromDecimalString((string) $this->value);

        return $value->greaterThan($subtotal) ? $subtotal : $value;
    }

    private function percentageBasisPoints(): int
    {
        return self::parsePercentageBasisPoints((string) $this->value);
    }

    /**
     * A percentage rate string ("10.50", "11", "0.01") as exact basis
     * points — "10.50" -> 1050, "11" -> 1100, "0.01" -> 1. No float.
     *
     * Shared by the seller-voucher write validation and the checkout read
     * path so they can never disagree. Rejects a non-2dp format and, since
     * vouchers.value is decimal(26,2) (far wider than a PHP int worth of
     * basis points), a rate whose basis points would overflow — the
     * largest representable is 92233720368547758.07 %.
     */
    public static function parsePercentageBasisPoints(string $value): int
    {
        if (preg_match('/^\d+(?:\.\d{1,2})?$/', $value) !== 1) {
            throw new InvalidArgumentException("Persentase voucher tidak sah: '{$value}'.");
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '0');
        $fraction = (int) str_pad(substr($fraction, 0, 2), 2, '0');

        $wholeInt = self::wholeDigitsToInt(ltrim($whole, '0') ?: '0', $value);

        $overflow = "Persentase voucher di luar jangkauan basis points: '{$value}'.";
        $hundred = Money::guardInt($wholeInt * 100, $overflow);

        return Money::guardInt($hundred + $fraction, $overflow);
    }

    private static function wholeDigitsToInt(string $digits, string $original): int
    {
        $max = (string) PHP_INT_MAX;

        if (strlen($digits) > strlen($max)
            || (strlen($digits) === strlen($max) && strcmp($digits, $max) > 0)) {
            throw new RangeException("Persentase voucher di luar jangkauan basis points: '{$original}'.");
        }

        return (int) $digits;
    }
}
