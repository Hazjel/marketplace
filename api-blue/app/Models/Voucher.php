<?php

namespace App\Models;

use App\Traits\UUID;
use App\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    /**
     * `value` (a decimal:2 percentage like "10.50") as exact basis points:
     * "10.50" -> 1050, "11.00" -> 1100, "0.01" -> 1. No float.
     */
    private function percentageBasisPoints(): int
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $this->value, 2), 2, '0');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        return ((int) $whole) * 100 + (int) $fraction;
    }
}
