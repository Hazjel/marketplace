<?php

namespace App\ValueObjects;

use App\Enums\RoundingMode;
use InvalidArgumentException;
use JsonSerializable;
use RangeException;

/**
 * Immutable value object: a whole-rupiah (IDR) monetary amount.
 *
 * Domain invariant: the amount is an exact integer number of rupiah.
 * Indonesian Rupiah has no sub-rupiah unit in circulation and every
 * money column in this codebase stores whole values, so the canonical
 * representation is a plain 64-bit integer (SCALE 0) — not "cents".
 *
 * Money does NOT cover the full range of the decimal(26,2) columns it is
 * stored in; it guarantees only amounts representable as a PHP int
 * (|amount| <= PHP_INT_MAX). Any arithmetic that would overflow that
 * range throws (RangeException) instead of silently degrading to float.
 *
 * See docs/money-contract.md.
 */
final class Money implements JsonSerializable
{
    public const SCALE = 0;

    public const CURRENCY = 'IDR';

    private function __construct(private readonly int $minor) {}

    public static function rupiah(int $amount): self
    {
        return new self($amount);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Parse a decimal string as stored in a decimal(26,2) column.
     *
     * Two stages: syntax first, then the whole-rupiah invariant. A
     * syntactically valid value with a non-zero fractional part
     * ("10.50") is a domain violation and throws — it is never rounded
     * away.
     *
     * No float at any step (is_numeric()/(float)/round() would defeat
     * the point of the primitive).
     */
    public static function fromDecimalString(string $amount): self
    {
        if (preg_match('/^-?\d+(?:\.\d{1,2})?$/', $amount) !== 1) {
            throw new InvalidArgumentException(
                "Bukan format desimal yang sah untuk Money: '{$amount}'."
            );
        }

        [$integerPart, $fractionPart] = explode('.', $amount, 2) + [1 => ''];

        if (rtrim($fractionPart, '0') !== '') {
            throw new InvalidArgumentException(
                "Money hanya menerima rupiah bulat, dapat pecahan: '{$amount}'."
            );
        }

        $negative = str_starts_with($integerPart, '-');
        $digits = ltrim($negative ? substr($integerPart, 1) : $integerPart, '0');
        $digits = $digits === '' ? '0' : $digits;

        self::assertDigitsWithinIntRange($digits, $negative, $amount);

        $normalized = ($negative && $digits !== '0' ? '-' : '').$digits;

        return new self((int) $normalized);
    }

    public function add(self $other): self
    {
        return new self(self::guardInt(
            $this->minor + $other->minor,
            'Penjumlahan Money melampaui jangkauan PHP int.'
        ));
    }

    public function subtract(self $other): self
    {
        return new self(self::guardInt(
            $this->minor - $other->minor,
            'Pengurangan Money melampaui jangkauan PHP int.'
        ));
    }

    /**
     * Exact quantity multiplication (line subtotal). No rounding.
     */
    public function multiplyByQty(int $qty): self
    {
        if ($qty < 1) {
            throw new InvalidArgumentException("Kuantitas harus >= 1, dapat {$qty}.");
        }

        return new self(self::guardInt(
            $this->minor * $qty,
            'Perkalian Money x kuantitas melampaui jangkauan PHP int.'
        ));
    }

    /**
     * `basisPoints` / 10_000 of this amount (1100 = 11%).
     *
     * The only method that rounds. HALF_UP = ties away from zero.
     *
     * Overflow-safe: the amount is decomposed as q * 10_000 + r before
     * multiplying, so the intermediate magnitude stays near
     * max(|amount|, 10_000 * basisPoints) rather than |amount| * basisPoints.
     */
    public function percentage(int $basisPoints, RoundingMode $mode = RoundingMode::HALF_UP): self
    {
        if ($basisPoints < 0) {
            throw new InvalidArgumentException("Basis points harus >= 0, dapat {$basisPoints}.");
        }

        // $mode is HALF_UP-only today; the parameter pins the semantics
        // at the call site and leaves room for more modes without a
        // signature change.
        unset($mode);

        $q = intdiv($this->minor, 10_000);
        $r = $this->minor - $q * 10_000; // same sign as $this->minor, |r| < 10_000

        $whole = self::guardInt(
            $q * $basisPoints,
            'Perkalian persentase Money melampaui jangkauan PHP int.'
        );

        $fractionNumerator = $r * $basisPoints; // |.| < 10_000 * basisPoints
        $fraction = intdiv($fractionNumerator, 10_000);
        $remainder = $fractionNumerator - $fraction * 10_000;

        if (abs($remainder) * 2 >= 10_000) {
            $fraction += $fractionNumerator >= 0 ? 1 : -1;
        }

        return new self(self::guardInt(
            $whole + $fraction,
            'Hasil persentase Money melampaui jangkauan PHP int.'
        ));
    }

    public function clampMin(self $floor): self
    {
        return $this->minor < $floor->minor ? $floor : $this;
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor;
    }

    public function compareTo(self $other): int
    {
        return $this->minor <=> $other->minor;
    }

    public function greaterThan(self $other): bool
    {
        return $this->minor > $other->minor;
    }

    public function lessThan(self $other): bool
    {
        return $this->minor < $other->minor;
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function minor(): int
    {
        return $this->minor;
    }

    public function jsonSerialize(): int
    {
        return $this->minor;
    }

    /**
     * PHP promotes 64-bit integer overflow to float instead of wrapping,
     * so a non-int arithmetic result is exactly the overflow signal.
     */
    private static function guardInt(int|float $value, string $overflowMessage): int
    {
        if (is_int($value)) {
            return $value;
        }

        throw new RangeException($overflowMessage);
    }

    /**
     * @param  string  $digits  leading-zero-stripped, unsigned
     */
    private static function assertDigitsWithinIntRange(string $digits, bool $negative, string $original): void
    {
        // PHP_INT_MAX = 9223372036854775807, |PHP_INT_MIN| = 9223372036854775808
        $limit = $negative ? '9223372036854775808' : '9223372036854775807';

        if (
            strlen($digits) > strlen($limit)
            || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)
        ) {
            throw new RangeException(
                "Nilai di luar jangkauan Money (PHP int): '{$original}'."
            );
        }
    }
}
