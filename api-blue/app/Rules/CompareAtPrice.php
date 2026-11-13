<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * "Harga sebelum diskon" next to its own price: `compare_at_price` checks
 * `price`, `variants.N.compare_at_price` checks `variants.N.price`.
 */
class CompareAtPrice implements DataAwareRule, ValidationRule
{
    /** A bigger cut than this is almost always a typo or a fake markup. */
    public const MAX_DISCOUNT_PERCENT = 90;

    /** @var array<string, mixed> */
    private array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $price = data_get($this->data, preg_replace('/compare_at_price$/', 'price', $attribute));

        // A bad price fails its own rule; nothing to compare against.
        if (filter_var($price, FILTER_VALIDATE_INT) === false || filter_var($value, FILTER_VALIDATE_INT) === false) {
            return;
        }

        $price = (int) $price;
        $value = (int) $value;

        if ($value <= $price) {
            $fail('Harga sebelum diskon harus lebih tinggi dari harga jual.');

            return;
        }

        if (($value - $price) * 100 > $value * self::MAX_DISCOUNT_PERCENT) {
            $fail('Diskon maksimal '.self::MAX_DISCOUNT_PERCENT.'%. Periksa lagi harga sebelum diskon.');
        }
    }
}
