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
     * Overflow-safe with no float and no BCMath: BOTH operands are split
     * into a 10_000-quotient and remainder, so
     *
     *   amount * bp / 10_000
     *     = aQ*bQ*10_000 + aQ*bR + aR*bQ          (integer "whole" part)
     *     + aR*bR / 10_000                        (the only rounded term)
     *
     * where |aR|, bR < 10_000, so `aR*bR` (< 1e8) can never overflow.
     * Each product in the whole part is range-checked: if the true
     * result exceeds PHP_INT_MAX it throws RangeException; if it fits,
     * it is computed exactly regardless of how large `basisPoints` is.
     */
    public function percentage(int $basisPoints, RoundingMode $mode = RoundingMode::HALF_UP): self
    {
        if ($basisPoints < 0) {
            throw new InvalidArgumentException("Basis points harus >= 0, dapat {$basisPoints}.");
        }

        $overflow = 'Perhitungan persentase Money melampaui jangkauan PHP int.';

        $amountQ = intdiv($this->minor, 10_000);
        $amountR = $this->minor % 10_000;     // sign of $minor, |.| < 10_000
        $bpQ = intdiv($basisPoints, 10_000);
        $bpR = $basisPoints % 10_000;         // 0 .. 9_999 ($basisPoints >= 0)

        $whole = self::guardInt($amountQ * $bpQ, $overflow);
        $whole = self::guardInt($whole * 10_000, $overflow);
        $whole = self::guardInt($whole + self::guardInt($amountQ * $bpR, $overflow), $overflow);
        $whole = self::guardInt($whole + self::guardInt($amountR * $bpQ, $overflow), $overflow);

        $smallNumerator = $amountR * $bpR;    // |.| < 10_000 * 10_000 = 1e8, cannot overflow
        $fraction = intdiv($smallNumerator, 10_000);
        $remainder = $smallNumerator % 10_000;

        $roundAwayFromZero = match ($mode) {
            RoundingMode::HALF_UP => abs($remainder) * 2 >= 10_000,
        };

        if ($roundAwayFromZero) {
            $fraction += $smallNumerator >= 0 ? 1 : -1;
        }

        return new self(self::guardInt($whole + $fraction, $overflow));
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
